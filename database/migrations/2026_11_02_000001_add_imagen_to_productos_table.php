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
        // Imagen principal opcional del producto: solo la versión vigente; los
        // archivos se derivan de ella (productos/{id}/{version}-m.webp y -t.webp).
        Schema::table('productos', function (Blueprint $table) {
            $table->string('imagen', 80)->nullable()->after('observaciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('imagen');
        });
    }
};
