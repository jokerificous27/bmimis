<?php

use App\Enums\Role;
use App\Livewire\Monitoring\Station;
use App\Models\ActivityLog;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Models\Unit;
use App\Services\AssessmentService;
use App\Services\WeighIn\ClaudeScaleReader;
use App\Services\WeighIn\ScaleReader;
use App\Services\WeighIn\ScaleReading;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    Storage::fake('local');
    Storage::fake('tmp-for-tests');
    config(['livewire.temporary_file_upload.disk' => 'tmp-for-tests']);

    $this->itms = Unit::where('code', 'ITMS')->first();
    $this->encoder = userWithRole(Role::HealthServiceEncoder);
    $this->actingAs($this->encoder);

    $this->personnel = personnelIn($this->itms);
    app(AssessmentService::class)->create($this->personnel, ['assessed_on' => today()->subDay()->format('Y-m-d'), 'height_cm' => 169, 'weight_kg' => 68], submit: true);
    $this->payload = $this->personnel->qrPayload();
});

/**
 * Use the AI weight reader with a fixed result (or switch reading off when not enabled).
 */
function fakeScaleReader(?ScaleReading $reading, bool $enabled = true): void
{
    app(Settings::class)->set(['weight_reader' => $enabled ? 'ai' : 'off']);

    app()->instance(ScaleReader::class, new class($reading, $enabled) implements ScaleReader
    {
        public function __construct(private ?ScaleReading $reading, private bool $enabled) {}

        public function isEnabled(): bool
        {
            return $this->enabled;
        }

        public function read(string $jpegBytes): ScaleReading
        {
            return $this->reading ?? ScaleReading::unavailable('off');
        }
    });
}

it('saves automatically from the reading made on the phone, without any API key', function () {
    config(['services.anthropic.key' => null]);
    MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['year' => 2025, 'month' => 12, 'weight_kg' => 68.5]);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->call('useDeviceReading', 67.7, 'high', '67.70')
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertHasNoErrors()
        ->assertSet('autoSaveHeld', true)
        ->call('saveWeightOnly')
        ->assertSet('lastSaved.automatic', true);

    $record = MonthlyWeightMonitoring::query()->where('capture_method', 'station')->sole();

    expect($record->weight_kg)->toBe(67.7)
        ->and($record->ai_weight_kg)->toBe(67.7)
        ->and($record->remarks)->toContain('read on device');
});

it('asks staff to type the weight when the phone could not read the display', function (mixed $weight, string $confidence) {
    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->call('useDeviceReading', $weight, $confidence, '')
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertSet('lastSaved', null)
        ->assertSet('personnelId', $this->personnel->id);

    expect(MonthlyWeightMonitoring::count())->toBe(0);
})->with([
    'not found' => [null, 'none'],
    'uncertain digits' => [67.7, 'low'],
    'nonsense value' => ['abc', 'high'],
]);

it('explains when the photo is too small to read the scale', function () {
    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->call('useDeviceReading', null, 'none', '', 582, 777)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertSet('personnelId', $this->personnel->id)
        ->assertSee('582 × 777 pixels')
        ->assertSee('full resolution');
});

it('saves a weight locked by the Smart camera and returns the name for its status', function () {
    $component = Livewire::test(Station::class)->call('identify', $this->payload);

    expect($component->effects['returns'][0] ?? null)->toBe($this->personnel->rank_name);

    $component->call('useDeviceReading', 59.8, 'high', '59.80', 2160, 3840, 3)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->call('saveWeightOnly')
        ->assertSet('lastSaved.automatic', true);

    expect(MonthlyWeightMonitoring::sole()->weight_kg)->toBe(59.8);
});

it('does not trust a reading with a different number of decimals than the scale shows', function () {
    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->call('useDeviceReading', 98.8, 'high', '98.8', 2160, 3840)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertSet('reading.confidence', 'low')
        ->assertSet('lastSaved', null);

    expect(MonthlyWeightMonitoring::count())->toBe(0);
});

