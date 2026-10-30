<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo para pruebas de esquema: crea el producto SIN presentación base.
 * Para un producto completo, usar CatalogoService::crearProducto().
 *
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'nombre' => fake()->unique()->words(3, true),
            'marca' => fake()->company(),
            'controla_caducidad' => true,
            'stock_minimo' => null,
            'activo' => true,
            'observaciones' => null,
        ];
    }
}
