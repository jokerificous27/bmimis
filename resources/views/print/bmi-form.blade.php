@php
    /** @var \App\Models\BmiAssessment $assessment */
    $personnel = $assessment->personnel;
    $rank = $assessment->rank ?? $personnel->rank;
@endphp

<x-layouts::print :title="__('Body Mass Index (BMI) Form')" orientation="portrait">
    <x-slot:head>
        <style>
            .bmi-form { margin-bottom: 4mm; }
            .bmi-form .grid-head td { border: none; border-bottom: 1px solid #333; padding: 2px 6px 3px; }
            .bmi-form .grid-head .label { display: block; }
            .bmi-form .measure th { width: 26%; }
            .bmi-form .measure td.result { width: 20%; text-align: center; font-weight: 700; font-size: 13px; }
            .bmi-form .measure td.remarks { font-size: 12px; }
            .bmi-form .bmi-result { font-size: 18px; font-weight: 700; }
            .status-flag { display: inline-block; border: 1px solid #b45309; color: #b45309; padding: 1px 6px; font-size: 9px; border-radius: 3px; }
        </style>
    </x-slot:head>

    <div class="sheet portrait">
        @for ($copy = 1; $copy <= $copies; $copy++)
            <div class="bmi-form">
                @include('print.partials.letterhead')
                <div class="doc-title">BODY MASS INDEX (BMI) FORM</div>

                <table class="grid-head" style="margin-bottom: 8px;">
                    <tr>
                        <td style="width: 14%"><span class="label">Rank</span><span class="value">{{ $rank?->abbreviation }}</span></td>
                        <td style="width: 22%"><span class="label">Surname</span><span class="value">{{ $personnel->last_name }}{{ $personnel->suffix ? ' '.$personnel->suffix : '' }}</span></td>
                        <td style="width: 26%"><span class="label">First name</span><span class="value">{{ $personnel->first_name }}</span></td>
                        <td style="width: 7%"><span class="label">MI</span><span class="value">{{ $personnel->middle_initial }}</span></td>
                        <td style="width: 7%"><span class="label">Q</span><span class="value">{{ $personnel->qualifier }}</span></td>
                        <td><span class="label">Signature</span><span class="value">&nbsp;</span></td>
                    </tr>
                    <tr>
                        <td><span class="label">Age</span><span class="value">{{ $assessment->age_at_assessment }}</span></td>
                        <td><span class="label">Sex</span><span class="value">{{ $assessment->sex?->initial() ?? $personnel->sex->initial() }}</span></td>
                        <td colspan="3"><span class="label">Office</span><span class="value">{{ $assessment->unit?->name ?? $personnel->unit?->name }}</span></td>
                        <td><span class="label">Date</span><span class="value">{{ $assessment->assessed_on->format('F j, Y') }}</span></td>
                    </tr>
                </table>

                <table class="measure">
                    <tr>
                        <th>Charts</th>
                        <th class="center">Result</th>
                        <th>Remarks</th>
                    </tr>
                    <tr>
                        <th>Height (cm)</th>
                        <td class="result">{{ rtrim(rtrim(number_format($assessment->height_cm, 1), '0'), '.') }}</td>
                        <td class="remarks" rowspan="3"><strong>IBW:</strong> {{ $assessment->ibw_min_kg !== null ? number_format($assessment->ibw_min_kg, 1).' kgs to '.number_format($assessment->ibw_max_kg, 1).' kgs' : '—' }}</td>
                    </tr>
                    <tr>
                        <th>Weight (kilogram)</th>
                        <td class="result">{{ number_format($assessment->weight_kg, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Waist (cm)</th>
                        <td class="result">{{ $assessment->waist_cm !== null ? rtrim(rtrim(number_format($assessment->waist_cm, 1), '0'), '.') : '' }}</td>
                    </tr>
                    <tr>
                        <th>Hip (cm)</th>
                        <td class="result">{{ $assessment->hip_cm !== null ? rtrim(rtrim(number_format($assessment->hip_cm, 1), '0'), '.') : '' }}</td>
                        <td class="remarks" rowspan="2"><strong>Weight to lose:</strong> {{ $assessment->weightActionText() }}</td>
                    </tr>
                    <tr>
                        <th>Wrist (cm)</th>
                        <td class="result">{{ $assessment->wrist_cm !== null ? rtrim(rtrim(number_format($assessment->wrist_cm, 1), '0'), '.') : '' }}</td>
                    </tr>
                    <tr>
                        <th>BMI Result</th>
                        <td class="result bmi-result">{{ number_format($assessment->bmi, 1) }}</td>
                        <td class="remarks">
                            <div><strong>PNP:</strong> {{ $assessment->pnp_classification ?? '—' }}</div>
                            <div><strong>WHO:</strong> {{ $assessment->who_classification ?? '—' }}</div>
                        </td>
                    </tr>
                </table>

                <table style="margin-top: 8px; border: none;">
                    <tr>
                        <td style="border: none; width: 33%;" class="signature">
                            <div class="label" style="text-align:left; margin-bottom: 20px;">Unit Representative:</div>
                            <div class="line">{{ $assessment->unit_representative_name ?: ' ' }}</div>
                            <div class="role">{{ $assessment->unit_representative_designation }}</div>
                        </td>
                        <td style="border: none; width: 34%;" class="signature">
                            <div class="label" style="text-align:left; margin-bottom: 20px;">Health Service Representative:</div>
                            <div class="line">{{ $assessment->health_service_representative_name ?: ' ' }}</div>
                            <div class="role">{{ $assessment->health_service_representative_designation }}</div>
                        </td>
                        <td style="border: none; width: 33%;" class="signature">
                            <div class="label" style="text-align:left; margin-bottom: 20px;">Encoder:</div>
                            <div class="line">{{ $assessment->encoder?->name ?: ' ' }}</div>
                            <div class="role">{{ $assessment->encoder?->designation }}</div>
                        </td>
                    </tr>
                </table>

                <div class="footer-note">
                    <span>Ref. No. BMI-{{ str_pad((string) $assessment->id, 6, '0', STR_PAD_LEFT) }} · {{ $personnel->employee_number }}</span>
                    <span>
                        @if ($assessment->status !== \App\Enums\AssessmentStatus::Certified)
                            <span class="status-flag">{{ strtoupper($assessment->status->label()) }}</span>
                        @else
                            Certified {{ $assessment->certified_at?->format('M j, Y') }}
                        @endif
                    </span>
                </div>
            </div>

            @if ($copy < $copies)
                <div class="cut-line"></div>
            @endif
        @endfor
    </div>
</x-layouts::print>
