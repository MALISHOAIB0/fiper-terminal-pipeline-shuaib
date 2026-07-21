<?php

namespace App\Console\Commands;

use App\Contracts\MarketDataProvider;
use App\Models\Instrument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ohlc:backfill {--symbol=} {--days=90}')]
#[Description('Backfill daily OHLC history for one instrument (or all active instruments)')]
class OhlcBackfill extends Command
{
    public function handle(MarketDataProvider $provider): int
    {
        $days = (int) $this->option('days');
        $symbol = $this->option('symbol');

        $instruments = $symbol
            ? Instrument::where('symbol', $symbol)->get()
            : Instrument::where('is_active', true)->get();

        if ($instruments->isEmpty()) {
            $this->error('No matching instrument(s) found.');

            return self::FAILURE;
        }

        foreach ($instruments as $instrument) {
            $this->line("Backfilling {$instrument->symbol} ({$days} days)...");

            $rows = $provider->fetchDailyOhlc($instrument->symbol, $days);

            foreach ($rows as $row) {
                $instrument->ohlcDaily()->updateOrCreate(
                    ['date' => $row['date']],
                    ['open' => $row['open'], 'high' => $row['high'], 'low' => $row['low'], 'close' => $row['close'], 'volume' => $row['volume']],
                );
            }

            $this->info("  {$instrument->symbol}: ".count($rows).' rows upserted.');
        }

        return self::SUCCESS;
    }
}
