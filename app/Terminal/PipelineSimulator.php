<?php

namespace App\Terminal;

use App\Enums\VesselStatus;
use App\Models\CargoMovement;
use App\Models\Tank;
use App\Models\Vessel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MartinKiragu\TankTerminal\Enums\MovementDirection;

/**
 * Demo feed: emits a burst of meter / weighbridge events whose arrival order
 * is shuffled relative to their event time, the way asynchronous source logs
 * reach a real terminal. Lets reviewers watch the sequencer and guards work.
 */
class PipelineSimulator
{
    /**
     * @return Collection<int, CargoMovement> the movements in the order they were logged
     */
    public function emit(int $count = 12): Collection
    {
        $count = max(1, min($count, 50));

        $tanks = Tank::query()->active()->get();
        $vessels = Vessel::query()->whereIn('status', [VesselStatus::Berthed, VesselStatus::Discharging])->get();

        if ($tanks->isEmpty()) {
            return collect();
        }

        $start = now()->subMinutes($count * 5);
        $batch = Str::upper(Str::random(4));

        $events = collect(range(1, $count))->map(function (int $i) use ($tanks, $vessels, $start, $batch) {
            $tank = $tanks->random();
            $inbound = $vessels->isNotEmpty() && random_int(0, 99) < 40;

            return [
                'reference' => sprintf('SIM-%s-%02d', $batch, $i),
                'direction' => $inbound ? MovementDirection::Inbound : MovementDirection::Outbound,
                'tank_id' => $tank->id,
                'vessel_id' => $inbound ? $vessels->random()->id : null,
                'truck_registration' => $inbound ? null : sprintf('K%s %03d%s', Str::upper(Str::random(2)), random_int(100, 999), Str::upper(Str::random(1))),
                // Inbound parcels are large (some will overflow); trucks carry 25–36 MT.
                'gross_kg' => $inbound ? random_int(200, 1_200) * 1000 : random_int(25_000, 36_000),
                'occurred_at' => $start->copy()->addMinutes($i * 5)->addSeconds(random_int(0, 59)),
            ];
        });

        // Log them in a scrambled order: arrival order != event order.
        return DB::transaction(fn () => $events->shuffle()->map(
            fn (array $attributes) => CargoMovement::query()->create($attributes)
        ));
    }
}
