<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ConfiguracionService;
use App\Services\ExistenciasService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);
        Paginator::useBootstrapFive();

        // Requerimientos §2.3: el administrador tiene implícitamente los tres
        // permisos especiales; la gestión de usuarios y la configuración son
        // exclusivas del administrador.
        Gate::define('administrar', fn (User $usuario) => $usuario->esAdmin());

        Gate::define('gestionar-catalogo', fn (User $usuario) => $usuario->esAdmin() || $usuario->puede_gestionar_catalogo);

        Gate::define('ajustar-inventario', fn (User $usuario) => $usuario->esAdmin() || $usuario->puede_ajustar_inventario);

        Gate::define('revertir', fn (User $usuario) => $usuario->esAdmin() || $usuario->puede_revertir);

        // Decisión H2: contador de lotes vencidos o próximos a caducar en el menú.
        View::composer('layouts.app', function ($vista) {
            if (Auth::check()) {
                $resumen = app(ExistenciasService::class)->resumenCaducidad(app(ConfiguracionService::class)->diasAlertaCaducidad());
                $vista->with('alertasCaducidad', $resumen['vencidos'] + $resumen['proximos']);
            }
        });
    }
}
