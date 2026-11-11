<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolePermission>
 */
class RolePermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => Role::HealthServiceEncoder,
            'permission' => Permission::EncodeMonitoring,
        ];
    }
}
