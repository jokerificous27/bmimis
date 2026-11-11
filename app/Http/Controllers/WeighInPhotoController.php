<?php

namespace App\Http\Controllers;

use App\Models\MonthlyWeightMonitoring;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the private Weigh-in Station photo of a monthly record after an authorization check.
 */
class WeighInPhotoController extends Controller
{
    public function __invoke(MonthlyWeightMonitoring $record): StreamedResponse
    {
        $this->authorize('view', $record);

        abort_unless($record->photo_path && Storage::disk('local')->exists($record->photo_path), 404);

        return Storage::disk('local')->response($record->photo_path, null, [
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
