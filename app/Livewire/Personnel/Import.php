<?php

namespace App\Livewire\Personnel;

use App\Enums\Permission;
use App\Services\Import\DataImporter;
use App\Support\Spreadsheet\SpreadsheetReader;
use App\Support\Spreadsheet\XlsxWriter;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Title('Import Data')]
class Import extends Component
{
    use WithFileUploads;

    public string $type = 'personnel';

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
        abort_unless(Auth::user()->hasPermission(Permission::ImportPersonnel), 403);
    }

    public function updatedType(): void
    {
        $this->reset('file', 'result', 'filename');
    }

    public function analyze(DataImporter $importer, SpreadsheetReader $reader): void
    {
        $this->validate([
            'type' => ['required', Rule::in(['personnel', 'monthly'])],
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
        ]);

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

        if (count($rows) > 5000) {
            $this->addError('file', __('Import at most 5,000 rows at a time.'));

            return;
        }

        $this->filename = $this->file->getClientOriginalName();
        $this->result = $importer->validate($this->type, $rows, Auth::user());
    }

    public function commit(DataImporter $importer): void
    {
        abort_unless($this->result && $this->result['valid'] !== [], 422);

        $count = $importer->commit($this->type, $this->result['valid'], Auth::user(), $this->filename);

        $this->reset('file', 'result', 'filename');
        Flux::toast(variant: 'success', text: trans_choice(':count record imported.|:count records imported.', $count, ['count' => $count]));
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $writer = new XlsxWriter;

        if ($this->type === 'monthly') {
            $writer->addSheet('Monthly weights', DataImporter::MONTHLY_COLUMNS, [['DEMO-00001', (int) today()->format('Y'), 1, 68.5, today()->format('Y').'-01-10', '']]);
        } else {
            $writer->addSheet('Personnel', DataImporter::PERSONNEL_COLUMNS, [['PNP-000001', 'PCpl', 'Dela Cruz', 'Juan', 'Santos', '', 'M', '1995-05-20', 'ITMS', 'Admin PNCO', '09171234567', '']]);
        }

        return response()->download($writer->save(), "bmi-{$this->type}-import-template.xlsx")->deleteFileAfterSend();
    }

    public function render(): View
    {
        return view('livewire.personnel.import', [
            'columns' => $this->type === 'monthly' ? DataImporter::MONTHLY_COLUMNS : DataImporter::PERSONNEL_COLUMNS,
        ]);
    }
}
