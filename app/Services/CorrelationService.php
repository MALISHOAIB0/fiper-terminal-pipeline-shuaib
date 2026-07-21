<?php

namespace App\Services;

use App\Models\Instrument;
use Illuminate\Support\Collection;

/**
 * Pearson correlation of daily returns between one instrument and every
 * other active instrument, over their overlapping OHLC history. Matches the
 * documented approach (90-day daily returns) at whatever seed size we
 * actually have — with only 3 seeded instruments this is a small matrix,
 * but it's real, not placeholder data.
 */
class CorrelationService
{
    public function correlationsFor(Instrument $subject, int $days = 90): Collection
    {
        $subjectReturns = $this->dailyReturnsByDate($subject, $days);

        return Instrument::where('is_active', true)
            ->where('id', '!=', $subject->id)
            ->get()
            ->map(function (Instrument $other) use ($subjectReturns, $days) {
                $otherReturns = $this->dailyReturnsByDate($other, $days);
                $dates = array_values(array_intersect(array_keys($subjectReturns), array_keys($otherReturns)));

                if (count($dates) < 5) {
                    return null;
                }

                $x = array_map(fn ($d) => $subjectReturns[$d], $dates);
                $y = array_map(fn ($d) => $otherReturns[$d], $dates);

                return [
                    'symbol' => $other->symbol,
                    'value' => round($this->pearson($x, $y), 4),
                ];
            })
            ->filter()
            ->sortByDesc(fn ($row) => abs($row['value']))
            ->values();
    }

    /** @return array<string, float> keyed by date */
    private function dailyReturnsByDate(Instrument $instrument, int $days): array
    {
        $rows = $instrument->ohlcDaily()->orderByDesc('date')->limit($days)->get()->reverse()->values();
        $returns = [];

        for ($i = 1; $i < $rows->count(); $i++) {
            $prev = (float) $rows[$i - 1]->close;
            $curr = (float) $rows[$i]->close;

            if ($prev != 0) {
                $returns[$rows[$i]->date->toDateString()] = ($curr - $prev) / $prev;
            }
        }

        return $returns;
    }

    /**
     * @param  array<int, float>  $x
     * @param  array<int, float>  $y
     */
    private function pearson(array $x, array $y): float
    {
        $n = count($x);
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $covariance = 0.0;
        $varX = 0.0;
        $varY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $covariance += $dx * $dy;
            $varX += $dx ** 2;
            $varY += $dy ** 2;
        }

        $denominator = sqrt($varX * $varY);

        return $denominator != 0 ? $covariance / $denominator : 0.0;
    }
}
