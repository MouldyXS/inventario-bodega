<?php

namespace App\Models;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Cabecera de una operación de inventario. Inmutable (Requerimientos RF-09):
 * las correcciones son movimientos nuevos. Solo lo crea InventarioService.
 */
#[Table('movimientos')]
#[Fillable(['tipo', 'motivo', 'fecha', 'usuario_id', 'observacion', 'revierte_movimiento_id', 'token'])]
class Movimiento extends Model
{
    public const UPDATED_AT = null;

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
            'tipo' => TipoMovimiento::class,
            'motivo' => MotivoMovimiento::class,
            'fecha' => 'datetime',
        ];
    }

    /**
     * @return HasMany<DetalleMovimiento, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleMovimiento::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Movimiento que esta reversión revierte.
     *
     * @return BelongsTo<Movimiento, $this>
     */
    public function revierte(): BelongsTo
    {
        return $this->belongsTo(Movimiento::class, 'revierte_movimiento_id');
    }

    /**
     * Reversión que revierte a este movimiento, si existe.
     *
     * @return HasOne<Movimiento, $this>
     */
    public function reversion(): HasOne
    {
        return $this->hasOne(Movimiento::class, 'revierte_movimiento_id');
    }
}
