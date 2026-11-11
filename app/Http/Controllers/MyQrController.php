<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Full-screen weigh-in QR for the personnel's own phone, placed in the holder beside the scale.
 */
class MyQrController extends Controller
{
    public function __invoke(Request $request): View
    {
        $personnel = $request->user()->personnel?->load(['rank:id,abbreviation', 'unit:id,code']);

        abort_unless($personnel, 404);

        return view('my-qr', [
            'personnel' => $personnel,
            'payload' => $personnel->qrPayload(),
        ]);
    }
}
