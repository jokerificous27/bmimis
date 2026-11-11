@php
    $keys = collect(array_keys($log->old_values ?? []))->merge(array_keys($log->new_values ?? []))->unique()->values();
@endphp

@if ($keys->isNotEmpty())
    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-xs">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-3 py-1.5 text-start font-medium">{{ __('Field') }}</th>
                    <th class="px-3 py-1.5 text-start font-medium">{{ __('Previous value') }}</th>
                    <th class="px-3 py-1.5 text-start font-medium">{{ __('New value') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($keys as $key)
                    @php
                        $old = data_get($log->old_values, $key);
                        $new = data_get($log->new_values, $key);
                    @endphp
                    <tr>
                        <td class="px-3 py-1.5 font-mono text-zinc-500">{{ $key }}</td>
                        <td class="px-3 py-1.5 text-red-700 dark:text-red-400">{{ is_array($old) ? json_encode($old) : ($old ?? '—') }}</td>
                        <td class="px-3 py-1.5 text-green-700 dark:text-green-400">{{ is_array($new) ? json_encode($new) : ($new ?? '—') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
