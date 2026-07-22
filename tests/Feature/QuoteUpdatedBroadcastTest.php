<?php

namespace Tests\Feature;

use App\Events\QuoteUpdated;
use App\Models\Instrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QuoteUpdatedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotes_refresh_dispatches_quote_updated_event(): void
    {
        Instrument::create([
            'symbol' => 'XAUUSD',
            'name' => 'Gold',
            'short_name' => 'Gold',
            'asset_class' => 'metals',
            'is_active' => true,
        ]);

        Event::fake([QuoteUpdated::class]);

        Artisan::call('quotes:refresh');

        Event::assertDispatched(QuoteUpdated::class, function (QuoteUpdated $event) {
            return $event->symbol === 'XAUUSD' && $event->price > 0;
        });
    }
}
