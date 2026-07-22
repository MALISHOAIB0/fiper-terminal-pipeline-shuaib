<?php

namespace App\Events;

use App\Models\Instrument;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class BriefGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param array<string, mixed> $briefEn
     * @param array<string, mixed> $briefAr
     */
    public function __construct(
        public readonly string $symbol,
        public readonly string $tier,
        public readonly array $briefEn,
        public readonly array $briefAr,
        public readonly ?string $bias,
        public readonly string $generatedAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('briefs');
    }

    public function broadcastAs(): string
    {
        return 'brief.generated';
    }

    public function broadcastWith(): array
    {
        $biasMeta = Instrument::biasMeta($this->bias);

        return [
            'symbol' => $this->symbol,
            'tier' => $this->tier,
            'brief_en' => $this->briefEn,
            'brief_ar' => $this->briefAr,
            'bias' => $this->bias,
            'bias_label_en' => $biasMeta['en'],
            'bias_label_ar' => $biasMeta['ar'],
            'bias_class' => $biasMeta['class'],
            'generated_at' => $this->generatedAt,
        ];
    }
}
