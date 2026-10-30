<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Requerimientos §5.2 (lotes). La fecha de recepción no se guarda aquí:
        // se obtiene de los movimientos de entrada (RF-04).
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            // Cadena vacía = sin código. No se usa NULL porque MySQL no considera
            // duplicadas las filas con NULL en un índice único.
            $table->string('codigo_lote', 50)->default('');
            $table->date('fecha_caducidad')->nullable()->index();
            // Solo para el índice único de identidad del lote.
            $table->date('caducidad_clave')->storedAs("COALESCE(fecha_caducidad, DATE '9999-12-31')");
            $table->timestamps();

            $table->unique(['producto_id', 'codigo_lote', 'caducidad_clave'], 'lotes_identidad_unique');
            // Destino de la clave foránea compuesta de detalles_movimiento.
            $table->unique(['id', 'producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
