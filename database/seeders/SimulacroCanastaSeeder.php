<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use App\Services\CargaInicialService;
use App\Services\CatalogoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DATOS DE SIMULACRO: productos de la canasta básica ecuatoriana con existencias
 * variadas (agotados, bajo el mínimo, próximos a caducar, vencidos, varios
 * lotes, cajas y sueltas) para probar la aplicación antes de tener los datos
 * reales. No son datos reales: se eliminan antes de la carga inicial verdadera.
 *
 *   php artisan db:seed --class=SimulacroCanastaSeeder --force
 *
 * Las fechas de caducidad son relativas a hoy. Los costos son estimaciones de compra
 * en USD por unidad de cada presentación (octubre de 2026) y las marcas son las
 * habituales en Ecuador: no son precios verificados de Guayaquil.
 */
class SimulacroCanastaSeeder extends Seeder
{
    private const NOTA = 'Dato de simulacro';

    /**
     * categoría => [nombre, marca, controla caducidad, stock mínimo (unidades base), unidad base, [presentación extra => factor]]
     *
     * @return array<string, list<array<int, mixed>>>
     */
    private function catalogo(): array
    {
        return [
            'Granos y cereales' => [
                ['Arroz blanco 1 kg', 'Conejo', false, 40, 'Funda', ['Saco x25' => 25]],
                ['Lenteja 500 g', null, true, 20, 'Funda', ['Caja x20' => 20]],
                ['Fréjol canario 500 g', null, true, 15, 'Funda', ['Caja x20' => 20]],
                ['Avena en hojuelas 400 g', 'Quaker', true, 12, 'Funda', []],
            ],
            'Aceites' => [
                ['Aceite de girasol 1 L', 'Girasol', true, 24, 'Botella', ['Caja x12' => 12]],
                ['Aceite vegetal 3 L', 'Cocinero', true, 10, 'Botella', ['Caja x4' => 4]],
                ['Margarina 250 g', 'Dorina', true, 10, 'Unidad', ['Caja x24' => 24]],
            ],
            'Azúcar, sal y endulzantes' => [
                ['Azúcar blanca 1 kg', 'Valdez', false, 40, 'Funda', ['Saco x50' => 50]],
                ['Panela granulada 500 g', null, true, 10, 'Funda', []],
                ['Sal yodada 1 kg', 'Crisal', false, 20, 'Funda', ['Caja x25' => 25]],
            ],
            'Enlatados y conservas' => [
                ['Atún lomitos en aceite 160 g', 'Real', true, 48, 'Lata', ['Caja x48' => 48]],
                ['Sardina en salsa de tomate 425 g', 'Gustadina', true, 24, 'Lata', ['Caja x24' => 24]],
                ['Salsa de tomate 400 g', 'Snob', true, 12, 'Frasco', ['Caja x24' => 24]],
            ],
            'Lácteos y huevos' => [
                ['Leche entera UHT 1 L', 'Vita', true, 60, 'Funda', ['Caja x12' => 12]],
                ['Queso fresco 500 g', 'El Ordeño', true, 8, 'Unidad', []],
                ['Yogur natural 1 L', 'Toni', true, 10, 'Botella', ['Caja x6' => 6]],
                ['Huevos AA', null, true, 90, 'Huevo', ['Cubeta x30' => 30]],
            ],
            'Pastas, harinas y panadería' => [
                ['Fideo tallarín 400 g', 'Sumesa', true, 30, 'Funda', ['Caja x20' => 20]],
                ['Harina de trigo 1 kg', 'Moderna', true, 15, 'Funda', ['Caja x10' => 10]],
                ['Pan de molde 600 g', 'Bimbo', true, 8, 'Funda', []],
                ['Galletas de sal 6 u', 'Ritz', true, 12, 'Paquete', ['Caja x24' => 24]],
            ],
            'Bebidas y café' => [
                ['Café molido 250 g', 'Minerva', true, 8, 'Funda', ['Caja x20' => 20]],
                ['Agua sin gas 600 ml', 'Tesalia', true, 48, 'Botella', ['Paquete x12' => 12, 'Caja x24' => 24]],
                ['Jugo de naranja 1 L', 'Del Valle', true, 10, 'Envase', ['Caja x12' => 12]],
            ],
            'Condimentos' => [
                ['Comino molido 50 g', 'McCormick', true, 6, 'Funda', []],
                ['Caldo de gallina (cubos x12)', 'Maggi', true, 10, 'Paquete', ['Caja x24' => 24]],
            ],
            'Limpieza del hogar' => [
                ['Detergente en polvo 1 kg', 'Deja', false, 12, 'Funda', ['Caja x12' => 12]],
                ['Cloro 1 L', 'Clorox', false, 10, 'Botella', ['Caja x12' => 12]],
                ['Jabón de lavar 250 g', 'Ales', false, 15, 'Barra', ['Paquete x10' => 10]],
                ['Lavavajillas en crema 500 g', 'Axion', false, 10, 'Pote', []],
            ],
            'Aseo personal' => [
                ['Papel higiénico doble hoja', 'Familia', false, 10, 'Paquete x12', ['Fardo x8' => 8]],
                ['Jabón de tocador 90 g', 'Protex', false, 20, 'Barra', ['Caja x72' => 72]],
                ['Pasta dental 75 ml', 'Colgate', true, 12, 'Tubo', ['Caja x36' => 36]],
            ],
        ];
    }