it('ignores device readings when the reader is set to off', function () {
    app(Settings::class)->set(['weight_reader' => 'off']);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->call('useDeviceReading', 67.7, 'high', '67.70')
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertSet('weight', '')
        ->assertSet('lastSaved', null);
});

it('issues a random QR payload that does not reveal the personnel identity', function () {
    expect($this->payload)->toStartWith(Personnel::QR_PREFIX)
        ->not->toContain($this->personnel->employee_number)
        ->not->toContain($this->personnel->last_name);
});

it('issues compact QR codes that fit the smallest QR version', function () {
    expect($this->payload)->toMatch('/^PNPBMI:[0-9A-Z]{13}$/')
        ->and(strlen($this->payload))->toBeLessThanOrEqual(20);
});

it('still accepts QR cards printed before the compact format', function () {
    $this->personnel->forceFill(['qr_token' => 'kQDEqLjHjWt47wF0lXwvHMCHMvC3q3hQG52XO9Wd'])->save();

    expect(Personnel::findByQrPayload('PNPBMI:kQDEqLjHjWt47wF0lXwvHMCHMvC3q3hQG52XO9Wd', $this->encoder)?->id)->toBe($this->personnel->id);
});

it('re-issues old long QR codes in the compact format', function () {
    $this->personnel->forceFill(['qr_token' => 'kQDEqLjHjWt47wF0lXwvHMCHMvC3q3hQG52XO9Wd'])->save();

    $this->artisan('bmi:compact-qr-codes', ['--force' => true])->assertSuccessful();

    expect($this->personnel->fresh()->qr_token)->toMatch('/^[0-9A-Z]{13}$/')
        ->and(Personnel::findByQrPayload('PNPBMI:kQDEqLjHjWt47wF0lXwvHMCHMvC3q3hQG52XO9Wd', $this->encoder))->toBeNull();
});

it('shows personnel their own QR full screen for the phone holder', function () {
    $ownerUser = userWithRole(Role::Personnel);
    $this->personnel->forceFill(['user_id' => $ownerUser->id])->save();

    $this->actingAs($ownerUser)->get(route('my.qr'))->assertOk()->assertSee($this->payload, false);
    $this->actingAs(userWithRole(Role::Personnel))->get(route('my.qr'))->assertNotFound();
});

it('identifies personnel from a scanned QR code', function () {
    fakeScaleReader(null, enabled: false);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->assertSet('personnelId', $this->personnel->id)
        ->assertSee($this->personnel->employee_number);
});

it('rejects unknown, foreign and re-issued QR codes', function () {
    fakeScaleReader(null, enabled: false);
    $oldPayload = $this->payload;
    $this->personnel->regenerateQrToken();

    Livewire::test(Station::class)
        ->call('identify', 'https://example.com/not-ours')->assertSet('personnelId', null)
        ->call('identify', 'PNPBMI:unknown-token')->assertSet('personnelId', null)
        ->call('identify', $oldPayload)->assertSet('personnelId', null)
        ->call('identify', $this->personnel->fresh()->qrPayload())->assertSet('personnelId', $this->personnel->id);
});

it('does not resolve QR codes of personnel outside the user scope', function () {
    $representative = userWithRole(Role::UnitRepresentative, Unit::where('code', 'FS')->first());

    expect(Personnel::findByQrPayload($this->payload, $representative))->toBeNull()
        ->and(Personnel::findByQrPayload($this->payload, $this->encoder)?->id)->toBe($this->personnel->id);
});

it('records the weight read from the photo after one confirmation', function () {
    fakeScaleReader(new ScaleReading(67.70, 'high', '67.7'));
    app(Settings::class)->set(['station_auto_save' => false]);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg', 1200, 900))
        ->assertHasNoErrors()
        ->assertSet('weight', '67.70')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('personnelId', null);

    $record = MonthlyWeightMonitoring::sole();

    expect($record->personnel_id)->toBe($this->personnel->id)
        ->and([$record->year, $record->month])->toBe([(int) today()->format('Y'), (int) today()->format('n')])
        ->and($record->weight_kg)->toBe(67.7)
        ->and($record->bmi)->toBe(23.7)
        ->and($record->capture_method)->toBe('station')
        ->and($record->ai_weight_kg)->toBe(67.7)
        ->and($record->ai_confidence)->toBe('high')
        ->and($record->remarks)->toContain('confirmed');

    Storage::disk('local')->assertExists($record->photo_path);
    expect(Storage::disk('local')->files('weigh-in-photos/pending'))->toBe([]);
});

