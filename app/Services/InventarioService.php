<?php

namespace App\Services;

use App\Enums\MotivoMovimiento;
use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Único código que escribe movimientos (Requerimientos §5.6 y §6.2).
 *
 * Procedimiento común (ejecutar): token de idempotencia → transacción con
 * reintentos → bloqueo de lotes en orden de id → validación, incluida la de
 * negativos por par (lote, presentación) → inserción. Los errores de clave
 * duplicada se clasifican por el índice que los provoca.
 *
 * Cada línea interna: lote_id, presentacion_id, cantidad (con signo), factor,
 * costo_unitario y campo (la clave del error de validación que le corresponde).
 */
class InventarioService
{
    private const INTENTOS_TRANSACCION = 3;

    public function __construct(
        private ExistenciasService $existencias = new ExistenciasService,
    ) {}

    // ---------------------------------------------------------------------
    // Operaciones
    // ---------------------------------------------------------------------

    /**
     * RF-03. Cada línea: presentacion_id, cantidad, codigo_lote (opcional),
     * fecha_caducidad (Y-m-d, opcional), costo_unitario (opcional, por unidad
     * de la presentación recibida). Los lotes se obtienen o se crean aquí.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    public function registrarEntrada(
        User $usuario,
        string $token,
        Producto $producto,
        CarbonInterface $fecha,
        MotivoMovimiento $motivo,
        array $lineas,
        ?string $observacion = null,
    ): ResultadoMovimiento {
        $tipo = TipoMovimiento::Entrada;

        return $this->ejecutar($usuario, $token, $tipo, function () use ($usuario, $token, $producto, $fecha, $motivo, $lineas, $observacion, $tipo) {
            $this->validarCabecera($tipo, $motivo, $fecha, $observacion);
            $lineas = $this->normalizarLineasEntrada($lineas);

            $producto = $this->bloquearProducto($producto->id);
            $this->validarCaducidades($producto, $lineas);

            $lotesPorLinea = $this->obtenerLotes($producto, $lineas);
            $this->bloquearLotes($producto, array_map(fn (Lote $lote) => $lote->id, $lotesPorLinea));
            $presentaciones = $this->leerPresentaciones($producto, $this->camposPresentacion($lineas));

            $detalles = [];

            foreach ($lineas as $i => $linea) {
                $detalles[] = [
                    'lote_id' => $lotesPorLinea[$i]->id,
                    'presentacion_id' => $linea['presentacion_id'],
                    'cantidad' => $linea['cantidad'],
                    'factor' => $presentaciones[$linea['presentacion_id']]->factor,
                    'costo_unitario' => $linea['costo_unitario'],
                    'campo' => "lineas.$i.cantidad",
                ];
            }

            return $this->guardar($tipo, $motivo, $usuario, $token, $fecha, $observacion, $producto, $detalles);
        });
    }

    /**
     * RF-06 y RF-07. Cada línea: lote_id, presentacion_id, cantidad (positiva,
     * la que sale). De un lote vencido solo se sale con motivo "vencido" o "dañado".
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    public function registrarSalida(
        User $usuario,
        string $token,
        Producto $producto,
        CarbonInterface $fecha,
        MotivoMovimiento $motivo,
        array $lineas,
        ?string $observacion = null,
    ): ResultadoMovimiento {
        $tipo = TipoMovimiento::Salida;

        return $this->ejecutar($usuario, $token, $tipo, function () use ($usuario, $token, $producto, $fecha, $motivo, $lineas, $observacion, $tipo) {
            $this->validarCabecera($tipo, $motivo, $fecha, $observacion);
            $lineas = $this->normalizarLineasConLote($lineas, 'cantidad', minimo: 1);

            $producto = $this->bloquearProducto($producto->id);
            $lotes = $this->bloquearLotes($producto, array_column($lineas, 'lote_id'));
            $presentaciones = $this->leerPresentaciones($producto, $this->camposPresentacion($lineas));

            $errores = [];
            $detalles = [];

            foreach ($lineas as $i => $linea) {
                $lote = $lotes->get($linea['lote_id']);

                if ($lote === null) {
                    $errores["lineas.$i.lote_id"] = 'El lote no pertenece a este producto.';

                    continue;
                }

                if ($this->existencias->estaVencido($lote) && ! in_array($motivo, [MotivoMovimiento::Vencido, MotivoMovimiento::Danado], true)) {
                    $errores["lineas.$i.lote_id"] = "El lote {$lote->etiqueta()} está vencido: solo puede salir con motivo «Producto vencido» o «Producto dañado».";
                }

                $detalles[] = [
                    'lote_id' => $lote->id,
                    'presentacion_id' => $linea['presentacion_id'],
                    'cantidad' => -$linea['cantidad'],
                    'factor' => $presentaciones[$linea['presentacion_id']]->factor,
                    'campo' => "lineas.$i.cantidad",
                ];
            }

            $this->lanzarSiHayErrores($errores);
            $this->validarSinNegativos($detalles, $lotes, $presentaciones);

            return $this->guardar($tipo, $motivo, $usuario, $token, $fecha, $observacion, $producto, $detalles);
        });
    }

    /**
     * RF-05. Convierte una cantidad de una presentación en su equivalente exacto
     * en otra, dentro del mismo lote (abrir cajas o reempaquetar). Conserva las
     * unidades base. Se permite también en lotes vencidos.
     */
    public function registrarConversion(
        User $usuario,
        string $token,
        Lote $lote,
        CarbonInterface $fecha,
        int $presentacionOrigenId,
        int $cantidadOrigen,
        int $presentacionDestinoId,
        ?string $observacion = null,
    ): ResultadoMovimiento {
        $tipo = TipoMovimiento::Conversion;

        return $this->ejecutar($usuario, $token, $tipo, function () use ($usuario, $token, $lote, $fecha, $presentacionOrigenId, $cantidadOrigen, $presentacionDestinoId, $observacion, $tipo) {
            $this->validarCabecera($tipo, null, $fecha, $observacion);

            if ($cantidadOrigen < 1) {
                throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser un número entero mayor que cero.']);
            }

            if ($presentacionOrigenId === $presentacionDestinoId) {
                throw ValidationException::withMessages(['presentacion_destino_id' => 'Elige una presentación de destino distinta de la de origen.']);
            }

            $producto = $this->bloquearProducto($lote->producto_id);
            $lotes = $this->bloquearLotes($producto, [$lote->id]);
            $presentaciones = $this->leerPresentaciones($producto, [
                'presentacion_origen_id' => $presentacionOrigenId,
                'presentacion_destino_id' => $presentacionDestinoId,
            ], exigirActivas: false);

            $origen = $presentaciones[$presentacionOrigenId];
            $destino = $presentaciones[$presentacionDestinoId];

            if (! $destino->activo) {
                throw ValidationException::withMessages(['presentacion_destino_id' => 'La presentación de destino está desactivada.']);
            }

            $unidadesBase = $cantidadOrigen * $origen->factor;

            if ($unidadesBase % $destino->factor !== 0) {
                throw ValidationException::withMessages([
                    'cantidad' => "{$cantidadOrigen} {$origen->nombre} son {$unidadesBase} unidades base: no forman un número exacto de {$destino->nombre} ({$destino->factor} cada una).",
                ]);
            }

            $detalles = [
                ['lote_id' => $lote->id, 'presentacion_id' => $origen->id, 'cantidad' => -$cantidadOrigen, 'factor' => $origen->factor, 'campo' => 'cantidad'],
                ['lote_id' => $lote->id, 'presentacion_id' => $destino->id, 'cantidad' => intdiv($unidadesBase, $destino->factor), 'factor' => $destino->factor, 'campo' => 'cantidad'],
            ];

            $this->validarSinNegativos($detalles, $lotes, $presentaciones);

            return $this->guardar($tipo, null, $usuario, $token, $fecha, $observacion, $producto, $detalles);
        });
    }

