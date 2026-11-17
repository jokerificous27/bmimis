<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the personnel's identification picture from the private disk after an authorization check
 * (never a public URL).
 */
class PersonnelPhotoController extends Controller
{
    public function __invoke(Personnel $personnel, ?string $view = null): StreamedResponse
    {
        $this->authorize('view', $personnel);

        $path = $view === null
            ? $personnel->displayPhotoPath()
            : $personnel->getAttribute(Personnel::PHOTO_COLUMNS[$view]);

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
