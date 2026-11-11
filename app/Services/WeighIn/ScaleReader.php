<?php

namespace App\Services\WeighIn;

/**
 * Reads the weight shown on a weighing scale from a JPEG photo.
 */
interface ScaleReader
{
    /**
     * Whether automatic reading is configured and switched on.
     */
    public function isEnabled(): bool;

    public function read(string $jpegBytes): ScaleReading;
}
