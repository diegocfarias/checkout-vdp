<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PushoverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyIssuersNewEmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Order $order
    ) {}

    public function handle(PushoverService $pushover): void
    {
        $pushover->notifyIssuers($this->order);
    }
}
