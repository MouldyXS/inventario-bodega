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
        // Requerimientos §5.2 (productos). Sin restricción única sobre nombre y
        // marca (RF-01): los posibles duplicados se avisan en el servicio.
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->string('nombre', 150)->index();
            $table->string('marca', 100)->nullable();
            $table->boolean('controla_caducidad')->default(true);
            $table->integer('stock_minimo')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE productos ADD CONSTRAINT chk_productos_stock_minimo
            CHECK (stock_minimo IS NULL OR stock_minimo >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
