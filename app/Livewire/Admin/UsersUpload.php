<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Services\Import\UserAccountImporter;
use App\Support\Spreadsheet\SpreadsheetReader;
use App\Support\Spreadsheet\XlsxWriter;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk creation of login accounts: export the personnel of a unit who have no account yet as an
 * Excel file, complete it, then upload it back.
 */
#[Title('Users Upload')]
class UsersUpload extends Component
{
    use WithFileUploads;

    public string $unit_id = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /**
     * Validated rows. Locked so the browser cannot alter rows between validation and commit.
     *
     * @var array{valid: list<array<string, mixed>>, errors: array<int, list<string>>, total: int}|null
     */
    #[Locked]
    public ?array $result = null;

    #[Locked]
    public string $filename = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission(Permission::ManageUsers), 403);
    }

    #[Computed]
    public function unit(): ?Unit
    {
        return $this->unit_id !== '' ? Unit::find($this->unit_id) : null;
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->unit ? app(UserAccountImporter::class)->pendingPersonnel($this->unit)->count() : 0;
    }

    /**
     * Excel file of the unit's personnel who have no login account yet, in the upload format.
     */
    public function export(UserAccountImporter $importer): ?BinaryFileResponse
    {
        $this->validate(['unit_id' => ['required', Rule::exists('units', 'id')->whereNull('deleted_at')]], ['unit_id.required' => __('Select a unit first.')]);

        $unit = Unit::findOrFail($this->unit_id);
        $rows = $importer->exportRows($unit);

        if ($rows === []) {
            $this->addError('unit_id', __('All active personnel of :unit already have a login account.', ['unit' => $unit->code]));

            return null;
        }

        $writer = (new XlsxWriter)
            ->addSheet('Users', UserAccountImporter::COLUMNS, $rows)
            ->addSheet('Guide', ['Column', 'What to enter'], [
                ['employee_number', 'Personnel ID. Links the account to the personnel record. Do not change.'],
                ['name', 'Name shown on the account. Already filled in from the personnel record.'],
                ['email', 'Required. The email the user signs in with. Must be unique.'],
                ['password', 'Optional. Leave blank and the system generates a temporary password for each account.'],
                ['role', 'personnel (default), unit_representative, health_service_encoder or health_service_admin.'],
                ['unit', 'Unit code. Required for unit_representative.'],
                ['designation', 'Optional.'],
                ['', 'Delete the rows of personnel who should not get an account. Upload the file in User Management → Users Upload.'],
            ]);

        ActivityLog::record('export.users-upload', newValues: ['unit' => $unit->code, 'rows' => count($rows)], description: "Exported users upload file for {$unit->code}");

        return response()->download($writer->save(), 'users-upload-'.Str::slug($unit->code).'-'.today()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    public function analyze(UserAccountImporter $importer, SpreadsheetReader $reader): void
    {
        $this->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt']]);

        try {
            $rows = $reader->read($this->file->getRealPath(), $this->file->getClientOriginalExtension());
        } catch (RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        }

        if ($rows === []) {
            $this->addError('file', __('The file has no data rows.'));

            return;
        }

        if (count($rows) > UserAccountImporter::MAX_ROWS) {
            $this->addError('file', __('Upload at most :max accounts at a time. Split the file and upload it in parts.', ['max' => UserAccountImporter::MAX_ROWS]));

            return;
        }

        $this->filename = $this->file->getClientOriginalName();
        $this->result = $importer->validate($rows, Auth::user());
    }

    /**
     * Create the accounts. When the system generated passwords, they are downloaded once as an Excel
     * file for distribution; they are not stored anywhere in readable form.
     */
    public function commit(UserAccountImporter $importer): ?BinaryFileResponse
    {
        abort_unless($this->result && $this->result['valid'] !== [], 422);

        set_time_limit(300);

        $created = $importer->commit($this->result['valid'], $this->filename);
        $count = count($created);

        $this->reset('file', 'result', 'filename');
        unset($this->pendingCount);
        Flux::toast(variant: 'success', text: trans_choice(':count account created.|:count accounts created.', $count, ['count' => $count]));

        if (collect($created)->whereNotNull('password')->isEmpty()) {
            return null;
        }

        $writer = (new XlsxWriter)->addSheet(
            'Temporary passwords',
            ['employee_number', 'name', 'email', 'temporary_password'],
            array_map(fn (array $account): array => [$account['employee_number'], $account['name'], $account['email'], $account['password'] ?? '(as set in the uploaded file)'], $created),
        );

        return response()->download($writer->save(), 'new-accounts-'.now()->format('Y-m-d-His').'.xlsx')->deleteFileAfterSend();
    }

    public function render(): View
    {
        return view('livewire.admin.users-upload', [
            'columns' => UserAccountImporter::COLUMNS,
            'allUnits' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
