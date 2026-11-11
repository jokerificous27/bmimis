<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Personnel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bmi:compact-qr-codes {--force : Do not ask for confirmation}')]
#[Description('Re-issue weigh-in QR codes that use the old long format as compact codes (old cards stop working)')]
class CompactQrCodes extends Command
{
    public function handle(): int
    {
        $query = Personnel::query()->whereNotNull('qr_token')->whereRaw('LENGTH(qr_token) > 13');
        $count = $query->count();

        if ($count === 0) {
            $this->components->info('All QR codes already use the compact format.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Re-issue {$count} QR codes? Printed cards and saved screenshots of the old codes will stop working.")) {
            return self::FAILURE;
        }

        $query->each(fn (Personnel $personnel) => $personnel->regenerateQrToken());

        ActivityLog::record('personnel.qr_compacted', newValues: ['count' => $count], description: "Re-issued {$count} weigh-in QR codes in the compact format");
        $this->components->info("Re-issued {$count} QR codes. Reprint the cards; personnel see the new code on My Weigh-in QR.");

        return self::SUCCESS;
    }
}
