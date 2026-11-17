@props(['personnel', 'url' => null])

{{-- Identification picture of a personnel, or their initials when no picture is on record. Size and shape come from the class attribute. --}}
@php($url ??= $personnel->photoUrl())

@if ($url)
    <img src="{{ $url }}" alt="{{ __('Photo of :name', ['name' => $personnel->full_name]) }}"
        {{ $attributes->class('shrink-0 bg-zinc-200 object-cover dark:bg-zinc-700') }}>
@else
    <div {{ $attributes->class('flex shrink-0 items-center justify-center bg-navy-800 font-semibold text-white') }} title="{{ __('No photo on record') }}">
        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($personnel->first_name, 0, 1).\Illuminate\Support\Str::substr($personnel->last_name, 0, 1)) }}
    </div>
@endif