    /**
     * Existencias: [producto, presentación, cantidad, lote, días hasta la caducidad (null = sin fecha), costo].
     * Sin fila = producto sin existencias.
     *
     * @return list<array<int, mixed>>
     */
    private function existencias(): array
    {
        return [
            ['Arroz blanco 1 kg', 'Saco x25', 6, 'A-2410', null, 27.5],
            ['Arroz blanco 1 kg', 'Funda', 18, '', null, 1.2],
            ['Lenteja 500 g', 'Caja x20', 3, 'LE-0526', 210, 17.5],
            ['Lenteja 500 g', 'Funda', 7, 'LE-0526', 210, 0.95],
            ['Fréjol canario 500 g', 'Caja x20', 2, 'FR-1125', 25, 24],
            ['Avena en hojuelas 400 g', 'Funda', 9, 'AV-77', 130, 2.1],
            ['Aceite de girasol 1 L', 'Caja x12', 10, 'L-1', 160, 28.5],
            ['Aceite de girasol 1 L', 'Caja x12', 4, 'L-2', 18, 28.9],
            ['Aceite de girasol 1 L', 'Botella', 5, 'L-1', 160, 2.45],
            ['Aceite vegetal 3 L', 'Caja x4', 5, 'CO-31', 300, 29],
            ['Margarina 250 g', 'Caja x24', 1, 'MG-09', 45, 27],
            ['Margarina 250 g', 'Unidad', 6, 'MG-09', 45, 1.2],
            ['Azúcar blanca 1 kg', 'Saco x50', 8, '', null, 49],
            ['Azúcar blanca 1 kg', 'Funda', 33, '', null, 1.05],
            ['Panela granulada 500 g', 'Funda', 14, 'PA-A', 90, 0.9],
            ['Sal yodada 1 kg', 'Caja x25', 2, '', null, 15.5],
            ['Sal yodada 1 kg', 'Funda', 3, '', null, 0.65],
            ['Atún lomitos en aceite 160 g', 'Caja x48', 1, 'T-5521', 540, 108],
            ['Atún lomitos en aceite 160 g', 'Lata', 12, 'T-5521', 540, 2.35],
            ['Sardina en salsa de tomate 425 g', 'Caja x24', 5, 'SG-12', 700, 44],
            ['Salsa de tomate 400 g', 'Frasco', 15, 'ST-3', 24, 1.6],
            ['Salsa de tomate 400 g', 'Caja x24', 1, 'ST-1', -12, 36],
            ['Leche entera UHT 1 L', 'Caja x12', 2, 'LH-88', 95, 12.9],
            ['Leche entera UHT 1 L', 'Funda', 9, 'LH-80', 12, 1.1],
            ['Yogur natural 1 L', 'Caja x6', 1, 'Y-40', 8, 12.6],
            ['Huevos AA', 'Cubeta x30', 6, 'H-301', 20, 3.7],
            ['Huevos AA', 'Huevo', 14, 'H-301', 20, 0.13],
            ['Fideo tallarín 400 g', 'Caja x20', 6, 'FT-14', 400, 17.5],
            ['Fideo tallarín 400 g', 'Funda', 11, 'FT-14', 400, 0.95],
            ['Harina de trigo 1 kg', 'Caja x10', 4, 'HT-02', 150, 11.8],
            ['Pan de molde 600 g', 'Funda', 6, 'PM-d1', 5, 2.3],
            ['Galletas de sal 6 u', 'Caja x24', 3, 'GS-7', 120, 25],
            ['Café molido 250 g', 'Caja x20', 1, 'CF-3', 280, 72],
            ['Café molido 250 g', 'Funda', 5, 'CF-3', 280, 3.8],
            ['Agua sin gas 600 ml', 'Caja x24', 5, 'AG-1', 360, 9.6],
            ['Agua sin gas 600 ml', 'Paquete x12', 3, 'AG-1', 360, 4.9],
            ['Agua sin gas 600 ml', 'Botella', 8, 'AG-1', 360, 0.45],
            ['Jugo de naranja 1 L', 'Caja x12', 2, 'JN-9', 60, 19.8],
            ['Caldo de gallina (cubos x12)', 'Caja x24', 1, 'CG-4', 330, 33],
            ['Detergente en polvo 1 kg', 'Caja x12', 4, '', null, 26],
            ['Detergente en polvo 1 kg', 'Funda', 7, '', null, 2.3],
            ['Cloro 1 L', 'Botella', 4, '', null, 1.25],
            ['Jabón de lavar 250 g', 'Paquete x10', 5, '', null, 7],
            ['Jabón de lavar 250 g', 'Barra', 7, '', null, 0.75],
            ['Lavavajillas en crema 500 g', 'Pote', 20, '', null, 1.7],
            ['Papel higiénico doble hoja', 'Fardo x8', 3, '', null, 38],
            ['Papel higiénico doble hoja', 'Paquete x12', 4, '', null, 4.9],
            ['Jabón de tocador 90 g', 'Caja x72', 1, '', null, 62],
            ['Pasta dental 75 ml', 'Caja x36', 2, 'PD-2', 500, 52],
        ];
    }

