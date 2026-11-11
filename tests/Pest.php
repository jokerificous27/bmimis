<?php

use App\Enums\Role;
use App\Models\Personnel;
use App\Models\Rank;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Ranks, units, WHO/provisional PNP standards, sample packages and the default permission matrix are
 * created by the "seed_reference_data" migration, so every test database has them. Tests call this
 * to state that they rely on that data.
 */
function seedReferenceData(): void
{
    expect(Rank::query()->exists())->toBeTrue('The reference data migration did not run.');
}

function userWithRole(Role $role, ?Unit $unit = null): User
{
    return User::factory()->role($role, $unit)->create();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function personnelIn(Unit $unit, array $attributes = []): Personnel
{
    return Personnel::factory()->create(array_merge([
        'unit_id' => $unit->id,
        'rank_id' => Rank::query()->value('id') ?? Rank::factory(),
        'sex' => 'male',
        'date_of_birth' => '1995-03-15',
    ], $attributes));
}
