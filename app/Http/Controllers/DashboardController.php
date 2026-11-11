<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends each user to the dashboard that matches their role.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match (true) {
            $user->hasPermission(Permission::ViewAllRecords) => redirect()->route('dashboard.health-service'),
            $user->hasRole(Role::UnitRepresentative) && $user->hasPermission(Permission::ViewCompliance) => redirect()->route('dashboard.unit'),
            $user->isStaff() && $user->hasPermission(Permission::ViewCompliance) => redirect()->route('dashboard.health-service'),
            default => redirect()->route('my.dashboard'),
        };
    }
}
