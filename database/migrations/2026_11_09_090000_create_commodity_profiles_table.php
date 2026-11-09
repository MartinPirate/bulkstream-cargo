<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MartinKiragu\TankTerminal\Enums\Commodity;

/**
 * Editable handling profile per commodity. The three codes are fixed by the
 * domain (Commodity enum); what operators tune here is how each one is stored.
 * Rows are seeded in the migration so every environment has them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commodity_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 80);
            $table->string('handling_note', 500);
            $table->decimal('storage_temp_min_c', 4, 1);
            $table->decimal('storage_temp_max_c', 4, 1);
            $table->boolean('requires_heating');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE commodity_profiles ADD CONSTRAINT commodity_profiles_temp_band_valid CHECK (storage_temp_min_c < storage_temp_max_c)');
        }

        $now = now();
        foreach (Commodity::cases() as $commodity) {
            [$min, $max] = $commodity->storageTemperatureRange();
            DB::table('commodity_profiles')->insert([
                'code' => $commodity->value,
                'name' => $commodity->label(),
                'handling_note' => $commodity->handlingNote(),
                'storage_temp_min_c' => $min,
                'storage_temp_max_c' => $max,
                'requires_heating' => $commodity->requiresHeating(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commodity_profiles');
    }
};
