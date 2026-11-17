<?php

namespace App\Services\Import;

use App\Enums\PersonnelStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Personnel;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates login accounts in bulk from a spreadsheet. The spreadsheet starts as an export of the
 * personnel of one unit who have no account yet, so administrators do not type each account.
 * Validation runs first; nothing is saved until the administrator confirms.
 */
class UserAccountImporter
{
    public const COLUMNS = ['employee_number', 'name', 'email', 'password', 'role', 'unit', 'designation'];

    /**
     * Passwords are hashed one by one, so a file is limited to keep the request short.
     */
    public const MAX_ROWS = 250;

    /**
     * Personnel of the unit who have no login account yet.
     *
     * @return Builder<Personnel>
     */
    public function pendingPersonnel(Unit $unit): Builder
    {
        return Personnel::query()
            ->where('unit_id', $unit->id)
            ->where('status', PersonnelStatus::Active->value)
            ->whereNull('user_id');
    }

    /**
     * One row per personnel of the unit without an account, ready to be completed and uploaded.
     *
     * @return list<list<string>>
     */
    public function exportRows(Unit $unit): array
    {
        return $this->pendingPersonnel($unit)
            ->with('rank:id,abbreviation')
            ->orderBy('last_name')->orderBy('first_name')
            ->get()
            ->map(fn (Personnel $personnel): array => [
                $personnel->employee_number,
                $personnel->rank_name,
                (string) $personnel->email,
                '',
                Role::Personnel->value,
                $unit->code,
                (string) $personnel->position,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{valid: list<array<string, mixed>>, errors: array<int, list<string>>, total: int}
     */
    public function validate(array $rows, User $actor): array
    {
        $roles = collect(Role::cases())
            ->reject(fn (Role $role) => $role === Role::SuperAdmin && ! $actor->isSuperAdmin())
            ->flatMap(fn (Role $role) => [mb_strtolower($role->value) => $role->value, mb_strtolower($role->label()) => $role->value]);
        $units = Unit::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => $id]);
        $numbers = collect($rows)->map(fn (array $row) => mb_strtolower(trim($row['employee_number'] ?? '')))->filter()->unique()->values()->all();
        $personnel = Personnel::query()->whereIn(DB::raw('LOWER(employee_number)'), $numbers)->with('rank:id,abbreviation')->get()->keyBy(fn (Personnel $p) => mb_strtolower($p->employee_number));
        $existingEmails = User::query()->pluck('email')->map(fn ($email) => mb_strtolower($email))->flip();
        $seenEmails = [];
        $seenNumbers = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $row = array_map('trim', $row);
            $messages = [];

            $number = $row['employee_number'] ?? '';
            $person = $number !== '' ? $personnel->get(mb_strtolower($number)) : null;
            $roleInput = $row['role'] ?? '';
            $role = $roleInput === '' ? Role::Personnel->value : ($roles[mb_strtolower($roleInput)] ?? null);
            $unitInput = $row['unit'] ?? '';
            $unitId = $unitInput === '' ? null : ($units[mb_strtolower($unitInput)] ?? null);
            $email = mb_strtolower($row['email'] ?? '');
            $password = $row['password'] ?? '';

            $data = [
                'personnel_id' => $person?->id,
                'employee_number' => $number,
                'name' => ($row['name'] ?? '') ?: (string) $person?->rank_name,
                'email' => $email,
                'role' => $role,
                'unit_id' => $unitId,
                'designation' => ($row['designation'] ?? '') ?: null,
            ];

            $validator = Validator::make($data + ['password' => $password], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'role' => ['required'],
                'designation' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', Password::defaults()],
            ], [
                'name.required' => 'Name is required when the row has no employee number.',
                'email.required' => 'Email is required: it is what the user signs in with.',
                'role.required' => "Unknown role \"{$roleInput}\". Use one of: ".$roles->values()->unique()->implode(', ').'.',
            ]);

            $messages = $validator->errors()->all();

            if ($number !== '' && ! $person) {
                $messages[] = "Employee number \"{$number}\" was not found in the Personnel Directory.";
            }

            if ($person?->user_id) {
                $messages[] = 'This personnel already has a login account.';
            }

            if ($number === '' && $role === Role::Personnel->value) {
                $messages[] = 'Employee number is required for PNP Personnel accounts, so the account opens their own records.';
            }

            if ($unitInput !== '' && $unitId === null) {
                $messages[] = "Unknown unit code \"{$unitInput}\".";
            }

            if ($role === Role::UnitRepresentative->value && $unitId === null && $unitInput === '') {
                $messages[] = 'Unit Representatives must be assigned to a unit.';
            }

            if ($email !== '' && $existingEmails->has($email)) {
                $messages[] = 'An account with this email already exists.';
            }

            if ($email !== '' && isset($seenEmails[$email])) {
                $messages[] = "Duplicate email (also on row {$seenEmails[$email]}).";
            }

            $numberKey = mb_strtolower($number);

            if ($numberKey !== '' && isset($seenNumbers[$numberKey])) {
                $messages[] = "Duplicate employee number (also on row {$seenNumbers[$numberKey]}).";
            }

            if ($email !== '') {
                $seenEmails[$email] = $line;
            }

            if ($numberKey !== '') {
                $seenNumbers[$numberKey] = $line;
            }

            if ($messages) {
                $errors[$line] = array_values(array_unique($messages));
            } else {
                // Kept encrypted while the administrator reviews the validation report.
                $valid[] = $data + ['password' => $password === '' ? null : Crypt::encryptString($password), '_row' => $line];
            }
        }