    public function run(CatalogoService $catalogo, CargaInicialService $carga): void
    {
        $admin = User::where('rol', 'admin')->where('activo', true)->orderBy('id')->firstOrFail();

        foreach ($this->catalogo() as $nombreCategoria => $productos) {
            $categoria = Categoria::firstOrCreate(['nombre' => $nombreCategoria]);

            foreach ($productos as [$nombre, $marca, $caducidad, $minimo, $base, $extras]) {
                if ($catalogo->posiblesDuplicados($nombre, $marca)->isNotEmpty()) {
                    continue;
                }

                $producto = $catalogo->crearProducto([
                    'categoria_id' => $categoria->id,
                    'nombre' => $nombre,
                    'marca' => $marca,
                    'controla_caducidad' => $caducidad,
                    'stock_minimo' => $minimo,
                    'observaciones' => self::NOTA,
                ], $base);

                foreach ($extras as $presentacion => $factor) {
                    $producto->presentaciones()->create(['nombre' => $presentacion, 'factor' => $factor]);
                }
            }
        }

        $lineas = ['producto;presentacion;cantidad;lote;caducidad;costo'];

        foreach ($this->existencias() as [$producto, $presentacion, $cantidad, $lote, $dias, $costo]) {
            $lineas[] = implode(';', [
                $producto, $presentacion, $cantidad, $lote,
                $dias === null ? '' : now()->addDays($dias)->toDateString(),
                $costo ?? '',
            ]);
        }

        $lectura = $carga->leer(implode("\n", $lineas));

        if ($lectura['errores'] !== []) {
            throw new \RuntimeException(implode("\n", $lectura['errores']));
        }

        $movimientos = $carga->registrar($admin, (string) Str::uuid(), $lectura['filas'], now());

        $this->command?->info('Simulacro cargado: '.Producto::count()." productos, $movimientos movimientos.");
    }
}
