<?php

namespace Tests\Feature;

use App\Events\BriefGenerated;
use App\Jobs\GenerateInstrumentBrief;
use App\Models\Instrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BriefGeneratedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_instrument_brief_dispatches_event(): void
    {
        $instrument = Instrument::create([
            'symbol' => 'XAUUSD',
            'name' => 'Gold',
            'short_name' => 'Gold',
            'asset_class' => 'metals',
            'is_active' => true,
            'is_tier_one' => true,
        ]);

        Artisan::call('ohlc:backfill', ['--symbol' => 'XAUUSD', '--days' => 90]);
        Artisan::call('quotes:refresh');

        Event::fake([BriefGenerated::class]);

        GenerateInstrumentBrief::dispatchSync($instrument->id);

        Event::assertDispatched(BriefGenerated::class, function (BriefGenerated $event) {
            return $event->symbol === 'XAUUSD'
                && $event->tier === 'tier_one'
                && $event->briefEn !== [];
        });
    }
}
