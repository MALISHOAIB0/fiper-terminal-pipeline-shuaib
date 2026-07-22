<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class NewsArticleIngested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** @param array<int, string> $relatedSymbols */
    public function __construct(
        public readonly int $articleId,
        public readonly string $headline,
        public readonly string $source,
        public readonly string $publishedAt,
        public readonly array $relatedSymbols,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('news');
    }

    public function broadcastAs(): string
    {
        return 'news.article-ingested';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->articleId,
            'headline' => $this->headline,
            'source' => $this->source,
            'published_at' => $this->publishedAt,
            'instrument_symbols' => $this->relatedSymbols,
        ];
    }
}