    /**
     * RF-09. Ajuste por cantidad contada: cada conteo es lote_id, presentacion_id
     * y contado (≥ 0). La diferencia con la existencia registrada se calcula
     * después de bloquear los lotes, así que una operación simultánea no la
     * falsea. Los conteos sin diferencia se ignoran.
     *
     * @param  list<array<string, mixed>>  $conteos
     */
    public function registrarAjuste(
        User $usuario,
        string $token,
        Producto $producto,
        CarbonInterface $fecha,
        MotivoMovimiento $motivo,
        array $conteos,
        ?string $observacion,
    ): ResultadoMovimiento {
        Gate::forUser($usuario)->authorize('ajustar-inventario');
        $tipo = TipoMovimiento::Ajuste;

        return $this->ejecutar($usuario, $token, $tipo, function () use ($usuario, $token, $producto, $fecha, $motivo, $conteos, $observacion, $tipo) {
            $this->validarCabecera($tipo, $motivo, $fecha, $observacion, observacionObligatoria: true);
            $conteos = $this->normalizarLineasConLote($conteos, 'contado', minimo: 0, prefijo: 'conteos');

            $producto = $this->bloquearProducto($producto->id);
            $lotes = $this->bloquearLotes($producto, array_column($conteos, 'lote_id'));
            $presentaciones = $this->leerPresentaciones($producto, $this->camposPresentacion($conteos, 'conteos'), exigirActivas: false);

            $errores = [];
            $vistos = [];

            foreach ($conteos as $i => $conteo) {
                $clave = $conteo['lote_id'].'|'.$conteo['presentacion_id'];

                if ($lotes->get($conteo['lote_id']) === null) {
                    $errores["conteos.$i.lote_id"] = 'El lote no pertenece a este producto.';
                } elseif (isset($vistos[$clave])) {
                    $errores["conteos.$i.contado"] = 'Ese lote y esa presentación ya están en otra línea.';
                }

                $vistos[$clave] = true;
            }

            $this->lanzarSiHayErrores($errores);

            $saldos = $this->saldos(array_map(fn ($c) => [$c['lote_id'], $c['presentacion_id']], $conteos));
            $detalles = [];

            foreach ($conteos as $i => $conteo) {
                $diferencia = $conteo['contado'] - ($saldos[$conteo['lote_id'].'|'.$conteo['presentacion_id']] ?? 0);
                $presentacion = $presentaciones[$conteo['presentacion_id']];

                if ($diferencia === 0) {
                    continue;
                }

                if ($diferencia > 0 && ! $presentacion->activo) {
                    $errores["conteos.$i.contado"] = 'La presentación está desactivada: no puede aumentar.';
                }

                $detalles[] = [
                    'lote_id' => $conteo['lote_id'],
                    'presentacion_id' => $presentacion->id,
                    'cantidad' => $diferencia,
                    'factor' => $presentacion->factor,
                    'campo' => "conteos.$i.contado",
                ];
            }

            $this->lanzarSiHayErrores($errores);

            if ($detalles === []) {
                throw ValidationException::withMessages(['conteos' => 'No hay diferencias con las existencias registradas: no se registró ningún ajuste.']);
            }

            $this->validarSinNegativos($detalles, $lotes, $presentaciones);

            return $this->guardar($tipo, $motivo, $usuario, $token, $fecha, $observacion, $producto, $detalles);
        });
    }

