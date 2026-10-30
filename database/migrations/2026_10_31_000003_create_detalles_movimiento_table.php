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
        // Requerimientos §5.2 (detalles_movimiento). Inmutable.
        Schema::create('detalles_movimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('movimientos')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedBigInteger('lote_id');
            $table->unsignedBigInteger('presentacion_id');
            // Con signo (§5.1): las salidas son negativas.
            $table->integer('cantidad');
            // Copia del factor de la presentación en el momento de la operación.
            $table->integer('factor');
            $table->bigInteger('cantidad_base')->storedAs('cantidad * factor');
            // Por unidad de la presentación recibida; solo en entradas.
            $table->decimal('costo_unitario', 12, 4)->nullable();

            // El lote y la presentación pertenecen al mismo producto (Caso 11).
            $table->foreign(['lote_id', 'producto_id'], 'detalles_lote_producto_foreign')
                ->references(['id', 'producto_id'])->on('lotes')->restrictOnDelete();
            $table->foreign(['presentacion_id', 'producto_id'], 'detalles_presentacion_producto_foreign')
                ->references(['id', 'producto_id'])->on('presentaciones')->restrictOnDelete();

            $table->index(['lote_id', 'presentacion_id']);
        });

        DB::statement('ALTER TABLE detalles_movimiento ADD CONSTRAINT chk_detalles_cantidad CHECK (cantidad <> 0)');
        DB::statement('ALTER TABLE detalles_movimiento ADD CONSTRAINT chk_detalles_factor CHECK (factor >= 1)');
        DB::statement('ALTER TABLE detalles_movimiento ADD CONSTRAINT chk_detalles_costo
            CHECK (costo_unitario IS NULL OR (costo_unitario >= 0 AND cantidad > 0))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalles_movimiento');
    }
};
