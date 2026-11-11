<?php

namespace App\Http\Controllers;

use App\Models\BmiAssessment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves assessment photos from the private disk after an authorization check (never a public URL).
 */
class AssessmentPhotoController extends Controller
{
    public function __invoke(BmiAssessment $assessment, string $view): StreamedResponse
    {
        $this->authorize('view', $assessment);

        $path = $assessment->photoPaths()[$view] ?? null;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
