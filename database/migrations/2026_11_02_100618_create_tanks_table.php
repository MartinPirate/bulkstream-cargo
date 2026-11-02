<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('commodity', 10)->index();
            // Whole kilograms; the UI renders metric tonnes.
            $table->unsignedBigInteger('capacity_kg');
            $table->unsignedBigInteger('balance_kg')->default(0);
            $table->decimal('temperature_c', 5, 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Defence in depth: the database refuses an overfilled tank even if
        // application code is bypassed (manual SQL, a future bug).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tanks ADD CONSTRAINT tanks_balance_within_capacity CHECK (balance_kg <= capacity_kg)');
            DB::statement('ALTER TABLE tanks ADD CONSTRAINT tanks_capacity_positive CHECK (capacity_kg > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tanks');
    }
};
