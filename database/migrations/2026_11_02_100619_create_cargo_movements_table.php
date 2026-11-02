<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_movements', function (Blueprint $table) {
            $table->id();
            // Idempotency key from the source system (meter ticket, B/L line, weighbridge ticket).
            $table->string('reference', 64)->unique();
            $table->string('direction', 10);
            $table->foreignId('tank_id')->constrained()->restrictOnDelete();
            $table->foreignId('vessel_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('truck_registration', 20)->nullable();
            $table->string('commodity', 10);
            $table->unsignedBigInteger('gross_kg');
            $table->unsignedBigInteger('loss_kg')->nullable();
            $table->unsignedBigInteger('net_kg')->nullable();
            $table->unsignedBigInteger('balance_after_kg')->nullable();
            // Microsecond precision so near-simultaneous source events keep their true order.
            $table->dateTime('occurred_at', 6);
            $table->dateTime('received_at', 6);
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->dateTime('processed_at', 6)->nullable();
            // Supervisor approval for large releases.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'occurred_at']);
            $table->index(['tank_id', 'occurred_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cargo_movements ADD CONSTRAINT cargo_movements_gross_positive CHECK (gross_kg > 0)');
            DB::statement("ALTER TABLE cargo_movements ADD CONSTRAINT cargo_movements_direction_valid CHECK (direction IN ('inbound','outbound'))");
            DB::statement("ALTER TABLE cargo_movements ADD CONSTRAINT cargo_movements_status_valid CHECK (status IN ('awaiting_approval','declined','pending','applied','rejected'))");
            // Inbound flows come off a vessel; outbound flows go onto a truck.
            DB::statement("ALTER TABLE cargo_movements ADD CONSTRAINT cargo_movements_party_matches_direction CHECK ((direction = 'inbound' AND vessel_id IS NOT NULL) OR (direction = 'outbound' AND truck_registration IS NOT NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_movements');
    }
};
