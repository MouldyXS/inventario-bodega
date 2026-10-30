<?php

/*
 * Proceso PHP independiente para las pruebas de concurrencia (Requerimientos §12).
 * Lo lanzan las pruebas que usan Tests\Feature\Inventario\LanzaProcesos, con APP_ENV=testing.
 *
 * Uso: php tests/Concurrencia/trabajador.php '<json>'
 * JSON común: operacion (entrada | salida | revertir), usuario_id, token, inicio
 * (marca de tiempo Unix en la que empezar). Según la operación:
 *   entrada:  producto_id, presentacion_id, cantidad, codigo_lote, fecha_caducidad
 *   salida:   producto_id, lote_id, presentacion_id, cantidad
 *   revertir: movimiento_id
 *
 * Responde una línea JSON: {ok: true, movimiento, reenvio}, {ok: false, rechazado: true,
 * errores} si una regla la rechazó, o {ok: false, error} ante cualquier otro fallo.
 */

use App\Enums\MotivoMovimiento;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$conexion = config('database.default');
TestCase::asegurarBaseDePruebas($conexion, (string) config("database.connections.$conexion.database"));

$datos = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$inventario = app(InventarioService::class);
$usuario = User::findOrFail($datos['usuario_id']);

// Todos los procesos esperan al mismo instante para coincidir de verdad.
while (microtime(true) < $datos['inicio']) {
    usleep(1000);
}

try {
    $resultado = match ($datos['operacion']) {
        'entrada' => $inventario->registrarEntrada(
            $usuario, $datos['token'], Producto::findOrFail($datos['producto_id']), now(), MotivoMovimiento::Compra,
            [[
                'presentacion_id' => $datos['presentacion_id'],
                'cantidad' => $datos['cantidad'],
                'codigo_lote' => $datos['codigo_lote'],
                'fecha_caducidad' => $datos['fecha_caducidad'],
            ]],
        ),
        'salida' => $inventario->registrarSalida(
            $usuario, $datos['token'], Producto::findOrFail($datos['producto_id']), now(), MotivoMovimiento::VentaEntrega,
            [['lote_id' => $datos['lote_id'], 'presentacion_id' => $datos['presentacion_id'], 'cantidad' => $datos['cantidad']]],
        ),
        'revertir' => $inventario->revertir($usuario, $datos['token'], Movimiento::findOrFail($datos['movimiento_id']), 'Prueba de concurrencia'),
    };

    echo json_encode(['ok' => true, 'movimiento' => $resultado->movimiento->id, 'reenvio' => $resultado->reenvio]);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'rechazado' => true, 'errores' => $e->errors()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e::class.': '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
