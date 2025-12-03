<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hold;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredHolds extends Command
{
    protected $signature = 'holds:release-expired';
    protected $description = 'Release expired holds and restore product availability';

    public function handle()
    {
        $now = now();

        // Use transaction to avoid race conditions
        DB::transaction(function () use ($now) {
            // Lock expired holds
            $expiredHolds = Hold::where('status', 'active')
                ->where('expires_at', '<=', $now)
                ->lockForUpdate()
                ->get();

            foreach ($expiredHolds as $hold) {
                // Restore product availability
                $hold->product->increment('available_stock', $hold->qty);

                // Mark hold as expired
                $hold->status = 'expired';
                $hold->save();
            }
        });

        $this->info('Expired holds released: ' . now());
    }
}
