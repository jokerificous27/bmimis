<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator-configurable system settings with documented defaults.
 */
class Settings
{
    private const CACHE_KEY = 'system_settings';

    /**
     * Default values. These are system defaults, not official PNP policy values;
     * administrators should align them with the applicable Health Service issuances.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'organization_name' => 'PHILIPPINE NATIONAL POLICE, HEALTH SERVICE',
        'facility_name' => 'GENERAL HOSPITAL',
        'facility_address' => 'Camp BGen Rafael T Crame, Quezon City',

        'bmi_precision' => 1,
        'ibw_method' => 'bmi_range',
        'ibw_bmi_lower' => 18.5,
        'ibw_bmi_upper' => 25.0,
        'ibw_tolerance_percent' => 10,

        'height_min_cm' => 120,
        'height_max_cm' => 230,
        'weight_min_kg' => 30,
        'weight_max_kg' => 250,
        'waist_min_cm' => 40,
        'waist_max_cm' => 200,
        'hip_min_cm' => 50,
        'hip_max_cm' => 200,
        'wrist_min_cm' => 8,
        'wrist_max_cm' => 30,

        'monitoring_deadline_day' => 25,
        'reminder_days_before' => 5,
        'compliance_target_percent' => 90,
        'assessment_validity_months' => 12,
        'significant_weight_change_kg' => 3.0,
        'trend_consecutive_months' => 3,
        // BMI points a monthly weigh-in may differ from the last assessment before administrators are asked
        // whether to measure waist, hip and wrist again. An operational setting, not an official PNP standard.
        'significant_bmi_change' => 2.0,
        'follow_up_default_days' => 30,

        'default_hs_representative_name' => '',
        'default_hs_representative_designation' => '',
        'notifications_email_enabled' => false,

        // Weigh-in Station: how the weight is read from the scale photo. "device" reads the LED display on
        // the phone itself (free, offline); "ai" sends the photo to the Anthropic API; "off" means staff type it.
        'weight_reader' => 'device',
        // Save clear, plausible readings without a confirmation tap (only when a reader is enabled).
        'station_auto_save' => true,
        // Decimals shown on the scale display ("1", "2" or "auto" for either). A reading with a different
        // number of decimals is never trusted, which rules out stray marks read as digits.
        'scale_decimals' => '2',
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $resolved = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public function float(string $key): float
    {
        return (float) $this->get($key);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return filter_var($this->get($key), FILTER_VALIDATE_BOOL);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, function (): array {
            if (! Schema::hasTable('system_settings')) {
                return [];
            }

            return SystemSetting::query()->pluck('value', 'key')->all();
        });

        return $this->resolved = array_merge(self::DEFAULTS, $stored);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = SystemSetting::firstOrNew(['key' => $key]);

            if ($setting->exists && $setting->value === $value) {
                continue;
            }

            $setting->value = $value;
            $setting->updated_by = Auth::id();
            $setting->save();
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
    }
}
