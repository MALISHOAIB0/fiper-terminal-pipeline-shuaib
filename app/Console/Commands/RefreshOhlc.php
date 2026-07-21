<?php

namespace App\Console\Commands;

use App\Contracts\MarketDataProvider;
use App\Models\Instrument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ohlc:daily-refresh')]
#[Description('Fetch the latest daily bar for every active instrument')]
class RefreshOhlc extends Command
{
    public function handle(MarketDataProvider $provider): int
    {
        $instruments = Instrument::where('is_active', true)->get();

        foreach ($instruments as $instrument) {
            $rows = $provider->fetchDailyOhlc($instrument->symbol, 2);

            foreach ($rows as $row) {
                $instrument->ohlcDaily()->updateOrCreate(
                    ['date' => $row['date']],
                    ['open' => $row['open'], 'high' => $row['high'], 'low' => $row['low'], 'close' => $row['close'], 'volume' => $row['volume']],
                );
            }

            $this->line("Refreshed OHLC for {$instrument->symbol}");
        }

        return self::SUCCESS;
    }
}