    /**
     * RF-09. Crea la negación exacta del movimiento original, con su mismo
     * factor. Una reversión no se revierte y un movimiento se revierte una sola
     * vez (comprobado bajo el bloqueo de los lotes y, además, por un índice único).
     */
    public function revertir(User $usuario, string $token, Movimiento $original, ?string $observacion): ResultadoMovimiento
    {
        Gate::forUser($usuario)->authorize('revertir');
        $tipo = TipoMovimiento::Reversion;

        try {
            return $this->ejecutar($usuario, $token, $tipo, function () use ($usuario, $token, $original, $observacion, $tipo) {
                $fecha = now();
                $this->validarCabecera($tipo, null, $fecha, $observacion, observacionObligatoria: true);

                if ($original->tipo === TipoMovimiento::Reversion) {
                    throw ValidationException::withMessages(['movimiento' => 'Una reversión no puede revertirse. Registra un movimiento nuevo.']);
                }

                $lineasOriginales = $original->detalles()->orderBy('id')->get();
                $producto = $this->bloquearProducto($lineasOriginales->first()->producto_id);
                $lotes = $this->bloquearLotes($producto, $lineasOriginales->pluck('lote_id')->all());

                if (Movimiento::where('revierte_movimiento_id', $original->id)->exists()) {
                    throw ValidationException::withMessages(['movimiento' => 'Este movimiento ya fue revertido.']);
                }

                $detalles = $lineasOriginales->map(fn ($detalle) => [
                    'lote_id' => $detalle->lote_id,
                    'presentacion_id' => $detalle->presentacion_id,
                    'cantidad' => -$detalle->cantidad,
                    // La negación exacta usa el factor original, no el actual.
                    'factor' => $detalle->factor,
                    'campo' => 'movimiento',
                ])->all();

                $presentaciones = Presentacion::whereIn('id', $lineasOriginales->pluck('presentacion_id'))->get()->keyBy('id');
                $this->validarSinNegativos($detalles, $lotes, $presentaciones);

                return $this->guardar($tipo, null, $usuario, $token, $fecha, $observacion, $producto, $detalles, $original);
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($this->esIndice($e, 'movimientos_revierte_unique')) {
                throw ValidationException::withMessages(['movimiento' => 'Este movimiento ya fue revertido.']);
            }

            throw $e;
        }
    }

    /**
     * RF-04. Corrige el código o la caducidad de un lote (no es un movimiento).
     * Se rechaza si el lote resultante coincide con otro lote del producto.
     */
    public function corregirLote(User $usuario, Lote $lote, ?string $codigo, ?string $caducidad): Lote
    {
        Gate::forUser($usuario)->authorize('ajustar-inventario');

        $codigo = Str::squish((string) $codigo);
        $caducidad = blank($caducidad) ? null : (string) $caducidad;
        $this->lanzarSiHayErrores(array_filter([
            'codigo_lote' => $this->errorCodigoLote($codigo),
            'fecha_caducidad' => $this->errorFecha($caducidad),
        ]));

        try {
            return DB::transaction(function () use ($lote, $codigo, $caducidad) {
                $producto = $this->bloquearProducto($lote->producto_id);
                $actual = Lote::whereKey($lote->id)->lockForUpdate()->firstOrFail();
                $this->validarCaducidades($producto, [['fecha_caducidad' => $caducidad]], 'fecha_caducidad');

                $actual->update(['codigo_lote' => $codigo, 'fecha_caducidad' => $caducidad]);

                return $actual;
            }, self::INTENTOS_TRANSACCION);
        } catch (UniqueConstraintViolationException $e) {
            if ($this->esIndice($e, 'lotes_identidad_unique')) {
                throw ValidationException::withMessages(['codigo_lote' => 'Ya existe otro lote de este producto con ese código y esa caducidad.']);
            }

            throw $e;
        }
    }

    // ---------------------------------------------------------------------
    // Núcleo común (§5.6)
    // ---------------------------------------------------------------------

    /**
     * Token de idempotencia, transacción con reintentos ante interbloqueo y
     * clasificación del 1062 del token (reenvío simultáneo).
     *
     * @param  callable(): Movimiento  $operacion
     */
    private function ejecutar(User $usuario, string $token, TipoMovimiento $tipo, callable $operacion): ResultadoMovimiento
    {
        if ($reenvio = $this->reenvio($token, $usuario, $tipo)) {
            return $reenvio;
        }

        try {
            $movimiento = DB::transaction(fn () => $operacion(), self::INTENTOS_TRANSACCION);
        } catch (UniqueConstraintViolationException $e) {
            if ($this->esIndice($e, 'movimientos_token_unique') && $reenvio = $this->reenvio($token, $usuario, $tipo)) {
                return $reenvio;
            }

            throw $e;
        }

        return new ResultadoMovimiento($movimiento->load('detalles'), false);
    }

    /**
     * Un token ya usado por el mismo usuario y tipo es un reenvío; cualquier
     * otro uso del token es una anomalía y se rechaza.
     */
    private function reenvio(string $token, User $usuario, TipoMovimiento $tipo): ?ResultadoMovimiento
    {
        if (! Str::isUuid($token)) {
            throw ValidationException::withMessages(['token' => 'El formulario no es válido. Recarga la página.']);
        }

        $existente = Movimiento::where('token', $token)->first();

        if ($existente === null) {
            return null;
        }

        if ($existente->usuario_id !== $usuario->id || $existente->tipo !== $tipo) {
            throw ValidationException::withMessages(['token' => 'Este formulario ya se usó en otra operación. Recarga la página.']);
        }

        return new ResultadoMovimiento($existente->load('detalles'), true);
    }

    /**
     * FOR SHARE: nadie cambia controla_caducidad durante la operación
     * (CatalogoService bloquea el producto con FOR UPDATE).
     */
    private function bloquearProducto(int $productoId): Producto
    {
        return Producto::whereKey($productoId)->sharedLock()->firstOrFail();
    }

    /**
     * §5.6: bloqueo de los lotes afectados en orden ascendente de id. Devuelve
     * los lotes releídos tras el bloqueo, solo los del producto.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Lote> Por id.
     */
    private function bloquearLotes(Producto $producto, array $ids): Collection
    {
        return Lote::whereIn('id', array_values(array_unique($ids)))
            ->where('producto_id', $producto->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * Presentaciones del producto leídas con FOR SHARE, para que nadie cambie su
     * factor durante la operación.
     *
     * @param  array<string, int>  $porCampo  Clave del error => presentacion_id.
     * @return Collection<int, Presentacion> Por id.
     */
    private function leerPresentaciones(Producto $producto, array $porCampo, bool $exigirActivas = true): Collection
    {
        $presentaciones = Presentacion::whereIn('id', array_values(array_unique($porCampo)))
            ->where('producto_id', $producto->id)
            ->orderBy('id')
            ->sharedLock()
            ->get()
            ->keyBy('id');

        $errores = [];

        foreach ($porCampo as $campo => $id) {
            $presentacion = $presentaciones->get($id);

            if ($presentacion === null) {
                $errores[$campo] = 'La presentación no pertenece a este producto.';
            } elseif ($exigirActivas && ! $presentacion->activo) {
                $errores[$campo] = 'La presentación está desactivada.';
            }
        }

        $this->lanzarSiHayErrores($errores);

        return $presentaciones;
    }

    /**
     * §5.6 y regla 13: ninguna combinación de lote y presentación queda negativa.
     * Se agrupan las variaciones por par y se comparan con la existencia actual,
     * consultada después del bloqueo de los lotes (READ COMMITTED).
     *
     * @param  list<array{lote_id: int, presentacion_id: int, cantidad: int, campo: string}>  $detalles
     * @param  Collection<int, Lote>  $lotes
     * @param  Collection<int, Presentacion>  $presentaciones
     */
    private function validarSinNegativos(array $detalles, Collection $lotes, Collection $presentaciones): void
    {
        $variaciones = [];
        $campos = [];

        foreach ($detalles as $detalle) {
            $clave = $detalle['lote_id'].'|'.$detalle['presentacion_id'];
            $variaciones[$clave] = ($variaciones[$clave] ?? 0) + $detalle['cantidad'];
            $campos[$clave] ??= $detalle['campo'];
        }

        $negativas = array_filter($variaciones, fn (int $variacion) => $variacion < 0);

        if ($negativas === []) {
            return;
        }

        $saldos = $this->saldos(array_map(fn (string $clave) => array_map('intval', explode('|', $clave)), array_keys($negativas)));
        $errores = [];

        foreach ($negativas as $clave => $variacion) {
            $saldo = $saldos[$clave] ?? 0;

            if ($saldo + $variacion < 0) {
                [$loteId, $presentacionId] = explode('|', $clave);
                $errores[$campos[$clave]] ??= "Solo hay {$saldo} {$presentaciones[$presentacionId]->nombre} en el lote {$lotes[$loteId]->etiqueta()}.";
            }
        }

        $this->lanzarSiHayErrores($errores);
    }

    /**
     * Existencia actual de cada par (lote, presentación).
     *
     * @param  list<array{0: int, 1: int}>  $pares
     * @return array<string, int> "lote_id|presentacion_id" => existencia.
     */
    private function saldos(array $pares): array
    {
        if ($pares === []) {
            return [];
        }

        return DB::table('detalles_movimiento')
            ->where(function ($query) use ($pares) {
                foreach ($pares as [$loteId, $presentacionId]) {
                    $query->orWhere(fn ($q) => $q->where('lote_id', $loteId)->where('presentacion_id', $presentacionId));
                }
            })
            ->selectRaw('lote_id, presentacion_id, SUM(cantidad) AS cantidad')
            ->groupBy('lote_id', 'presentacion_id')
            ->get()
            ->mapWithKeys(fn (object $fila) => [$fila->lote_id.'|'.$fila->presentacion_id => (int) $fila->cantidad])
            ->all();
    }

    /**
     * @param  list<array{lote_id: int, presentacion_id: int, cantidad: int, factor: int, costo_unitario?: string|null}>  $detalles
     */
    private function guardar(
        TipoMovimiento $tipo,
        ?MotivoMovimiento $motivo,
        User $usuario,
        string $token,
        CarbonInterface $fecha,
        ?string $observacion,
        Producto $producto,
        array $detalles,
        ?Movimiento $revierte = null,
    ): Movimiento {
        $movimiento = Movimiento::create([
            'tipo' => $tipo,
            'motivo' => $motivo,
            'fecha' => $fecha,
            'usuario_id' => $usuario->id,
            'observacion' => blank($observacion) ? null : $observacion,
            'revierte_movimiento_id' => $revierte?->id,
            'token' => $token,
        ]);

        foreach ($detalles as $detalle) {
            $movimiento->detalles()->create([
                'producto_id' => $producto->id,
                'lote_id' => $detalle['lote_id'],
                'presentacion_id' => $detalle['presentacion_id'],
                'cantidad' => $detalle['cantidad'],
                // §5.2: copia del factor en el momento de la operación.
                'factor' => $detalle['factor'],
                'costo_unitario' => $detalle['costo_unitario'] ?? null,
            ]);
        }

        return $movimiento;
    }

    // ---------------------------------------------------------------------
    // Validaciones de datos
    // ---------------------------------------------------------------------

    private function validarCabecera(
        TipoMovimiento $tipo,
        ?MotivoMovimiento $motivo,
        CarbonInterface $fecha,
        ?string $observacion,
        bool $observacionObligatoria = false,
    ): void {
        $errores = [];

        if ($motivo !== null && ! in_array($motivo, MotivoMovimiento::paraTipo($tipo), true)) {
            $errores['motivo'] = 'El motivo no corresponde a este tipo de movimiento.';
        }

        // §5.5: la fecha puede ser pasada (registro posterior), nunca futura.
        if ($fecha->isFuture()) {
            $errores['fecha'] = 'La fecha no puede ser futura.';
        }

        if (blank($observacion) && $observacionObligatoria) {
            $errores['observacion'] = 'La observación es obligatoria.';
        } elseif (blank($observacion) && $motivo === MotivoMovimiento::Otro) {
            $errores['observacion'] = 'Con "Otro motivo" la observación es obligatoria.';
        }

        $this->lanzarSiHayErrores($errores);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{presentacion_id: int, cantidad: int, codigo_lote: string, fecha_caducidad: string|null, costo_unitario: string|null}>
     */
    private function normalizarLineasEntrada(array $lineas): array
    {
        if ($lineas === []) {
            throw ValidationException::withMessages(['lineas' => 'Añade al menos una línea.']);
        }

        $errores = [];
        $normalizadas = [];

        foreach (array_values($lineas) as $i => $linea) {
            $cantidad = filter_var($linea['cantidad'] ?? null, FILTER_VALIDATE_INT);

            if ($cantidad === false || $cantidad < 1) {
                $errores["lineas.$i.cantidad"] = 'La cantidad debe ser un número entero mayor que cero.';
            }

            $costo = $linea['costo_unitario'] ?? null;
            $costo = blank($costo) ? null : (string) $costo;

            if ($costo !== null && (! is_numeric($costo) || (float) $costo < 0)) {
                $errores["lineas.$i.costo_unitario"] = 'El costo no puede ser negativo.';
            }

            $caducidad = blank($linea['fecha_caducidad'] ?? null) ? null : (string) $linea['fecha_caducidad'];
            $codigo = Str::squish((string) ($linea['codigo_lote'] ?? ''));

            if ($error = $this->errorFecha($caducidad)) {
                $errores["lineas.$i.fecha_caducidad"] = $error;
            }

            if ($error = $this->errorCodigoLote($codigo)) {
                $errores["lineas.$i.codigo_lote"] = $error;
            }

            $normalizadas[] = [
                'presentacion_id' => (int) ($linea['presentacion_id'] ?? 0),
                'cantidad' => (int) $cantidad,
                'codigo_lote' => $codigo,
                'fecha_caducidad' => $caducidad,
                'costo_unitario' => $costo,
            ];
        }

        $this->lanzarSiHayErrores($errores);

        return $normalizadas;
    }

    /**
     * Líneas que se refieren a un lote existente: lote_id, presentacion_id y un
     * entero en $campoCantidad (≥ $minimo).
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{lote_id: int, presentacion_id: int, cantidad: int, contado: int}>
     */
    private function normalizarLineasConLote(array $lineas, string $campoCantidad, int $minimo, string $prefijo = 'lineas'): array
    {
        if ($lineas === []) {
            throw ValidationException::withMessages([$prefijo => 'Añade al menos una línea.']);
        }

        $errores = [];
        $normalizadas = [];

        foreach (array_values($lineas) as $i => $linea) {
            $cantidad = filter_var($linea[$campoCantidad] ?? null, FILTER_VALIDATE_INT);

            if ($cantidad === false || $cantidad < $minimo) {
                $errores["$prefijo.$i.$campoCantidad"] = $minimo > 0
                    ? 'La cantidad debe ser un número entero mayor que cero.'
                    : 'La cantidad contada debe ser un número entero, cero o mayor.';
            }

            if (empty($linea['lote_id'])) {
                $errores["$prefijo.$i.lote_id"] = 'Elige el lote.';
            }

            $normalizadas[] = [
                'lote_id' => (int) ($linea['lote_id'] ?? 0),
                'presentacion_id' => (int) ($linea['presentacion_id'] ?? 0),
                $campoCantidad => (int) $cantidad,
            ];
        }

        $this->lanzarSiHayErrores($errores);

        return $normalizadas;
    }

    /**
     * @param  list<array{presentacion_id: int}>  $lineas
     * @return array<string, int>
     */
    private function camposPresentacion(array $lineas, string $prefijo = 'lineas'): array
    {
        $campos = [];

        foreach ($lineas as $i => $linea) {
            $campos["$prefijo.$i.presentacion_id"] = $linea['presentacion_id'];
        }

        return $campos;
    }

    /**
     * RF-01 y RF-03: la caducidad es obligatoria si el producto la controla y no
     * se admite si no la controla.
     *
     * @param  list<array{fecha_caducidad: string|null}>  $lineas
     */
    private function validarCaducidades(Producto $producto, array $lineas, ?string $campoUnico = null): void
    {
        $errores = [];

        foreach ($lineas as $i => $linea) {
            $campo = $campoUnico ?? "lineas.$i.fecha_caducidad";

            if ($producto->controla_caducidad && $linea['fecha_caducidad'] === null) {
                $errores[$campo] = 'Este producto controla caducidad: indica la fecha.';
            } elseif (! $producto->controla_caducidad && $linea['fecha_caducidad'] !== null) {
                $errores[$campo] = 'Este producto no controla caducidad.';
            }
        }

        $this->lanzarSiHayErrores($errores);
    }

    private function errorFecha(?string $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        $leida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $leida === false || $leida->format('Y-m-d') !== $fecha ? 'La fecha de caducidad no es válida.' : null;
    }

    private function errorCodigoLote(string $codigo): ?string
    {
        return mb_strlen($codigo) > 50 ? 'El código de lote admite hasta 50 caracteres.' : null;
    }

    /**
     * @param  array<string, string>  $errores
     */
    private function lanzarSiHayErrores(array $errores): void
    {
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    // ---------------------------------------------------------------------
    // Lotes de las entradas
    // ---------------------------------------------------------------------

    /**
     * RF-03: la misma combinación de producto, código y caducidad es el mismo
     * lote; si no existe, se crea aquí, dentro de la entrada. Si otro usuario lo
     * crea a la vez, el índice único lo detecta y se reutiliza (§5.6).
     *
     * @param  list<array{codigo_lote: string, fecha_caducidad: string|null}>  $lineas
     * @return array<int, Lote> Lote de cada línea, por índice de línea.
     */
    private function obtenerLotes(Producto $producto, array $lineas): array
    {
        $porClave = [];
        $lotes = [];

        foreach ($lineas as $i => $linea) {
            $clave = $linea['codigo_lote'].'|'.$linea['fecha_caducidad'];
            $porClave[$clave] ??= $this->obtenerLote($producto, $linea['codigo_lote'], $linea['fecha_caducidad']);
            $lotes[$i] = $porClave[$clave];
        }

        return $lotes;
    }

    private function obtenerLote(Producto $producto, string $codigo, ?string $caducidad): Lote
    {
        $buscar = fn () => Lote::where('producto_id', $producto->id)
            ->where('codigo_lote', $codigo)
            ->where('caducidad_clave', $caducidad ?? '9999-12-31')
            ->first();

        if ($lote = $buscar()) {
            return $lote;
        }

        try {
            $lote = new Lote(['codigo_lote' => $codigo, 'fecha_caducidad' => $caducidad]);
            $lote->producto()->associate($producto);
            $lote->save();

            return $lote;
        } catch (UniqueConstraintViolationException $e) {
            if ($this->esIndice($e, 'lotes_identidad_unique') && $existente = $buscar()) {
                return $existente;
            }

            throw $e;
        }
    }

    /**
     * MySQL nombra la clave en el mensaje: "Duplicate entry '…' for key 'tabla.indice'".
     */
    private function esIndice(UniqueConstraintViolationException $e, string $indice): bool
    {
        return str_contains($e->getMessage(), "'$indice'") || str_contains($e->getMessage(), ".$indice'");
    }
}