it('saves a clear reading automatically as soon as the photo is taken', function () {
    fakeScaleReader(new ScaleReading(67.70, 'high', '67.7'));
    MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['year' => 2025, 'month' => 12, 'weight_kg' => 68.5]);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg', 1200, 900))
        ->assertHasNoErrors()
        ->assertSet('autoSaveHeld', true)
        ->assertSee('Update the waist, hip and wrist too?')
        ->call('saveWeightOnly')
        ->assertSet('personnelId', null)
        ->assertSet('lastSaved.automatic', true)
        ->assertSee('Saved automatically');

    $record = MonthlyWeightMonitoring::query()->where('capture_method', 'station')->sole();

    expect($record->weight_kg)->toBe(67.7)
        ->and($record->remarks)->toContain('saved automatically');
    Storage::disk('local')->assertExists($record->photo_path);
});

it('waits for staff when the automatic reading is not safe to save', function (ScaleReading $reading, ?float $previousWeight, string $reason) {
    fakeScaleReader($reading);

    if ($previousWeight !== null) {
        MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['year' => 2025, 'month' => 12, 'weight_kg' => $previousWeight]);
    }

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertSet('personnelId', $this->personnel->id)
        ->assertSet('lastSaved', null)
        ->assertSee($reason);

    expect(MonthlyWeightMonitoring::query()->where('capture_method', 'station')->exists())->toBeFalse();
})->with([
    'low confidence' => [new ScaleReading(67.70, 'medium', '67.7'), null, 'not clear enough'],
    'unreadable display' => [ScaleReading::unavailable('Blurred'), null, 'could not be read'],
    'large jump from last month' => [new ScaleReading(77.70, 'high', '77.7'), 68.0, 'differs by 9.70 kg'],
]);

it('does not save automatically when nobody was identified', function () {
    fakeScaleReader(new ScaleReading(67.70, 'high', '67.7'));

    Livewire::test(Station::class)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertSee('Nobody identified');

    expect(MonthlyWeightMonitoring::count())->toBe(0);
});

it('never lets the browser mark a staff save as automatic', function () {
    fakeScaleReader(null, enabled: false);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('weight', '67.70')
        ->call('save', automatic: true)
        ->assertSet('lastSaved.automatic', false);
});

it('keeps the staff correction when the AI reading was wrong', function () {
    fakeScaleReader(new ScaleReading(61.70, 'low', '61.7'));

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->set('weight', '67.70')
        ->call('save')
        ->assertHasNoErrors();

    $record = MonthlyWeightMonitoring::sole();

    expect($record->weight_kg)->toBe(67.7)
        ->and($record->ai_weight_kg)->toBe(61.7)
        ->and($record->remarks)->toContain('corrected by staff');
});

it('works without AI reading by typing the weight', function () {
    fakeScaleReader(null, enabled: false);

    Livewire::test(Station::class)
        ->call('choosePersonnel', $this->personnel->id)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertSet('weight', '')
        ->set('weight', '70.25')
        ->call('save')
        ->assertHasNoErrors();

    expect(MonthlyWeightMonitoring::sole()->weight_kg)->toBe(70.25)
        ->and(MonthlyWeightMonitoring::sole()->ai_weight_kg)->toBeNull();
});

it('blocks a second weigh-in in the same month and discards the photo', function () {
    fakeScaleReader(null, enabled: false);
    MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['year' => (int) today()->format('Y'), 'month' => (int) today()->format('n'), 'weight_kg' => 68]);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->set('weight', '70')
        ->call('save')
        ->assertHasErrors('weight_kg');

    expect(MonthlyWeightMonitoring::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('weigh-in-photos'))->toBe([]);
});

