<?php

namespace App\Services\Providers\Stub;

use App\Contracts\MarketDataProvider;
use Carbon\Carbon;

/**
 * Deterministic fake OHLC/quote generator. No network calls. Lets the pipeline
 * run end-to-end (scheduler, queue, brief generation) before real provider
 * keys (TwelveData) are available — swap the binding in PipelineServiceProvider
 * to Live\TwelveDataProvider once a key exists.
 *
 * Always generates from a fixed anchor date, then slices the requested window,
 * so fetchDailyOhlc($symbol, 2) and fetchDailyOhlc($symbol, 90) agree on the
 * shared tail — quote and OHLC history stay consistent regardless of which
 * call site asked for how many days.
 */
class StubMarketDataProvider implements MarketDataProvider
{
    private const ANCHOR_DAYS = 365;

    private const BASE_PRICES = [
        '2222.SR' => 26.10,
        'XAUUSD' => 2380.00,
        'BTCUSDT' => 61000.00,
    ];

    public function fetchDailyOhlc(string $symbol, int $days): array
    {
        $full = $this->generateSeries($symbol);

        return array_slice($full, -$days);
    }

    public function fetchQuote(string $symbol): array
    {
        $recent = $this->fetchDailyOhlc($symbol, 2);
        [$yesterday, $today] = $recent;

        $change = $today['close'] - $yesterday['close'];
        $changePercent = $yesterday['close'] != 0 ? ($change / $yesterday['close']) * 100 : 0;

        return [
            'price' => $today['close'],
            'change' => round($change, 6),
            'change_percent' => round($changePercent, 4),
            'volume' => $today['volume'],
            'quoted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{date: string, open: float, high: float, low: float, close: float, volume: int}>
     */
    private function generateSeries(string $symbol): array
    {
        $seed = ['state' => crc32($symbol) ?: 42];
        $price = self::BASE_PRICES[$symbol] ?? 100.0;
        $rows = [];

        $date = Carbon::today()->subDays(self::ANCHOR_DAYS - 1);

        for ($i = 0; $i < self::ANCHOR_DAYS; $i++) {
            $rand = $this->nextRandom($seed);
            $drift = ($rand - 0.47) * ($price * 0.012);

            $open = $price;
            $close = max(0.01, $open + $drift);
            $high = max($open, $close) + abs($this->nextRandom($seed)) * ($price * 0.006);
            $low = min($open, $close) - abs($this->nextRandom($seed)) * ($price * 0.006);

            $rows[] = [
                'date' => $date->copy()->addDays($i)->toDateString(),
                'open' => round($open, 6),
                'high' => round($high, 6),
                'low' => round($low, 6),
                'close' => round($close, 6),
                'volume' => $this->deterministicVolume($seed),
            ];

            $price = $close;
        }

        return $rows;
    }

    private function deterministicVolume(array &$seed): int
    {
        return 10_000 + (int) ($this->nextRandom($seed) * 490_000);
    }

    private function nextRandom(array &$seed): float
    {
        $seed['state'] = ($seed['state'] * 1103515245 + 12345) % 2147483648;

        return $seed['state'] / 2147483648;
    }
}
