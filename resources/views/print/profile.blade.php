@php
    /** @var \App\Models\Personnel $personnel */
    $latest = $personnel->latestAssessment;
@endphp

<x-layouts::print :title="__('Consolidated BMI Profile')" orientation="portrait">
    <x-slot:head>
        <style>
            h3 { font-size: 12px; margin: 14px 0 5px; text-transform: uppercase; color: #1d3461; }
            .kv td { width: 25%; }
            .kv .k { font-size: 9px; color: #444; text-transform: uppercase; display: block; }
            .kv .v { font-weight: 600; }
        </style>
    </x-slot:head>

    <div class="sheet portrait">
        @include('print.partials.letterhead')
        <div class="doc-title">CONSOLIDATED PERSONNEL BMI PROFILE</div>

        <h3>Personal Information</h3>
        <table class="kv">
            <tr>
                <td><span class="k">Employee No.</span><span class="v">{{ $personnel->employee_number }}</span></td>
                <td><span class="k">Rank</span><span class="v">{{ $personnel->rank?->name }}</span></td>
                <td colspan="2"><span class="k">Name</span><span class="v">{{ $personnel->formal_name }}</span></td>
            </tr>
            <tr>
                <td><span class="k">Sex</span><span class="v">{{ $personnel->sex->label() }}</span></td>
                <td><span class="k">Date of birth / Age</span><span class="v">{{ $personnel->date_of_birth?->format('M j, Y') ?? '—' }} {{ $personnel->age !== null ? '('.$personnel->age.')' : '' }}</span></td>
                <td><span class="k">Unit / Office</span><span class="v">{{ $personnel->unit?->name ?? '—' }}</span></td>
                <td><span class="k">Position</span><span class="v">{{ $personnel->position ?? '—' }}</span></td>
            </tr>
        </table>

        <h3>Current BMI Information</h3>
        @if ($latest)
            <table class="kv">
                <tr>
                    <td><span class="k">Date of latest assessment</span><span class="v">{{ $latest->assessed_on->format('F j, Y') }}</span></td>
                    <td><span class="k">Height / Weight</span><span class="v">{{ number_format($latest->height_cm, 1) }} cm / {{ number_format($latest->weight_kg, 2) }} kg</span></td>
                    <td><span class="k">BMI</span><span class="v">{{ number_format($latest->bmi, 1) }}</span></td>
                    <td><span class="k">Waist / Hip / Wrist</span><span class="v">{{ $latest->waist_cm ?? '—' }} / {{ $latest->hip_cm ?? '—' }} / {{ $latest->wrist_cm ?? '—' }} cm</span></td>
                </tr>
                <tr>
                    <td><span class="k">PNP classification</span><span class="v">{{ $latest->pnp_classification ?? '—' }}</span></td>
                    <td><span class="k">WHO classification</span><span class="v">{{ $latest->who_classification ?? '—' }}</span></td>
                    <td><span class="k">Ideal body weight</span><span class="v">{{ $latest->ibwRangeText() }}</span></td>
                    <td><span class="k">Weight to lose / maintain</span><span class="v">{{ $latest->weightActionText() }}</span></td>
                </tr>
            </table>
        @else
            <p class="muted">{{ __('No submitted or certified assessment on record.') }}</p>
        @endif

        <h3>Monthly Weight Monitoring — {{ $year }}</h3>
        <table>
            <tr>@foreach ($sheet as $row)<th class="center">{{ substr($row['name'], 0, 3) }}</th>@endforeach</tr>
            <tr>@foreach ($sheet as $row)<td class="center">{{ $row['record'] ? number_format($row['record']->weight_kg, 2) : '—' }}</td>@endforeach</tr>
            <tr>@foreach ($sheet as $row)<td class="center muted">{{ $row['difference'] !== null ? ($row['difference'] > 0 ? '+' : '').number_format($row['difference'], 2) : '' }}</td>@endforeach</tr>
        </table>

        <h3>Assessment History</h3>
        <table>
            <tr><th>Date</th><th>Height</th><th>Weight</th><th>BMI</th><th>PNP</th><th>WHO</th><th>Waist</th><th>Hip</th><th>Status</th></tr>
            @forelse ($assessments as $assessment)
                <tr>
                    <td>{{ $assessment->assessed_on->format('Y-m-d') }}</td>
                    <td class="num">{{ number_format($assessment->height_cm, 1) }}</td>
                    <td class="num">{{ number_format($assessment->weight_kg, 2) }}</td>
                    <td class="num">{{ number_format($assessment->bmi, 1) }}</td>
                    <td>{{ $assessment->pnp_classification }}</td>
                    <td>{{ $assessment->who_classification }}</td>
                    <td class="num">{{ $assessment->waist_cm }}</td>
                    <td class="num">{{ $assessment->hip_cm }}</td>
                    <td>{{ $assessment->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="center muted">{{ __('No assessments.') }}</td></tr>
            @endforelse
        </table>

        <h3>Interventions</h3>
        <table>
            <tr><th>Package</th><th>Assigned</th><th>Status</th><th>Next follow-up</th><th>Outcome</th></tr>
            @forelse ($personnel->interventions as $intervention)
                <tr>
                    <td>{{ $intervention->package->displayName() }} — {{ $intervention->package->name }}</td>
                    <td>{{ $intervention->assigned_on->format('Y-m-d') }}</td>
                    <td>{{ $intervention->status->label() }}</td>
                    <td>{{ $intervention->next_follow_up_on?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $intervention->outcome }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center muted">{{ __('No interventions.') }}</td></tr>
            @endforelse
        </table>

        <h3>Assignment History</h3>
        <table>
            <tr><th>Effective</th><th>Until</th><th>Unit / Office</th><th>Rank</th><th>Position</th></tr>
            @foreach ($personnel->assignments as $assignment)
                <tr>
                    <td>{{ $assignment->effective_from->format('Y-m-d') }}</td>
                    <td>{{ $assignment->effective_to?->format('Y-m-d') ?? __('Present') }}</td>
                    <td>{{ $assignment->unit?->name }}</td>
                    <td>{{ $assignment->rank?->abbreviation }}</td>
                    <td>{{ $assignment->position }}</td>
                </tr>
            @endforeach
        </table>

        <div class="footer-note">
            <span>{{ __('Generated') }} {{ now()->format('M j, Y g:i A') }} {{ __('by') }} {{ auth()->user()->name }}</span>
            <span>{{ __('This report is informational and subject to Health Service evaluation; it is not a medical diagnosis.') }}</span>
        </div>
    </div>
</x-layouts::print>
