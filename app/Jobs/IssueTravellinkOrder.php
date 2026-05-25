<?php

namespace App\Jobs;

use App\Models\OrderEmission;
use App\Models\OrderEmissionLog;
use App\Services\TravellinkEmissionProcessor;
use App\Services\TravellinkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IssueTravellinkOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 120;

    public function __construct(
        public OrderEmission $emission
    ) {}

    public function handle(TravellinkService $travellink, TravellinkEmissionProcessor $processor): void
    {
        $this->emission->loadMissing(['order.flights', 'order.passengers']);

        if (! $travellink->canAutoIssueOrder($this->emission->order)) {
            return;
        }

        try {
            $processor->process($this->emission);
        } catch (\Throwable $e) {
            OrderEmissionLog::create([
                'order_emission_id' => $this->emission->id,
                'action' => 'travellink_failed',
                'notes' => $e->getMessage(),
            ]);

            Log::error('Travellink: falha na emissão automática', [
                'order_emission_id' => $this->emission->id,
                'order_id' => $this->emission->order_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
