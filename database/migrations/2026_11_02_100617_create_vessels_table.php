<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('imo_number', 7)->nullable()->unique();
            $table->string('voyage_number')->nullable();
            $table->string('status', 20)->default('expected')->index();
            $table->dateTime('eta')->nullable();
            $table->dateTime('berthed_at')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessels');
    }
};
