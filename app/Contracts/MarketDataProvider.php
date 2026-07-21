<?php

namespace App\Contracts;

interface MarketDataProvider
{
    /**
     * @return array<int, array{date: string, open: float, high: float, low: float, close: float, volume: int|null}>
     */
    public function fetchDailyOhlc(string $symbol, int $days): array;

    /**
     * @return array{price: float, change: float, change_percent: float, volume: int|null, quoted_at: string}
     */
    public function fetchQuote(string $symbol): array;
}
