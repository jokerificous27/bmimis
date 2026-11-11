<div class="letterhead">
    <div class="small">Republic of the Philippines</div>
    <div class="org">NATIONAL POLICE COMMISSION</div>
    <div class="org">{{ $settings->get('organization_name') }}</div>
    @if (filled($settings->get('facility_name')))
        <div class="facility">{{ $settings->get('facility_name') }}</div>
    @endif
    @if (filled($settings->get('facility_address')))
        <div class="small">{{ $settings->get('facility_address') }}</div>
    @endif
</div>
