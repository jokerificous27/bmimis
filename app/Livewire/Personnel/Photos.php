<?php

namespace App\Livewire\Personnel;

use App\Models\Personnel;
use App\Services\WeighIn\WeighInPhoto;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Pictures of a personnel: the profile picture (shown when their QR code is scanned) and the left,
 * center and right views. The personnel uploads their own; staff who manage the record may too.
 * A picture is saved as soon as it is chosen.
 */
class Photos extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $personnelId;

    /** @var TemporaryUploadedFile|null */
    public $profile = null;

    /** @var TemporaryUploadedFile|null */
    public $left = null;

    /** @var TemporaryUploadedFile|null */
    public $center = null;

    /** @var TemporaryUploadedFile|null */
    public $right = null;

    public function mount(Personnel $personnel): void
    {
        $this->authorize('view', $personnel);
        $this->personnelId = $personnel->id;
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    public static function views(): array
    {
        return [
            'profile' => ['label' => __('Profile picture'), 'hint' => __('Face clearly visible, like an ID picture. Shown when the QR code is scanned.')],
            'left' => ['label' => __('Left view'), 'hint' => __('Whole body, left side facing the camera.')],
            'center' => ['label' => __('Center view'), 'hint' => __('Whole body, facing the camera.')],
            'right' => ['label' => __('Right view'), 'hint' => __('Whole body, right side facing the camera.')],
        ];
    }

    public function updated(string $property): void
    {
        if (array_key_exists($property, Personnel::PHOTO_COLUMNS)) {
            $this->store($property);
        }
    }

    /**
     * Store the chosen picture privately (rotation fixed, resized, metadata stripped) and delete the
     * one it replaces, so only one picture is kept per view.
     */
    private function store(string $view): void
    {
        $personnel = $this->personnel();
        $this->authorize('managePhotos', $personnel);

        $this->validate([$view => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']], attributes: [$view => __('picture')]);

        try {
            $jpeg = WeighInPhoto::normalize($this->{$view}->getRealPath());
        } catch (RuntimeException $exception) {
            $this->addError($view, $exception->getMessage());

            return;
        }

        $column = Personnel::PHOTO_COLUMNS[$view];
        $previous = $personnel->getAttribute($column);
        $path = 'personnel-photos/'.$personnel->id.'/'.$view.'-'.Str::random(20).'.jpg';

        Storage::disk('local')->put($path, $jpeg);

        $personnel->forceFill([$column => $path]);
        $personnel->auditRemarks = $this->auditRemark($view, 'updated', $personnel);
        $personnel->save();

        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        $this->reset($view);
        $this->dispatch('personnel-photos-updated');
        Flux::toast(variant: 'success', text: __(':view saved.', ['view' => self::views()[$view]['label']]));
    }

    public function remove(string $view): void
    {
        abort_unless(array_key_exists($view, Personnel::PHOTO_COLUMNS), 404);

        $personnel = $this->personnel();
        $this->authorize('managePhotos', $personnel);

        $column = Personnel::PHOTO_COLUMNS[$view];
        $previous = $personnel->getAttribute($column);

        if (! $previous) {
            return;
        }

        $personnel->forceFill([$column => null]);
        $personnel->auditRemarks = $this->auditRemark($view, 'removed', $personnel);
        $personnel->save();

        Storage::disk('local')->delete($previous);

        $this->dispatch('personnel-photos-updated');
        Flux::toast(text: __(':view removed.', ['view' => self::views()[$view]['label']]));
    }

    private function auditRemark(string $view, string $action, Personnel $personnel): string
    {
        $by = $personnel->user_id === Auth::id() ? ' by the personnel' : '';

        return ucfirst($view).' picture '.$action.$by;
    }

    private function personnel(): Personnel
    {
        return Personnel::findOrFail($this->personnelId);
    }

    public function render(): View
    {
        $personnel = $this->personnel();

        return view('livewire.personnel.photos', [
            'personnel' => $personnel,
            'views' => self::views(),
            'canManage' => Auth::user()->can('managePhotos', $personnel),
            'isOwn' => $personnel->user_id === Auth::id(),
        ]);
    }
}
