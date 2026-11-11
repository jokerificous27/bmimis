@php
    /** @var \App\Models\Personnel $personnel */
    /** @var \App\Models\BmiAssessment|null $assessment */
    $format = fn (?float $value, int $decimals = 1) => $value === null ? '' : rtrim(rtrim(number_format($value, $decimals), '0'), '.');
@endphp

<x-layouts::print :title="__('Individual BMI Monitoring Form')" orientation="landscape">
    <x-slot:head>
        <style>
            .mf-top { display: grid; grid-template-columns: 46% 54%; gap: 12px; }
            .photos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
            .photo { border: 1px solid #333; height: 62mm; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fafafa; }
            .photo img { width: 100%; height: 100%; object-fit: cover; }
            .photo-label { text-align: center; font-weight: 700; font-size: 10px; margin-top: 3px; }
            .info td { border: none; border-bottom: 1px solid #bbb; padding: 3px 4px; }
            .info td.k { width: 46%; font-weight: 700; }
            .info td.u { width: 10%; color: #444; }
            .class-box { margin-top: 8px; }
            .class-box td { text-align: center; vertical-align: middle; height: 18mm; font-size: 13px; font-weight: 700; }
            .class-box th { text-align: center; font-size: 10px; }
            .monthly th, .monthly td { text-align: center; }
            .monthly td.w { font-size: 12px; font-weight: 600; height: 9mm; vertical-align: middle; }
        </style>
    </x-slot:head>

    <div class="sheet landscape">
        <div class="doc-title" style="margin-top: 0;">INDIVIDUAL BMI MONITORING FORM</div>

        <div class="mf-top">
            <div>
                <div class="photos">
                    @foreach (['right' => 'Right View', 'front' => 'Front View', 'left' => 'Left View'] as $view => $label)
                        <div>
                            <div class="photo">
                                @if ($assessment && $assessment->photoPaths()[$view])
                                    <img src="{{ route('assessments.photo', [$assessment, $view]) }}" alt="{{ $label }}">
                                @else
                                    <span class="muted">{{ __('No photo') }}</span>
                                @endif
                            </div>
                            <div class="photo-label">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>

                <table class="class-box">
                    <tr><th colspan="2">BMI CLASSIFICATION</th></tr>
                    <tr>
                        <th style="width: 50%">PNP Acceptable BMI Classification:</th>
                        <th>WHO Standard:</th>
                    </tr>
                    <tr>
                        <td>{{ $assessment?->pnp_classification ?? '—' }}</td>
                        <td>{{ $assessment?->who_classification ?? '—' }}</td>
                    </tr>
                </table>
            </div>

            <div>
                <table class="info">
                    <tr><td class="k">Rank/Name:</td><td colspan="2">{{ $personnel->rank_name }}</td></tr>
                    <tr><td class="k">Unit/Office:</td><td colspan="2">{{ ($assessment?->unit ?? $personnel->unit)?->code ?? '' }} {{ ($assessment?->unit ?? $personnel->unit) ? '— '.($assessment?->unit ?? $personnel->unit)->name : '' }}</td></tr>
                    <tr><td class="k">Age:</td><td colspan="2">{{ $assessment?->age_at_assessment ?? $personnel->age }}</td></tr>
                    <tr><td class="k">Height:</td><td>{{ $format($assessment?->height_cm) }}</td><td class="u">cm</td></tr>
                    <tr><td class="k">Weight:</td><td>{{ $assessment ? number_format($assessment->weight_kg, 2) : '' }}</td><td class="u">kgs</td></tr>
                    <tr><td class="k">Waist:</td><td>{{ $format($assessment?->waist_cm) }}</td><td class="u">cm</td></tr>
                    <tr><td class="k">Hip:</td><td>{{ $format($assessment?->hip_cm) }}</td><td class="u">cm</td></tr>
                    <tr><td class="k">Wrist:</td><td>{{ $format($assessment?->wrist_cm) }}</td><td class="u">cm</td></tr>
                    <tr><td class="k">Gender:</td><td colspan="2">{{ ($assessment?->sex ?? $personnel->sex)->label() }}</td></tr>
                    <tr><td class="k">Date Taken:</td><td colspan="2">{{ $assessment?->assessed_on->format('F j, Y') ?? '—' }}</td></tr>
                    <tr><td class="k">BMI Result:</td><td colspan="2"><strong>{{ $assessment ? number_format($assessment->bmi, 1) : '—' }}</strong></td></tr>
                    <tr><td class="k">Normal Weight Range: (PNP Acceptable)</td><td colspan="2">{{ $assessment?->ibw_min_kg !== null ? number_format($assessment->ibw_min_kg, 1).' kg - '.number_format($assessment->ibw_max_kg, 1).' kg' : '—' }}</td></tr>
                    <tr><td class="k">Weight to Lose</td><td colspan="2">{{ $assessment?->weightActionText() ?? '—' }}</td></tr>
                </table>

                <table class="class-box">
                    <tr>
                        <th style="width: 40%">Intervention Package:</th>
                        <th>Certified Correct Signature:</th>
                    </tr>
                    <tr>
                        <td>{{ $assessment?->interventionPackage?->displayName() ?? '—' }}</td>
                        <td style="font-size: 11px;">
                            <div style="height: 8mm;"></div>
                            <div style="text-transform: uppercase;">{{ $assessment?->certifier?->name ?? '' }}</div>
                            <div style="font-weight: 400;">{{ $assessment?->certifier?->designation }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="doc-title" style="margin: 12px 0 6px; font-size: 13px;">MONTHLY WEIGHT MONITORING</div>
        <table class="monthly">
            <tr>
                <th style="width: 10%">YEAR</th>
                <th colspan="12">{{ $year }}</th>
            </tr>
            <tr>
                <th>MONTH</th>
                @foreach ($sheet as $row)
                    <th>{{ substr($row['name'], 0, 3) }}</th>
                @endforeach
            </tr>
            <tr>
                <th>WEIGHT (kg)</th>
                @foreach ($sheet as $row)
                    <td class="w">{{ $row['record'] ? number_format($row['record']->weight_kg, 2) : '' }}</td>
                @endforeach
            </tr>
        </table>

        <div class="footer-note">
            <span>{{ $personnel->employee_number }} · {{ __('Generated') }} {{ now()->format('M j, Y g:i A') }}</span>
            <span>{{ __('Confidential health record — Data Privacy Act of 2012') }}</span>
        </div>
    </div>
</x-layouts::print>
