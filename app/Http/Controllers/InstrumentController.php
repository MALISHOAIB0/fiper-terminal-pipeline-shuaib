<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Services\CorrelationService;
use Illuminate\View\View;

class InstrumentController extends Controller
{
    public function show(string $symbol, CorrelationService $correlationService): View
    {
        $instrument = Instrument::where('symbol', $symbol)->where('is_active', true)->firstOrFail();

        $ohlc = $instrument->ohlcDaily()
            ->orderBy('date')
            ->get(['date', 'open', 'high', 'low', 'close'])
            ->map(fn ($row) => [
                'date' => $row->date->toDateString(),
                'open' => (float) $row->open,
                'high' => (float) $row->high,
                'low' => (float) $row->low,
                'close' => (float) $row->close,
            ]);

        $quote = $instrument->quoteSnapshots()->latest('quoted_at')->first();

        $news = $instrument->newsArticles()
            ->orderByDesc('published_at')
            ->limit(4)
            ->get(['title', 'source', 'published_at']);

        $correlations = $correlationService->correlationsFor($instrument, 90)->take(6);

        return view('pages.instrument', [
            'instrument' => $instrument,
            'ohlc' => $ohlc,
            'quote' => $quote,
            'news' => $news,
            'correlations' => $correlations,
        ]);
    }
}
