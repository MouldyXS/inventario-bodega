<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un artículo concreto: "Aceite de girasol 1 L" y "Aceite de girasol 500 ml"
 * son productos distintos (RF-01). Se crea siempre con su presentación base
 * mediante CatalogoService.
 */
#[Table('productos')]
#[Fillable(['categoria_id', 'nombre', 'marca', 'controla_caducidad', 'stock_minimo', 'activo', 'observaciones'])]
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'controla_caducidad' => 'boolean',
            'stock_minimo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Base primero y después por factor.
     *
     * @return HasMany<Presentacion, $this>
     */
    public function presentaciones(): HasMany
    {
        return $this->hasMany(Presentacion::class)->orderByDesc('es_base')->orderBy('factor')->orderBy('nombre');
    }

    /**
     * @return HasOne<Presentacion, $this>
     */
    public function presentacionBase(): HasOne
    {
        return $this->hasOne(Presentacion::class)->where('es_base', true);
    }

    /**
     * Nombre o marca parcial; código de barras exacto (§7).
     *
     * @param  Builder<self>  $query
     */
    public function scopeBuscar(Builder $query, string $texto): void
    {
        $patron = '%'.addcslashes($texto, '%_\\').'%';

        $query->where(fn (Builder $q) => $q
            ->where($this->qualifyColumn('nombre'), 'like', $patron)
            ->orWhere($this->qualifyColumn('marca'), 'like', $patron)
            ->orWhereHas('presentaciones.codigos', fn (Builder $c) => $c->where('codigo', $texto)));
    }
}
