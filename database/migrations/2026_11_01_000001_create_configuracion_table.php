<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Requerimientos §5.2 (configuracion).
        Schema::create('configuracion', function (Blueprint $table) {
            $table->string('clave', 50)->primary();
            $table->string('valor', 255);
            $table->timestamp('updated_at')->nullable();
        });

        // RF-08: periodo de alerta inicial de 30 días.
        DB::table('configuracion')->insert([
            'clave' => 'dias_alerta_caducidad',
            'valor' => '30',
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion');
    }
};
