<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Copias de seguridad (Requerimientos RNF-05, decisión K3)
    |--------------------------------------------------------------------------
    |
    | scripts/respaldo.ps1 escribe aquí el resultado de cada copia. La aplicación
    | solo lo lee, para avisar al administrador si hace demasiado que no hay una
    | copia correcta.
    |
    */

    'respaldo' => [
        'archivo_estado' => env('RESPALDO_ESTADO', storage_path('app/respaldo-estado.json')),
        'horas_aviso' => 36,
    ],

];
