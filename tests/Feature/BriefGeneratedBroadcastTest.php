<?php

namespace Tests\Feature;

use App\Events\BriefGenerated;
use App\Jobs\GenerateInstrumentBrief;
use App\Models\Instrument;
use Illuminate\Broadcasting\Channel;
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

    public function test_brief_generated_broadcast_payload_contract(): void
    {
        $briefEn = ['title' => 'Gold outlook', 'summary' => 'Steady gains expected.'];
        $briefAr = ['title' => 'توقعات الذهب', 'summary' => 'مكاسب مستقرة متوقعة.'];

        $event = new BriefGenerated('XAUUSD', 'tier_one', $briefEn, $briefAr, 'bullish', '2026-07-22T12:00:00Z');

        $this->assertSame('brief.generated', $event->broadcastAs());

        $channel = $event->broadcastOn();
        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame('briefs', $channel->name);

        $this->assertSame([
            'symbol' => 'XAUUSD',
            'tier' => 'tier_one',
            'brief_en' => $briefEn,
            'brief_ar' => $briefAr,
            'bias' => 'bullish',
            'bias_label_en' => 'Bullish',
            'bias_label_ar' => 'صعودي',
            'bias_class' => 'badge-bull',
            'generated_at' => '2026-07-22T12:00:00Z',
        ], $event->broadcastWith());
    }
}
