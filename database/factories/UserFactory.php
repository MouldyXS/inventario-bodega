<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'rol' => Rol::Encargado,
            'puede_gestionar_catalogo' => false,
            'puede_ajustar_inventario' => false,
            'puede_revertir' => false,
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol' => Rol::Admin,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /**
     * @param  'puede_gestionar_catalogo'|'puede_ajustar_inventario'|'puede_revertir'  $permiso
     */
    public function conPermiso(string $permiso): static
    {
        return $this->state(fn (array $attributes) => [
            $permiso => true,
        ]);
    }
}