        return ['valid' => $valid, 'errors' => $errors, 'total' => count($rows)];
    }

    /**
     * Create the accounts. Rows without a password get a random temporary one, returned so it can be
     * handed to the user once.
     *
     * @param  list<array<string, mixed>>  $valid
     * @return list<array{employee_number: string, name: string, email: string, password: string|null}>
     */
    public function commit(array $valid, string $filename): array
    {
        $created = DB::transaction(function () use ($valid): array {
            $created = [];

            foreach ($valid as $data) {
                $generated = $data['password'] === null ? self::temporaryPassword() : null;

                $user = new User([
                    'name' => $data['name'],
                    'designation' => $data['designation'],
                    'email' => $data['email'],
                    'password' => $generated ?? Crypt::decryptString($data['password']),
                ]);
                $user->forceFill([
                    'role' => Role::from($data['role']),
                    'unit_id' => $data['unit_id'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ])->save();

                if ($data['personnel_id']) {
                    $personnel = Personnel::query()->lockForUpdate()->find($data['personnel_id']);

                    abort_if(! $personnel || $personnel->user_id !== null, 409, "Row {$data['_row']}: the personnel was linked to another account in the meantime. Validate the file again.");

                    $personnel->forceFill(['user_id' => $user->id])->save();
                }

                $created[] = [
                    'employee_number' => $data['employee_number'],
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $generated,
                ];
            }

            return $created;
        });

        ActivityLog::record('import.users', newValues: ['file' => $filename, 'imported' => count($created)], description: 'Created '.count($created).' user accounts from an uploaded file');

        return $created;
    }

    /**
     * 14 characters with upper and lower case letters, digits and a symbol; no look-alike characters
     * (0/O, 1/l/I) because it is retyped from a printout. Starts with a letter so spreadsheets do not
     * treat it as a formula.
     */
    public static function temporaryPassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '@#$%&*?!';
        $pick = fn (string $set): string => $set[random_int(0, strlen($set) - 1)];

        $rest = [$pick($lower), $pick($lower), $pick($upper), $pick($digits), $pick($digits), $pick($symbols)];

        while (count($rest) < 13) {
            $rest[] = $pick($upper.$lower.$digits);
        }

        for ($i = count($rest) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$rest[$i], $rest[$j]] = [$rest[$j], $rest[$i]];
        }

        return $pick($upper).implode('', $rest);
    }
}
