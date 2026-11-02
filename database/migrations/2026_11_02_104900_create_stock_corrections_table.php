<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manual stock corrections (e.g. after a tank dip / ullage survey disagrees
 * with the book balance). Always maker-checker: an operator requests, a
 * supervisor approves, and only then does the engine touch the balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained()->restrictOnDelete();
            $table->string('direction', 10); // increase | decrease
            $table->unsignedBigInteger('quantity_kg');
            $table->text('reason');
            $table->string('status', 20)->default('awaiting_approval')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->unsignedBigInteger('balance_after_kg')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_corrections ADD CONSTRAINT stock_corrections_qty_positive CHECK (quantity_kg > 0)');
            DB::statement("ALTER TABLE stock_corrections ADD CONSTRAINT stock_corrections_direction_valid CHECK (direction IN ('increase','decrease'))");
            DB::statement("ALTER TABLE stock_corrections ADD CONSTRAINT stock_corrections_status_valid CHECK (status IN ('awaiting_approval','applied','declined','failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_corrections');
    }
};
