<?php

use App\Enums\Role;
use App\Livewire\Personnel\Photos;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedReferenceData();
    Storage::fake('local');

    $this->itms = Unit::where('code', 'ITMS')->first();
    $this->ownerUser = userWithRole(Role::Personnel);
    $this->owner = personnelIn($this->itms, ['user_id' => $this->ownerUser->id]);
});

it('lets personnel upload their profile, left, center and right view pictures', function () {
    $component = Livewire::actingAs($this->ownerUser)->test(Photos::class, ['personnel' => $this->owner]);

    foreach (['profile', 'left', 'center', 'right'] as $view) {
        $component->set($view, UploadedFile::fake()->image($view.'.jpg', 600, 800))->assertHasNoErrors();
    }

    $owner = $this->owner->fresh();

    foreach (['photo_path', 'photo_left_path', 'photo_center_path', 'photo_right_path'] as $column) {
        expect($owner->{$column})->not->toBeNull();
        Storage::disk('local')->assertExists($owner->{$column});
    }

    $this->actingAs($this->ownerUser);
    $this->get(route('my.profile', ['tab' => 'photos']))->assertOk()->assertSee('Left view')->assertSee('Shown on QR scan');
    $this->get($owner->viewPhotoUrl('left'))->assertOk();
    $this->get($owner->viewPhotoUrl('right'))->assertOk();
});

it('shows the uploaded profile picture as the identification picture and replaces the old file', function () {
    $component = Livewire::actingAs($this->ownerUser)->test(Photos::class, ['personnel' => $this->owner])
        ->set('profile', UploadedFile::fake()->image('first.jpg', 600, 800));

    $first = $this->owner->fresh()->photo_path;
    expect($this->owner->fresh()->displayPhotoPath())->toBe($first);

    $component->set('profile', UploadedFile::fake()->image('second.png', 600, 800));

    $second = $this->owner->fresh()->photo_path;
    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);

    $component->call('remove', 'profile');

    expect($this->owner->fresh()->photo_path)->toBeNull();
    Storage::disk('local')->assertMissing($second);
});

it('uses the center view picture when there is no profile picture', function () {
    Livewire::actingAs($this->ownerUser)->test(Photos::class, ['personnel' => $this->owner])
        ->set('center', UploadedFile::fake()->image('center.jpg', 600, 800));

    $owner = $this->owner->fresh();

    expect($owner->displayPhotoPath())->toBe($owner->photo_center_path);
});

it('rejects files that are not pictures', function () {
    Livewire::actingAs($this->ownerUser)->test(Photos::class, ['personnel' => $this->owner])
        ->set('left', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
        ->assertHasErrors('left');

    expect($this->owner->fresh()->photo_left_path)->toBeNull();
});

it('does not let other personnel or view-only staff change the pictures', function () {
    $colleague = userWithRole(Role::Personnel);
    personnelIn($this->itms, ['user_id' => $colleague->id]);

    Livewire::actingAs($colleague)->test(Photos::class, ['personnel' => $this->owner])->assertForbidden();

    $representative = userWithRole(Role::UnitRepresentative, $this->itms);

    Livewire::actingAs($representative)->test(Photos::class, ['personnel' => $this->owner])
        ->assertOk()
        ->assertDontSee('type="file"', false)
        ->set('left', UploadedFile::fake()->image('left.jpg'))
        ->assertForbidden();

    expect($this->owner->fresh()->photo_left_path)->toBeNull();

    Storage::disk('local')->put('personnel-photos/x.jpg', 'jpeg');
    $this->owner->forceFill(['photo_left_path' => 'personnel-photos/x.jpg'])->save();

    $this->actingAs($colleague)->get(route('personnel.photo', ['personnel' => $this->owner, 'view' => 'left']))->assertForbidden();
    $this->actingAs($representative)->get(route('personnel.photo', ['personnel' => $this->owner, 'view' => 'left']))->assertOk();
});

it('lets staff who manage the record upload pictures for the personnel', function () {
    Livewire::actingAs(userWithRole(Role::HealthServiceAdmin))->test(Photos::class, ['personnel' => $this->owner])
        ->set('right', UploadedFile::fake()->image('right.jpg', 600, 800))
        ->assertHasNoErrors();

    expect($this->owner->fresh()->photo_right_path)->not->toBeNull();
});
