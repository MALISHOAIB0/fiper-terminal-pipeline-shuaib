<?php

namespace App\Console\Commands;

use App\Contracts\NewsProvider;
use App\Models\Instrument;
use App\Models\NewsArticle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('news:ingest')]
#[Description('Fetch and de-duplicate news for all active instruments')]
class IngestNews extends Command
{
    public function handle(NewsProvider $provider): int
    {
        $instruments = Instrument::where('is_active', true)->get();

        if ($instruments->isEmpty()) {
            return self::SUCCESS;
        }

        $items = $provider->fetchNewsForSymbols($instruments->pluck('symbol')->all(), limit: 50);
        $created = 0;

        foreach ($items as $item) {
            $article = NewsArticle::updateOrCreate(
                ['marketaux_uuid' => $item['uuid']],
                [
                    'title' => $item['title'],
                    'summary' => $item['summary'],
                    'source' => $item['source'],
                    'url' => $item['url'],
                    'published_at' => $item['published_at'],
                ],
            );

            // NOTE: Eloquent\Collection::only()/except() match by primary key,
            // not array key — even after keyBy(). whereIn() on the attribute
            // is the safe way to filter by symbol here.
            $matchedIds = $instruments->whereIn('symbol', $item['related_symbols'])->pluck('id');
            $article->instruments()->syncWithoutDetaching($matchedIds);

            $created++;
        }

        $this->line("Ingested {$created} news item(s).");

        return self::SUCCESS;
    }
}
