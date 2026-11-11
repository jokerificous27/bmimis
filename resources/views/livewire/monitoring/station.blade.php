@php
    $person = $this->personnel;
    $existing = $this->thisMonthRecord;
    $previous = $person?->monthlyWeights->first(fn ($record) => ! ($record->year === (int) today()->format('Y') && $record->month === (int) today()->format('n')));
    $confidenceColor = ['high' => 'green', 'medium' => 'amber', 'low' => 'red', 'none' => 'zinc'];
@endphp

<div class="mx-auto max-w-5xl" x-data="weighInStation(@js(['readOnDevice' => $readerMode === 'device', 'minWeight' => $weightRange['min'], 'maxWeight' => $weightRange['max'], 'decimals' => $weightRange['decimals']]))" x-on:keydown.escape.window="stopScan()">
    <x-page-header :title="__('Weigh-in Station')" :subtitle="__(':month weigh-in · deadline :deadline. Scan the QR code, take one photo of the scale, confirm.', ['month' => today()->format('F Y'), 'deadline' => $deadline->format('M j')])">
        <x-slot:actions>
            @can('reports.generate')
                <flux:button size="sm" icon="qr-code" :href="route('print.qr-cards')" target="_blank">{{ __('Print QR cards') }}</flux:button>
            @endcan
            <flux:button size="sm" variant="ghost" :href="route('monitoring.index')" wire:navigate>{{ __('Encoding sheet') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($lastSaved)
        <div class="mb-6 flex flex-col gap-3 rounded-xl border-2 border-green-500 bg-green-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:bg-green-950/40" wire:key="last-saved-{{ $lastSaved['personnel_id'] }}-{{ $lastSaved['weight'] }}">
            <div class="flex items-center gap-3">
                <flux:icon.check-badge class="size-8 shrink-0 text-green-600" />
                <div>
                    <div class="text-lg font-semibold text-green-900 dark:text-green-200">{{ $lastSaved['name'] }} — {{ number_format($lastSaved['weight'], 2) }} kg</div>
                    <div class="text-sm text-green-800 dark:text-green-300">
                        {{ $lastSaved['automatic'] ? __('Saved automatically from the photo.') : __('Saved.') }}
                        @if ($lastSaved['measurements'] ?? false) {{ __('Waist, hip and wrist updated.') }} @endif
                        @if ($lastSaved['bmi']) BMI {{ number_format($lastSaved['bmi'], 1) }}. @endif
                        {{ __('Ready for the next personnel.') }}
                    </div>
                </div>
            </div>
            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('monitoring.personnel', ['personnel' => $lastSaved['personnel_id'], 'year' => $lastSaved['year']])" wire:navigate>{{ __('Wrong? Correct it') }}</flux:button>
        </div>
        @if ($lastSaved['review_record_id'] ?? null)
            <div class="mb-6">
                <livewire:monitoring.measurement-review :record-id="$lastSaved['review_record_id']" :key="'measurement-review-'.$lastSaved['review_record_id']" />
            </div>
        @endif
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            {{-- Step 1 + 2: capture --}}
            @include('livewire.monitoring.partials.photo-guide', ['collapsed' => (bool) $lastSaved])

            <x-panel :heading="__('1. Take the weigh-in photo')" :subheading="__('Whole-body photo with the scale display and the personnel QR (phone in the holder beside the scale). One photo identifies them and reads the weight.')">
                {{-- Smart camera: live viewfinder with QR / weight status; capture only when both are picked up. --}}
                <div x-show="scanning" x-cloak class="mb-4">
                    <div class="relative overflow-hidden rounded-xl bg-black" x-on:click="tapDisplay($event)" x-on:resize.window.debounce.100ms="updateOverlay()">
                        <video x-ref="video" playsinline muted class="max-h-[70vh] w-full object-contain"></video>

                        {{-- Spot staff tapped (where the display is read) and the display that was found. --}}
                        <div x-show="overlay.spot" class="pointer-events-none absolute rounded-md border-2 border-dashed border-white/70" x-bind:style="overlay.spot"></div>
                        <div x-show="overlay.display" class="pointer-events-none absolute rounded-md border-[3px] transition-colors"
                            x-bind:class="live.state === 'locked' ? 'border-green-400' : 'border-amber-400'" x-bind:style="overlay.display"></div>

                        <div class="pointer-events-none absolute inset-x-2 top-2 flex flex-col gap-1.5 text-sm font-semibold">
                            <div class="flex w-fit items-center gap-2 rounded-full px-3 py-1.5 shadow" x-bind:class="identified ? 'bg-green-600 text-white' : 'bg-black/70 text-white'">
                                <span x-text="identified ? '✓' : '…'"></span>
                                <span x-text="identified ? (personName || @js(__('Personnel identified'))) : @js(__('Looking for the QR code…'))"></span>
                            </div>
                            <template x-if="readOnDevice">
                                <div class="flex w-fit items-center gap-2 rounded-full px-3 py-1.5 shadow"
                                    x-bind:class="{ 'bg-green-600 text-white': live.state === 'locked', 'bg-amber-500 text-black': live.state === 'reading', 'bg-black/70 text-white': live.state === 'none' }">
                                    <span x-text="live.state === 'locked' ? '✓' : (live.state === 'reading' ? '…' : '×')"></span>
                                    <span x-show="live.state === 'locked'" x-text="@js(__('Weight picked up:')) + ' ' + live.text + ' kg'"></span>
                                    <span x-show="live.state === 'reading'" x-text="@js(__('Reading')) + ' ' + live.text + ' — ' + @js(__('hold steady'))"></span>
                                    <span x-show="live.state === 'none'">{{ __('Weight not picked up — tap the scale display') }}</span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                        <span x-show="! displaySpot">{{ __('Tip: tap the scale display on the screen once. It is then read there (and exposed for), and remembered on this phone.') }}</span>
                        <button type="button" x-show="displaySpot" x-on:click="clearDisplaySpot()" class="underline">{{ __('Clear display spot') }}</button>
                        <label class="flex items-center gap-1.5" x-show="readOnDevice">
                            <input type="checkbox" x-bind:checked="autoCapture" x-on:change="toggleAutoCapture()" class="rounded">
                            {{ __('Capture automatically when ready') }}
                        </label>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <template x-if="liveCameraSupported">
                        <div class="flex flex-1 gap-3">
                            <button type="button" x-show="! scanning" x-on:click="startScan()" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-navy-800 px-4 py-4 text-base font-semibold text-white hover:bg-navy-700">
                                <flux:icon.video-camera class="size-6" /> {{ __('Smart camera') }}
                            </button>
                            <button type="button" x-show="scanning" x-on:click="captureFrame()" x-bind:disabled="! ready || capturing"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-4 font-semibold transition disabled:cursor-not-allowed"
                                x-bind:class="ready ? 'bg-green-600 text-white' : 'bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400'">
                                <flux:icon.camera class="size-6" />
                                <span x-text="ready ? @js(__('Capture')) : (identified ? @js(__('Waiting for the weight…')) : @js(__('Waiting for the QR code…')))"></span>
                            </button>
                            <button type="button" x-show="scanning" x-on:click="stopScan()" class="rounded-xl border border-zinc-300 px-4 py-4 dark:border-zinc-600">{{ __('Stop') }}</button>
                        </div>
                    </template>
                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-zinc-300 px-4 py-4 font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800" x-show="! scanning"
                        x-bind:class="liveCameraSupported ? '' : '!border-transparent bg-navy-800 text-white hover:!bg-navy-700'">
                        <flux:icon.camera class="size-6" />
                        <span>{{ __('Take photo') }}</span>
                        <input type="file" accept="image/*" capture="environment" class="sr-only" x-on:change="handleFile($event)">
                    </label>
                </div>
                <template x-if="! secure">
                    <flux:text size="xs" class="mt-2">{{ __('The Smart camera (live weight check before capture) needs a secure connection (https). Open the system through its https address, or use "Take photo".') }}</flux:text>
                </template>

                <div class="mt-3 min-h-5 text-sm" aria-live="polite">
                    <span x-show="message" x-text="message" class="text-zinc-600 dark:text-zinc-300"></span>
                    <span wire:loading wire:target="photo" class="text-zinc-600 dark:text-zinc-300">{{ $readerMode === 'ai' ? __('Reading the scale…') : __('Saving photo…') }}</span>
                </div>
                @if ($scanMessage)
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mt-2" :heading="$scanMessage" />
                @endif
                <flux:error name="photo" />
                @if ($reviewReason && ! $person)
                    <flux:callout variant="warning" icon="exclamation-triangle" class="mt-2" :heading="$reviewReason" />
                @endif

                @if ($pendingPhotoPath)
                    <div class="mt-4 flex items-start gap-3 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                        <flux:icon.check-badge class="size-5 shrink-0 text-green-600" />
                        <div>
                            <div class="font-medium">{{ __('Photo captured') }}</div>
                            @if ($reading && $reading['weight_kg'] !== null)
                                <div>{{ __('Scale reads') }} <strong>{{ number_format($reading['weight_kg'], 2) }} kg</strong>
                                    <flux:badge size="sm" :color="$confidenceColor[$reading['confidence']] ?? 'zinc'">{{ __(':level confidence', ['level' => ucfirst($reading['confidence'])]) }}</flux:badge>
                                </div>
                            @endif
                            @if ($reading['note'] ?? null)
                                <div class="text-zinc-500">{{ $reading['note'] }}</div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($autoSave)
                    <flux:text size="xs" class="mt-3">{{ __('Automatic mode: when the QR code and the scale reading are clear, the weight is saved as soon as the photo is taken. Unclear readings or large changes from last month wait for your check.') }}</flux:text>
                @elseif ($readerEnabled)
                    <flux:text size="xs" class="mt-3">{{ __('The weight is read from the photo; confirm it with one tap.') }}</flux:text>
                @else
                    <flux:text size="xs" class="mt-3">{{ __('Automatic weight reading is off, so staff type the weight shown in the photo. An administrator can switch it on in System Settings.') }}</flux:text>
                @endif
                @if ($readerMode === 'device')
                    <flux:text size="xs" class="mt-1">{{ __('The QR code and the scale display are read on this phone; no photo is sent to any outside service.') }}</flux:text>
                @endif
            </x-panel>

            @unless ($person)
                <x-panel :heading="__('No QR code? Search instead')">
                    <div class="relative">
                        <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :placeholder="__('Name or employee number')" autocomplete="off" />
                        @if ($this->searchResults->isNotEmpty())
                            <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                @foreach ($this->searchResults as $result)
                                    <button type="button" wire:click="choosePersonnel({{ $result->id }})" class="block w-full px-3 py-2.5 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                        {{ $result->rank?->abbreviation }} {{ $result->formal_name }} <span class="text-zinc-500">· {{ $result->employee_number }} · {{ $result->unit?->code }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-panel>
            @endunless
        </div>

        {{-- Step 3: confirm --}}
        <div class="space-y-6 lg:col-span-2">
            <x-panel :heading="__('2. Confirm and save')">
                @if ($person)
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-lg font-semibold text-navy-900 dark:text-white">{{ $person->rank?->abbreviation }} {{ $person->formal_name }}</div>
                            <div class="text-sm text-zinc-500">{{ $person->employee_number }} · {{ $person->unit?->name }}</div>
                            <div class="mt-1 text-xs text-zinc-500">
                                {{ __('Height for BMI') }}: {{ $person->latestAssessment ? number_format($person->latestAssessment->height_cm, 1).' cm' : __('no assessment yet') }}
                                @if ($previous) · {{ __('Last weight') }}: {{ number_format($previous->weight_kg, 2) }} kg ({{ $previous->monthName() }}) @endif
                            </div>
                        </div>
                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="nextPersonnel" :tooltip="__('Clear')" />
                    </div>

                    @if ($existing)
                        <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4" :heading="__('Already weighed this month: :weight kg', ['weight' => number_format($existing->weight_kg, 2)])">
                            <flux:callout.text>{{ __('One weigh-in is kept per month. If the personnel was weighed again, check the new weight and replace it; the old value stays in the audit trail.') }}</flux:callout.text>
                        </flux:callout>
                        @can('update', $existing)
                            @php($replaceConfirm = __("Replace this month's weight of :weight kg?", ['weight' => number_format($existing->weight_kg, 2)]))
                            <form wire:submit="replaceThisMonth" class="mt-4 space-y-4">
                                <flux:input wire:model="weight" :label="__('New weight (kg)')" type="number" step="0.01" inputmode="decimal" class="text-2xl" required
                                    :description="$reading && $reading['weight_kg'] !== null ? __('Read from the photo — check it against the scale before replacing.') : __('Type the weight shown on the scale.')" />
                                <flux:error name="weight_kg" />
                                <flux:button type="submit" variant="filled" icon="arrow-path" class="w-full"
                                    :wire:confirm="$replaceConfirm">{{ __("Replace this month's weight") }}</flux:button>
                            </form>
                        @endcan
                    @else
                        @if ($reviewReason)
                            <flux:callout variant="warning" icon="eye" class="mt-4" :heading="__('Please check before saving')">
                                <flux:callout.text>{{ $reviewReason }}</flux:callout.text>
                            </flux:callout>
                        @endif
                        @if ($autoSaveHeld && ! $reviewReason)
                            <flux:callout variant="success" icon="check-circle" class="mt-4" :heading="__('Reading is clear')">
                                <flux:callout.text>{{ __('Answer the question below to save.') }}</flux:callout.text>
                            </flux:callout>
                        @endif
                        <form wire:submit="save" class="mt-4 space-y-4">
                            <flux:input wire:model="weight" :label="__('Weight (kg)')" type="number" step="0.01" inputmode="decimal" class="text-2xl" required
                                :description="$reading && $reading['weight_kg'] !== null ? __('Read from the photo — check it against the scale before saving.') : __('Type the weight shown on the scale.')" />
                            <flux:error name="weight_kg" />
                            <flux:error name="personnel" />
                            <flux:error name="assessed_on" />

                            @if ($this->canUpdateMeasurements)
                                @php($lastAssessment = $person->latestAssessment)
                                <div class="rounded-xl border border-navy-200 bg-navy-50/50 p-4 dark:border-navy-700 dark:bg-navy-900/40">
                                    @if ($updateMeasurements)
                                        <div class="font-semibold text-navy-900 dark:text-white">{{ __('Waist, hip and wrist') }}</div>
                                        <div class="mt-3 grid grid-cols-3 gap-3">
                                            <flux:input wire:model="waist_cm" :label="__('Waist (cm)')" type="number" step="0.1" inputmode="decimal" />
                                            <flux:input wire:model="hip_cm" :label="__('Hip (cm)')" type="number" step="0.1" inputmode="decimal" />
                                            <flux:input wire:model="wrist_cm" :label="__('Wrist (cm)')" type="number" step="0.1" inputmode="decimal" />
                                        </div>
                                        <flux:text size="xs" class="mt-2">{{ __('The last values are filled in; change the ones you measured. Saved with the weight as a new assessment dated today (height :height cm carried over), sent for certification.', ['height' => number_format($lastAssessment->height_cm, 1)]) }}</flux:text>
                                    @else
                                        <div class="font-semibold text-navy-900 dark:text-white">{{ __('Update the waist, hip and wrist too?') }}</div>
                                        <flux:text size="sm" class="mt-1">
                                            {{ __('Last measured :date: waist :waist · hip :hip · wrist :wrist', [
                                                'date' => $lastAssessment->assessed_on->format('M j, Y'),
                                                'waist' => $lastAssessment->waist_cm !== null ? number_format($lastAssessment->waist_cm, 1).' cm' : '—',
                                                'hip' => $lastAssessment->hip_cm !== null ? number_format($lastAssessment->hip_cm, 1).' cm' : '—',
                                                'wrist' => $lastAssessment->wrist_cm !== null ? number_format($lastAssessment->wrist_cm, 1).' cm' : '—',
                                            ]) }}
                                        </flux:text>
                                    @endif
                                </div>

                                @if ($updateMeasurements)
                                    <flux:button type="submit" variant="primary" icon="check" class="w-full !py-6 !text-base">{{ __('Save weight and measurements') }}</flux:button>
                                    <flux:button type="button" variant="ghost" size="sm" class="w-full" wire:click="cancelMeasurements">{{ __('Back') }}</flux:button>
                                @else
                                    <div class="grid gap-3">
                                        <flux:button type="button" variant="primary" icon="pencil-square" class="w-full !py-6 !text-base" wire:click="startMeasurements">{{ __('Yes, update measurements') }}</flux:button>
                                        <flux:button type="button" icon="check" class="w-full !py-6 !text-base" wire:click="saveWeightOnly">{{ __('No, save weight only') }}</flux:button>
                                    </div>
                                @endif
                            @else
                                <flux:button type="submit" variant="primary" icon="check" class="w-full !py-6 !text-base">{{ __('Confirm and save') }}</flux:button>
                            @endif
                            @unless ($pendingPhotoPath)
                                <flux:text size="xs">{{ __('Tip: take the photo first so it is kept as evidence of the reading.') }}</flux:text>
                            @endunless
                        </form>
                    @endif
                @else
                    <x-empty-state icon="qr-code" :heading="__('Waiting for a QR code')" :text="__('Take a photo with the personnel QR code visible, use the live camera, or search by name.')" class="py-6" />
                @endif
            </x-panel>

            <x-panel :heading="__('Recorded by you today')" :padding="false">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->recentCaptures as $record)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                            <span class="truncate">{{ $record->personnel->rank?->abbreviation }} {{ $record->personnel->formal_name }}</span>
                            <span class="shrink-0 font-semibold tabular-nums">{{ number_format($record->weight_kg, 2) }} kg</span>
                        </div>
                    @empty
                        <x-empty-state icon="scale" :heading="__('No weigh-ins yet today')" class="py-6" />
                    @endforelse
                </div>
            </x-panel>
        </div>
    </div>

    <canvas x-ref="canvas" class="hidden"></canvas>
</div>
