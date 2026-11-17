<x-panel :heading="__('Pictures')"
    :subheading="$canManage
        ? ($isOwn ? __('Upload your profile picture and your left, center and right view pictures. A picture is saved as soon as you choose it.') : __('Profile picture and left, center and right view pictures. A picture is saved as soon as it is chosen.'))
        : __('Profile picture and left, center and right view pictures.')">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($views as $view => $info)
            @php($url = $personnel->viewPhotoUrl($view))
            <div class="flex flex-col rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" wire:key="photo-{{ $view }}">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div class="font-medium">{{ $info['label'] }}</div>
                    @if ($view === 'profile')
                        <flux:badge size="sm" color="blue" icon="qr-code">{{ __('Shown on QR scan') }}</flux:badge>
                    @endif
                </div>

                <div class="relative flex aspect-[3/4] items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    @if ($url)
                        <a href="{{ $url }}" target="_blank" class="block size-full">
                            <img src="{{ $url }}" alt="{{ $info['label'] }} — {{ $personnel->full_name }}" class="size-full object-cover">
                        </a>
                    @else
                        <div class="p-4 text-center text-sm text-zinc-400">
                            <flux:icon.photo class="mx-auto mb-1 size-10" />
                            {{ __('No picture yet') }}
                        </div>
                    @endif
                    <div wire:loading.flex wire:target="{{ $view }}" class="absolute inset-0 items-center justify-center bg-white/80 text-sm font-medium text-zinc-700 dark:bg-zinc-900/80 dark:text-zinc-200">
                        {{ __('Uploading…') }}
                    </div>
                </div>

                <flux:text size="xs" class="mt-2">{{ $info['hint'] }}</flux:text>

                @if ($canManage)
                    <div class="mt-3 flex items-center gap-2">
                        <label class="inline-flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-800 shadow-xs hover:bg-zinc-50 focus-within:ring-2 focus-within:ring-navy-800 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white dark:hover:bg-zinc-600">
                            <flux:icon.arrow-up-tray variant="micro" />
                            <span>{{ $url ? __('Replace') : __('Upload') }}</span>
                            <input type="file" wire:model="{{ $view }}" accept="image/jpeg,image/png,image/webp" class="sr-only" aria-label="{{ __('Upload :view', ['view' => $info['label']]) }}">
                        </label>
                        @if ($url)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="remove('{{ $view }}')" wire:confirm="{{ __('Remove this picture?') }}" :aria-label="__('Remove :view', ['view' => $info['label']])" />
                        @endif
                    </div>
                    <flux:error :name="$view" />
                @endif
            </div>
        @endforeach
    </div>

    @if ($canManage)
        <flux:text size="xs" class="mt-3">{{ __('JPG, PNG or WebP, up to 10 MB each. Pictures are kept private and can only be seen by you and authorized Health Service and unit staff.') }}</flux:text>
    @endif
</x-panel>
