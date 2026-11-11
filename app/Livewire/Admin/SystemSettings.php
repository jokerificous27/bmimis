<?php

namespace App\Livewire\Admin;

use App\Enums\IbwMethod;
use App\Services\Bmi\BmiClassifier;
use App\Support\Settings;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('System Settings')]
class SystemSettings extends Component
{
    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    public function mount(Settings $settings): void
    {
        $this->values = array_intersect_key($settings->all(), Settings::DEFAULTS);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'values.organization_name' => ['required', 'string', 'max:255'],
            'values.facility_name' => ['nullable', 'string', 'max:255'],
            'values.facility_address' => ['nullable', 'string', 'max:255'],
            'values.bmi_precision' => ['required', 'integer', 'between:1,2'],
            'values.ibw_method' => ['required', Rule::enum(IbwMethod::class)],
            'values.ibw_bmi_lower' => ['required', 'numeric', 'between:10,30'],
            'values.ibw_bmi_upper' => ['required', 'numeric', 'between:15,40', 'gt:values.ibw_bmi_lower'],
            'values.ibw_tolerance_percent' => ['required', 'numeric', 'between:0,30'],
            'values.height_min_cm' => ['required', 'numeric', 'between:50,200'],
            'values.height_max_cm' => ['required', 'numeric', 'between:100,260', 'gt:values.height_min_cm'],
            'values.weight_min_kg' => ['required', 'numeric', 'between:10,150'],
            'values.weight_max_kg' => ['required', 'numeric', 'between:50,400', 'gt:values.weight_min_kg'],
            'values.waist_min_cm' => ['required', 'numeric', 'between:10,150'],
            'values.waist_max_cm' => ['required', 'numeric', 'between:50,300', 'gt:values.waist_min_cm'],
            'values.hip_min_cm' => ['required', 'numeric', 'between:10,150'],
            'values.hip_max_cm' => ['required', 'numeric', 'between:50,300', 'gt:values.hip_min_cm'],
            'values.wrist_min_cm' => ['required', 'numeric', 'between:1,30'],
            'values.wrist_max_cm' => ['required', 'numeric', 'between:5,60', 'gt:values.wrist_min_cm'],
            'values.monitoring_deadline_day' => ['required', 'integer', 'between:1,31'],
            'values.reminder_days_before' => ['required', 'integer', 'between:0,27'],
            'values.compliance_target_percent' => ['required', 'numeric', 'between:1,100'],
            'values.assessment_validity_months' => ['required', 'integer', 'between:1,60'],
            'values.significant_weight_change_kg' => ['required', 'numeric', 'between:0.5,30'],
            'values.significant_bmi_change' => ['required', 'numeric', 'between:0.5,15'],
            'values.trend_consecutive_months' => ['required', 'integer', 'between:2,12'],
            'values.follow_up_default_days' => ['required', 'integer', 'between:1,365'],
            'values.default_hs_representative_name' => ['nullable', 'string', 'max:255'],
            'values.default_hs_representative_designation' => ['nullable', 'string', 'max:255'],
            'values.notifications_email_enabled' => ['boolean'],
            'values.weight_reader' => ['required', Rule::in(['device', 'ai', 'off'])],
            'values.station_auto_save' => ['boolean'],
            'values.scale_decimals' => ['required', Rule::in(['1', '2', 'auto'])],
        ];
    }

    public function save(Settings $settings): void
    {
        $validated = $this->validate()['values'];

        $numeric = ['bmi_precision', 'monitoring_deadline_day', 'reminder_days_before', 'assessment_validity_months', 'trend_consecutive_months', 'follow_up_default_days'];

        foreach ($validated as $key => $value) {
            if (in_array($key, $numeric, true)) {
                $validated[$key] = (int) $value;
            } elseif (is_numeric($value) && ! is_string(Settings::DEFAULTS[$key])) {
                $validated[$key] = (float) $value;
            } elseif ($value === null) {
                $validated[$key] = '';
            }
        }

        $validated['notifications_email_enabled'] = (bool) $validated['notifications_email_enabled'];
        $validated['station_auto_save'] = (bool) $validated['station_auto_save'];

        $settings->set($validated);
        app(BmiClassifier::class)->flush();

        Flux::toast(variant: 'success', text: __('Settings saved. Changes apply to new computations; existing records keep their stored results.'));
    }

    public function render(): View
    {
        return view('livewire.admin.system-settings', [
            'ibwMethods' => IbwMethod::options(),
        ]);
    }
}
