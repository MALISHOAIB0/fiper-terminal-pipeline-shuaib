<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\PageContent;
use Illuminate\View\View;

class HeatmapController extends Controller
{
    public function index(): View
    {
        $instruments = Instrument::where('is_active', true)
            ->with('latestQuote')
            ->orderBy('symbol')
            ->get();

        return view('pages.heatmap', [
            'grouped' => $instruments->groupBy('asset_class'),
            'content' => PageContent::for('heatmap'),
        ]);
    }
}
