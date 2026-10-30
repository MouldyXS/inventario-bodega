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
        // Requerimientos §5.2 (movimientos). Cabecera inmutable: sin updated_at.
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['entrada', 'salida', 'conversion', 'ajuste', 'reversion']);
            $table->enum('motivo', [
                'compra', 'carga_inicial', 'venta_entrega', 'danado', 'vencido',
                'devolucion_proveedor', 'conteo_fisico', 'otro',
            ])->nullable();
            // Cuándo ocurrió (puede ser pasada, nunca futura: lo valida el servicio).
            $table->dateTime('fecha')->index();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->text('observacion')->nullable();
            $table->foreignId('revierte_movimiento_id')->nullable()
                ->unique('movimientos_revierte_unique')
                ->constrained('movimientos')->restrictOnDelete();
            $table->char('token', 36)->unique('movimientos_token_unique');
            // Cuándo se registró.
            $table->timestamp('created_at')->useCurrent();
        });

        // Combinaciones de tipo y motivo (§5.2). `motivo IS NOT NULL` es necesario:
        // un CHECK que evalúa a NULL se considera cumplido.
        DB::statement("ALTER TABLE movimientos ADD CONSTRAINT chk_movimientos_tipo_motivo CHECK (
            (tipo = 'entrada' AND motivo IS NOT NULL AND motivo IN ('compra', 'carga_inicial', 'otro'))
            OR (tipo = 'salida' AND motivo IS NOT NULL AND motivo IN ('venta_entrega', 'danado', 'vencido', 'devolucion_proveedor', 'otro'))
            OR (tipo = 'ajuste' AND motivo IS NOT NULL AND motivo IN ('conteo_fisico', 'danado', 'otro'))
            OR (tipo IN ('conversion', 'reversion') AND motivo IS NULL)
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
