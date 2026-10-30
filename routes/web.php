<?php

use App\Http\Controllers\AjusteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CaducidadController;
use App\Http\Controllers\CargaInicialController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CodigoBarrasController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConversionController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PresentacionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\ReversionController;
use App\Http\Controllers\SalidaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', PanelController::class)->name('inicio');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Entradas, salidas, conversiones, inventario, caducidad e historial: cualquier usuario autenticado (§2.3).
    Route::get('/caducidad', [CaducidadController::class, 'index'])->name('caducidad.index');
    Route::get('/recepciones/crear', [RecepcionController::class, 'create'])->name('recepciones.create');
    Route::post('/recepciones', [RecepcionController::class, 'store'])->name('recepciones.store');
    Route::get('/salidas/crear', [SalidaController::class, 'create'])->name('salidas.create');
    Route::post('/salidas', [SalidaController::class, 'store'])->name('salidas.store');
    Route::get('/lotes/{lote}/convertir', [ConversionController::class, 'create'])->name('conversiones.create');
    Route::post('/lotes/{lote}/convertir', [ConversionController::class, 'store'])->name('conversiones.store');
    Route::get('/movimientos/{movimiento}', [MovimientoController::class, 'show'])->name('movimientos.show');
    Route::get('/historial', [HistorialController::class, 'index'])->name('historial.index');
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/{producto}', [InventarioController::class, 'show'])->name('inventario.show');

    // Ajustes y corrección de lotes (§2.3): administrador o puede_ajustar_inventario.
    Route::middleware('can:ajustar-inventario')->group(function () {
        Route::get('/inventario/{producto}/ajuste', [AjusteController::class, 'create'])->name('ajustes.create');
        Route::post('/inventario/{producto}/ajuste', [AjusteController::class, 'store'])->name('ajustes.store');
        Route::get('/lotes/{lote}/editar', [LoteController::class, 'edit'])->name('lotes.edit');
        Route::put('/lotes/{lote}', [LoteController::class, 'update'])->name('lotes.update');
    });

    // Reversiones (§2.3): administrador o puede_revertir.
    Route::post('/movimientos/{movimiento}/revertir', [ReversionController::class, 'store'])
        ->middleware('can:revertir')->name('reversiones.store');

    // Cada usuario cambia su propia contraseña (§9.10).
    Route::get('/mi-cuenta', [CuentaController::class, 'edit'])->name('cuenta.edit');
    Route::put('/mi-cuenta', [CuentaController::class, 'update'])->name('cuenta.update');

    // Configuración y usuarios (§9.10): solo el administrador.
    Route::middleware('can:administrar')->group(function () {
        Route::get('/configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion.edit');
        Route::put('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');

        Route::get('/carga-inicial', [CargaInicialController::class, 'create'])->name('carga-inicial.create');
        Route::get('/carga-inicial/plantilla', [CargaInicialController::class, 'plantilla'])->name('carga-inicial.plantilla');
        Route::post('/carga-inicial/revisar', [CargaInicialController::class, 'revisar'])->name('carga-inicial.revisar');
        Route::post('/carga-inicial', [CargaInicialController::class, 'store'])->name('carga-inicial.store');

        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    });

    // Catálogo (§2.3): cualquiera consulta; crear y editar requiere el permiso.
    // Las rutas de escritura van primero para que productos/crear no se tome como {producto}.
    Route::middleware('can:gestionar-catalogo')->group(function () {
        Route::resource('categorias', CategoriaController::class)
            ->only(['create', 'store', 'edit', 'update'])
            ->parameters(['categorias' => 'categoria']);
        Route::resource('productos', ProductoController::class)
            ->only(['create', 'store', 'edit', 'update'])
            ->parameters(['productos' => 'producto']);

        Route::post('/productos/{producto}/presentaciones', [PresentacionController::class, 'store'])->name('presentaciones.store');
        Route::get('/presentaciones/{presentacion}/editar', [PresentacionController::class, 'edit'])->name('presentaciones.edit');
        Route::put('/presentaciones/{presentacion}', [PresentacionController::class, 'update'])->name('presentaciones.update');

        Route::post('/presentaciones/{presentacion}/codigos', [CodigoBarrasController::class, 'store'])->name('codigos.store');
        Route::delete('/codigos/{codigo}', [CodigoBarrasController::class, 'destroy'])->name('codigos.destroy');
    });

    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::get('/productos/{producto}', [ProductoController::class, 'show'])->name('productos.show');
});
