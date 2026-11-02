<?php

namespace App\Models;

use App\Enums\MovementStatus;
use App\Observers\CargoMovementObserver;
use Database\Factories\CargoMovementFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MartinKiragu\TankTerminal\Domain\CargoMovement as DomainMovement;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Enums\Commodity;
use MartinKiragu\TankTerminal\Enums\MovementDirection;

/**
 * One cargo event in the vessel → tank → truck pipeline.
 *
 * Rows are an append-only ledger: once applied or rejected they are never
 * edited (enforced by CargoMovementObserver).
 *
 * @property int $id
 * @property string $reference
 * @property MovementDirection $direction
 * @property Commodity $commodity
 * @property MovementStatus $status
 * @property int $gross_kg
 */
#[ObservedBy(CargoMovementObserver::class)]
class CargoMovement extends Model
{
    /** @use HasFactory<CargoMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'reference', 'direction', 'tank_id', 'vessel_id', 'truck_registration',
        'commodity', 'gross_kg', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MovementDirection::class,
            'commodity' => Commodity::class,
            'status' => MovementStatus::class,
            'gross_kg' => 'integer',
            'loss_kg' => 'integer',
            'net_kg' => 'integer',
            'balance_after_kg' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === MovementStatus::Pending;
    }

    public function toDomain(): DomainMovement
    {
        return new DomainMovement(
            id: $this->id,
            direction: $this->direction,
            tankId: $this->tank_id,
            commodity: $this->commodity,
            quantity: Quantity::kg($this->gross_kg),
            occurredAt: DateTimeImmutable::createFromInterface($this->occurred_at),
        );
    }
}
