<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `es_base` no es asignable en masa (solo lo fija CatalogoService al crear el
 * producto) y `producto_base` es una columna generada que nunca se asigna.
 */
#[Table('presentaciones')]
#[Fillable(['nombre', 'factor', 'activo'])]
class Presentacion extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor' => 'integer',
            'es_base' => 'boolean',
            'activo' => 'boolean',
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
     * @return HasMany<CodigoBarras, $this>
     */
    public function codigos(): HasMany
    {
        return $this->hasMany(CodigoBarras::class)->orderBy('codigo');
    }
}