it('lets correction-authorized staff replace this month\'s weight after a re-weigh', function () {
    $this->actingAs(userWithRole(Role::HealthServiceAdmin));
    $record = MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['year' => (int) today()->format('Y'), 'month' => (int) today()->format('n'), 'weight_kg' => 55.35]);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->call('useDeviceReading', 59.8, 'high', '59.80')
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg'))
        ->assertSet('lastSaved', null)
        ->assertSet('weight', '59.80')
        ->call('replaceThisMonth')
        ->assertHasNoErrors()
        ->assertSet('lastSaved.weight', 59.8);

    $log = ActivityLog::where('action', 'monthly_weight.updated')->where('subject_id', $record->id)->latest('id')->first();

    expect(MonthlyWeightMonitoring::sole()->weight_kg)->toBe(59.8)
        ->and(MonthlyWeightMonitoring::sole()->photo_path)->not->toBeNull()
        ->and($log->old_values['weight_kg'])->toEqual(55.35)
        ->and($log->remarks)->toContain('Re-weighed at the Weigh-in Station');
});

it('does not let encoders without correction rights replace this month\'s weight', function () {
    MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['year' => (int) today()->format('Y'), 'month' => (int) today()->format('n'), 'weight_kg' => 55.35]);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('weight', '59.80')
        ->call('replaceThisMonth')
        ->assertForbidden();

    expect(MonthlyWeightMonitoring::sole()->weight_kg)->toBe(55.35);
});

it('rejects implausible weights', function () {
    fakeScaleReader(null, enabled: false);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('weight', '677')
        ->call('save')
        ->assertHasErrors('weight');
});

it('is only available to users who encode monitoring', function () {
    $this->actingAs(userWithRole(Role::UnitRepresentative, $this->itms))->get(route('monitoring.station'))->assertForbidden();
    $this->actingAs(userWithRole(Role::Personnel))->get(route('monitoring.station'))->assertForbidden();
});

it('protects weigh-in photos like other health records', function () {
    $ownerUser = userWithRole(Role::Personnel);
    $this->personnel->forceFill(['user_id' => $ownerUser->id])->save();
    Storage::disk('local')->put('weigh-in-photos/x.jpg', 'jpeg');
    $record = MonthlyWeightMonitoring::factory()->for($this->personnel)->create(['month' => 1, 'year' => 2025, 'photo_path' => 'weigh-in-photos/x.jpg']);

    $this->actingAs($ownerUser)->get(route('monitoring.photo', $record))->assertOk();
    $this->actingAs(userWithRole(Role::Personnel))->get(route('monitoring.photo', $record))->assertForbidden();
});

it('lets personnel print only their own QR card', function () {
    $ownerUser = userWithRole(Role::Personnel);
    $this->personnel->forceFill(['user_id' => $ownerUser->id])->save();
    $other = personnelIn($this->itms);

    $this->actingAs($ownerUser)->get(route('print.qr-cards', ['personnel_id' => $this->personnel->id]))->assertOk()->assertSee($this->payload);
    $this->actingAs($ownerUser)->get(route('print.qr-cards', ['personnel_id' => $other->id]))->assertNotFound();
    $this->actingAs($ownerUser)->get(route('print.qr-cards'))->assertForbidden();
});

it('prints QR cards for a whole unit', function () {
    $other = personnelIn($this->itms);
    $outsider = personnelIn(Unit::where('code', 'FS')->first());

    $this->get(route('print.qr-cards', ['unit_id' => $this->itms->id]))
        ->assertOk()
        ->assertSee($this->personnel->employee_number)
        ->assertSee($other->employee_number)
        ->assertDontSee($outsider->employee_number);

    expect($other->fresh()->qr_token)->not->toBeNull();
});

