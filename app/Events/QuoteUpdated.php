<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class QuoteUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $symbol,
        public readonly float $price,
        public readonly float $change,
        public readonly float $changePercent,
        public readonly string $quotedAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('quotes');
    }

    public function broadcastAs(): string
    {
        return 'quote.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'symbol' => $this->symbol,
            'price' => $this->price,
            'change' => $this->change,
            'change_percent' => $this->changePercent,
            'quoted_at' => $this->quotedAt,
        ];
    }
}
