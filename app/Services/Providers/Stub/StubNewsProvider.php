<?php

namespace App\Services\Providers\Stub;

use App\Contracts\NewsProvider;
use Carbon\Carbon;

class StubNewsProvider implements NewsProvider
{
    private const HEADLINES = [
        '2222.SR' => [
            ['title' => 'OPEC+ Reaffirms Gradual Output Increase Through Q3', 'hours_ago' => 2],
            ['title' => 'Saudi Aramco Signs New LNG Supply Agreement with Asian Buyers', 'hours_ago' => 5],
            ['title' => 'Brent Crude Holds Above $82 as Middle East Tensions Ease', 'hours_ago' => 8],
        ],
        'XAUUSD' => [
            ['title' => 'Gold Steadies as Traders Await Fed Rate Decision', 'hours_ago' => 3],
            ['title' => 'Central Bank Gold Buying Continues at Record Pace', 'hours_ago' => 9],
        ],
        'BTCUSDT' => [
            ['title' => 'Bitcoin ETF Inflows Resume After Two-Week Pause', 'hours_ago' => 1],
            ['title' => 'On-Chain Data Shows Long-Term Holders Accumulating', 'hours_ago' => 6],
        ],
    ];

    public function fetchNewsForSymbols(array $symbols, int $limit = 10): array
    {
        $items = [];

        foreach ($symbols as $symbol) {
            foreach (self::HEADLINES[$symbol] ?? [] as $headline) {
                $items[] = [
                    'uuid' => md5($symbol.'|'.$headline['title']),
                    'title' => $headline['title'],
                    'summary' => $headline['title'],
                    'source' => 'Marketaux (stub)',
                    'url' => 'https://example.com/news/'.md5($headline['title']),
                    'published_at' => Carbon::now()->subHours($headline['hours_ago'])->toIso8601String(),
                    'related_symbols' => [$symbol],
                ];
            }
        }

        return array_slice($items, 0, $limit);
    }
}
