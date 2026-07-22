<?php

namespace Tests\Feature;

use App\Events\QuoteUpdated;
use App\Models\Instrument;
use Illuminate\Broadcasting\Channel;
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

    public function test_quote_updated_broadcast_payload_contract(): void
    {
        $event = new QuoteUpdated('XAUUSD', 2400.5, 12.3, 0.51, '2026-07-22T12:00:00Z');

        $this->assertSame('quote.updated', $event->broadcastAs());

        $channel = $event->broadcastOn();
        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame('quotes', $channel->name);

        $this->assertSame([
            'symbol' => 'XAUUSD',
            'price' => 2400.5,
            'change' => 12.3,
            'change_percent' => 0.51,
            'quoted_at' => '2026-07-22T12:00:00Z',
        ], $event->broadcastWith());
    }
}
