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
        // Requerimientos §5.2 (presentaciones).
        Schema::create('presentaciones', function (Blueprint $table) {
            $table->id();
            // RESTRICT es obligatorio: producto_id es la base de una columna generada.
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('nombre', 50);
            // Con signo a propósito (§5.1): cantidad * factor admite cantidades negativas.
            $table->integer('factor');
            $table->boolean('es_base')->default(false);
            // Como máximo una presentación base por producto: MySQL no tiene índices
            // únicos parciales y admite varios NULL en un índice único.
            $table->unsignedBigInteger('producto_base')->nullable()
                ->storedAs('IF(es_base = 1, producto_id, NULL)')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['producto_id', 'nombre']);
            // Destino de las claves foráneas compuestas de detalles_movimiento (§5.2).
            $table->unique(['id', 'producto_id']);
        });

        DB::statement('ALTER TABLE presentaciones ADD CONSTRAINT chk_presentaciones_factor
            CHECK (factor >= 1)');
        DB::statement('ALTER TABLE presentaciones ADD CONSTRAINT chk_presentaciones_base_factor
            CHECK (es_base = 0 OR factor = 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentaciones');
    }
};
