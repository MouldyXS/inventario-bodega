<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Variación de existencias de un par (lote, presentación). Inmutable.
 * `cantidad_base` es una columna generada (cantidad × factor) que nunca se asigna.
 */
#[Table('detalles_movimiento')]
#[Fillable(['producto_id', 'lote_id', 'presentacion_id', 'cantidad', 'factor', 'costo_unitario'])]
#[WithoutTimestamps]
class DetalleMovimiento extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos confirmados no se modifican.'));
        static::deleting(fn () => throw new LogicException('Los movimientos confirmados no se eliminan.'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'factor' => 'integer',
            'cantidad_base' => 'integer',
            'costo_unitario' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Movimiento, $this>
     */
    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(Movimiento::class);
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return BelongsTo<Lote, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * @return BelongsTo<Presentacion, $this>
     */
    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class);
    }
}