it('interprets the AI scale reading safely', function () {
    $reader = new ClaudeScaleReader(app(Settings::class));

    expect($reader->toReading(['readable' => true, 'weight' => 67.7, 'unit' => 'kg', 'confidence' => 'high', 'displayed_text' => '67.7', 'note' => ''])->toArray())
        ->toMatchArray(['weight_kg' => 67.7, 'confidence' => 'high'])
        ->and($reader->toReading(['readable' => true, 'weight' => 150, 'unit' => 'lb', 'confidence' => 'high', 'displayed_text' => '150', 'note' => ''])->weightKg)->toBe(68.04)
        ->and($reader->toReading(['readable' => true, 'weight' => 67.7, 'unit' => 'unknown', 'confidence' => 'high', 'displayed_text' => '67.7', 'note' => ''])->confidence)->toBe('medium')
        ->and($reader->toReading(['readable' => false, 'weight' => 0, 'unit' => 'unknown', 'confidence' => 'low', 'displayed_text' => '', 'note' => 'Blurred'])->hasWeight())->toBeFalse()
        ->and($reader->toReading(null)->hasWeight())->toBeFalse();
});

it('keeps AI reading off unless chosen and an API key is configured', function () {
    $settings = app(Settings::class);
    config(['services.anthropic.key' => 'test-key']);

    expect($settings->get('weight_reader'))->toBe('device')
        ->and((new ClaudeScaleReader($settings))->isEnabled())->toBeFalse();

    $settings->set(['weight_reader' => 'ai']);
    expect((new ClaudeScaleReader($settings))->isEnabled())->toBeTrue();

    config(['services.anthropic.key' => null]);
    expect((new ClaudeScaleReader($settings))->isEnabled())->toBeFalse()
        ->and((new ClaudeScaleReader($settings))->read('jpeg')->hasWeight())->toBeFalse();
});

it('asks whether to update waist, hip and wrist before saving, then saves them with the weight', function () {
    fakeScaleReader(new ScaleReading(75.0, 'high', '75.0'));
    $baseline = $this->personnel->assessments()->sole();
    $baseline->forceFill(['waist_cm' => 82, 'hip_cm' => 96, 'wrist_cm' => 16.5])->save();

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg', 1200, 900))
        ->assertSet('autoSaveHeld', true)
        ->assertSee('Update the waist, hip and wrist too?');

    expect(MonthlyWeightMonitoring::count())->toBe(0);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg', 1200, 900))
        ->call('startMeasurements')
        ->assertSet('waist_cm', '82.0')
        ->set('waist_cm', '90')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('lastSaved.measurements', true);

    $record = MonthlyWeightMonitoring::sole();
    $assessment = $this->personnel->assessments()->whereKeyNot($baseline->id)->sole();

    expect($record->weight_kg)->toBe(75.0)
        ->and($record->measurement_review)->toBeNull()
        ->and($assessment->assessed_on->isToday())->toBeTrue()
        ->and($assessment->weight_kg)->toBe(75.0)
        ->and($assessment->height_cm)->toBe(169.0)
        ->and($assessment->waist_cm)->toBe(90.0)
        ->and($assessment->hip_cm)->toBe(96.0)
        ->and($assessment->wrist_cm)->toBe(16.5);
});

it('needs at least one measurement and saves nothing until it is given', function () {
    fakeScaleReader(null, enabled: false);

    Livewire::test(Station::class)
        ->call('identify', $this->payload)
        ->set('weight', '70')
        ->call('startMeasurements')
        ->set('waist_cm', '')
        ->set('hip_cm', '')
        ->set('wrist_cm', '')
        ->call('save')
        ->assertHasErrors('waist_cm');

    expect(MonthlyWeightMonitoring::count())->toBe(0)
        ->and($this->personnel->assessments()->count())->toBe(1);
});

it('saves automatically without asking when there is no height on record to measure against', function () {
    fakeScaleReader(new ScaleReading(67.70, 'high', '67.7'));
    $newcomer = personnelIn($this->itms);

    Livewire::test(Station::class)
        ->call('identify', $newcomer->qrPayload())
        ->set('photo', UploadedFile::fake()->image('weigh-in.jpg', 1200, 900))
        ->assertSet('lastSaved.automatic', true);
});
