<?php

namespace App\Console\Commands;

use App\Contracts\MarketDataProvider;
use App\Events\QuoteUpdated;
use App\Models\Instrument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quotes:refresh')]
#[Description('Fetch a live quote snapshot for every active instrument')]
class RefreshQuotes extends Command
{
    public function handle(MarketDataProvider $provider): int
    {
        $instruments = Instrument::where('is_active', true)->get();

        foreach ($instruments as $instrument) {
            $quote = $provider->fetchQuote($instrument->symbol);

            $snapshot = $instrument->quoteSnapshots()->create([
                'quoted_at' => $quote['quoted_at'],
                'price' => $quote['price'],
                'change' => $quote['change'],
                'change_percent' => $quote['change_percent'],
                'volume' => $quote['volume'],
            ]);

            QuoteUpdated::dispatch(
                $instrument->symbol,
                (float) $snapshot->price,
                (float) $snapshot->change,
                (float) $snapshot->change_percent,
                $snapshot->quoted_at->toIso8601String(),
            );
        }

        $this->line("Refreshed quotes for {$instruments->count()} instrument(s).");

        return self::SUCCESS;
    }
}
