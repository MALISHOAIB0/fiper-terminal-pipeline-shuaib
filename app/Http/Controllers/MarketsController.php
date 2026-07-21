<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\PageContent;
use Illuminate\View\View;

class MarketsController extends Controller
{
    public function index(): View
    {
        $instruments = Instrument::where('is_active', true)
            ->with('latestQuote')
            ->orderBy('asset_class')
            ->orderBy('symbol')
            ->get();

        return view('pages.markets', [
            'instruments' => $instruments,
            'content' => PageContent::for('markets'),
        ]);
    }
}
