<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInstrumentBrief;
use App\Models\Instrument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The single, unified brief pipeline. Replaces the two documented parallel
 * commands (analytics:refresh-tier-one hourly + ai:refresh-syntheses every
 * 4h, the second of which had gone silently stale). Tier is a column on the
 * instrument, not a hardcoded symbol list duplicated across two code paths.
 */
#[Signature('analytics:refresh-briefs {--symbol=} {--tier=}')]
#[Description('Dispatch AI brief generation for active instruments (one queued job per instrument)')]
class RefreshBriefs extends Command
{
    public function handle(): int
    {
        $symbol = $this->option('symbol');
        $tier = $this->option('tier'); // 'one' | 'standard' | null (= all)

        $query = Instrument::where('is_active', true);

        if ($symbol) {
            $query->where('symbol', $symbol);
        }

        if ($tier === 'one') {
            $query->where('is_tier_one', true);
        } elseif ($tier === 'standard') {
            $query->where('is_tier_one', false);
        }

        $instruments = $query->get();

        foreach ($instruments as $instrument) {
            GenerateInstrumentBrief::dispatch($instrument->id);
        }

        $this->line("Dispatched brief generation for {$instruments->count()} instrument(s).");

        return self::SUCCESS;
    }
}
