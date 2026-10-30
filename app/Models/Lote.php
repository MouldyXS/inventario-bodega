<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Identidad: producto + código de lote + fecha de caducidad (RF-03). Solo se
 * crea dentro de una entrada (InventarioService). `caducidad_clave` es una
 * columna generada que nunca se asigna.
 */
#[Table('lotes')]
#[Fillable(['codigo_lote', 'fecha_caducidad'])]
class Lote extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_caducidad' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return HasMany<DetalleMovimiento, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleMovimiento::class);
    }

    public function etiqueta(): string
    {
        return $this->codigo_lote !== '' ? $this->codigo_lote : 'Sin código';
    }
}
