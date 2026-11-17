<?php

use App\Enums\Role;
use App\Livewire\Admin\UsersUpload;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use App\Services\Import\UserAccountImporter;
use App\Support\Spreadsheet\SpreadsheetReader;
use App\Support\Spreadsheet\XlsxWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    $this->admin = userWithRole(Role::SuperAdmin);
    $this->itms = Unit::where('code', 'ITMS')->first();
    $this->finance = Unit::where('code', 'FS')->first();

    $this->ana = personnelIn($this->itms, ['employee_number' => 'PNP-200001', 'email' => 'ana@example.test']);
    $this->ben = personnelIn($this->itms, ['employee_number' => 'PNP-200002', 'email' => null]);
    $this->withAccount = personnelIn($this->itms, ['employee_number' => 'PNP-200003', 'user_id' => userWithRole(Role::Personnel)->id]);
    $this->otherUnit = personnelIn($this->finance, ['employee_number' => 'PNP-200004']);
});

function usersCsv(string ...$lines): UploadedFile
{
    return UploadedFile::fake()->createWithContent('users.csv', implode(',', UserAccountImporter::COLUMNS)."\n".implode("\n", $lines)."\n");
}

it('exports the personnel of a unit who have no account yet, in the upload format', function () {
    $rows = app(UserAccountImporter::class)->exportRows($this->itms);

    expect(array_column($rows, 0))->toBe(collect([$this->ana, $this->ben])->sortBy('last_name')->pluck('employee_number')->values()->all())
        ->and(collect($rows)->firstWhere(0, 'PNP-200001'))->toMatchArray([2 => 'ana@example.test', 3 => '', 4 => 'personnel', 5 => 'ITMS']);

    Livewire::actingAs($this->admin)->test(UsersUpload::class)
        ->set('unit_id', (string) $this->itms->id)
        ->call('export')
        ->assertHasNoErrors()
        ->assertFileDownloaded('users-upload-itms-'.today()->format('Y-m-d').'.xlsx');

    // The exported workbook can be uploaded back as is.
    $path = (new XlsxWriter)->addSheet('Users', UserAccountImporter::COLUMNS, $rows)->save();
    $read = app(SpreadsheetReader::class)->read($path, 'xlsx');

    expect($read)->toHaveCount(2)->and(array_keys($read[0]))->toBe(UserAccountImporter::COLUMNS);
});

it('creates accounts from the uploaded file and links them to the personnel', function () {
    $component = Livewire::actingAs($this->admin)->test(UsersUpload::class)
        ->set('file', usersCsv(
            'PNP-200001,,ana@example.test,,,ITMS,',
            'PNP-200002,,Ben@Example.test,Secret-Pass-123,personnel,ITMS,Clerk',
        ))
        ->call('analyze')
        ->assertHasNoErrors();

    expect($component->get('result')['valid'])->toHaveCount(2)
        ->and($component->get('result')['errors'])->toBe([])
        ->and(json_encode($component->get('result')))->not->toContain('Secret-Pass-123')
        ->and(User::where('email', 'ana@example.test')->exists())->toBeFalse();

    $component->call('commit')->assertFileDownloaded();

    $ana = User::where('email', 'ana@example.test')->sole();
    $ben = User::where('email', 'ben@example.test')->sole();

    expect($ana->role)->toBe(Role::Personnel)
        ->and($ana->is_active)->toBeTrue()
        ->and($ana->name)->toBe($this->ana->fresh()->rank_name)
        ->and($this->ana->fresh()->user_id)->toBe($ana->id)
        ->and($this->ben->fresh()->user_id)->toBe($ben->id)
        ->and($ben->designation)->toBe('Clerk')
        ->and(Hash::check('Secret-Pass-123', $ben->password))->toBeTrue()
        ->and(ActivityLog::where('action', 'import.users')->exists())->toBeTrue();
});

it('reports problem rows and skips them', function () {
    $existing = userWithRole(Role::HealthServiceEncoder);

    $component = Livewire::actingAs($this->admin)->test(UsersUpload::class)
        ->set('file', usersCsv(
            'PNP-200001,,ana@example.test,,,,',
            'PNP-200002,,,,,,',
            'PNP-200003,,taken@example.test,,,,',
            'PNP-999999,,ghost@example.test,,,,',
            "PNP-200004,,{$existing->email},,,,",
            ',Rep One,rep@example.test,,unit_representative,,',
            ',Rep Two,rep2@example.test,,Unit Representative,ITMS,',
            ',Nobody,ana@example.test,,wizard,,',
        ))
        ->call('analyze');

    $result = $component->get('result');

    expect($result['total'])->toBe(8)
        ->and(array_column($result['valid'], '_row'))->toBe([2, 8])
        ->and(array_keys($result['errors']))->toBe([3, 4, 5, 6, 7, 9]);

    $component->call('commit');

    expect(User::where('email', 'rep2@example.test')->sole()->unit_id)->toBe($this->itms->id)
        ->and(User::where('email', 'rep2@example.test')->sole()->role)->toBe(Role::UnitRepresentative)
        ->and($this->withAccount->fresh()->user->email)->not->toBe('taken@example.test')
        ->and(User::where('email', 'rep@example.test')->exists())->toBeFalse();
});

it('only lets a super administrator upload super administrator accounts', function () {
    $manager = userWithRole(Role::HealthServiceAdmin);
    $rows = [['name' => 'Root', 'email' => 'root@example.test', 'role' => 'super_admin']];

    expect(app(UserAccountImporter::class)->validate($rows, $manager)['valid'])->toBe([])
        ->and(app(UserAccountImporter::class)->validate($rows, $this->admin)['valid'])->toHaveCount(1);
});

it('generates temporary passwords that meet the production password policy', function () {
    $password = UserAccountImporter::temporaryPassword();

    expect(strlen($password))->toBe(14)
        ->and($password)->toMatch('/^[A-Z]/')
        ->and($password)->toMatch('/[a-z]/')
        ->and($password)->toMatch('/[0-9]/')
        ->and($password)->toMatch('/[@#$%&*?!]/')
        ->and(UserAccountImporter::temporaryPassword())->not->toBe($password);
});

it('is limited to user managers', function () {
    $this->actingAs(userWithRole(Role::HealthServiceEncoder))->get(route('admin.users.upload'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.users.upload'))->assertOk()->assertSee('Export to Excel');
    $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()->assertSee(route('admin.users.upload'), false);
});
