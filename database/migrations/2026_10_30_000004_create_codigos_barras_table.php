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
        // Requerimientos §5.2 (codigos_barras): único en todo el catálogo.
        Schema::create('codigos_barras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentacion_id')->constrained('presentaciones')->restrictOnDelete();
            $table->string('codigo', 64)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codigos_barras');
    }
};
