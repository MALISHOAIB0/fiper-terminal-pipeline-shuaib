<?php

namespace App\Contracts;

use App\Models\Instrument;

interface AiBriefProvider
{
    /**
     * @param  array{ohlc: array<int, array<string, mixed>>, quote: array<string, mixed>|null, news: array<int, array<string, mixed>>, forecast: array<string, mixed>, indicators: array{rsi_14: float|null, macd: float|null, macd_signal: float|null, macd_histogram: float|null}}  $context
     * @param  'tier_one'|'standard'  $modelTier
     * @return array{en: array<string, mixed>, ar: array<string, mixed>, bias: string}
     */
    public function generateBrief(Instrument $instrument, array $context, string $modelTier): array;
}
