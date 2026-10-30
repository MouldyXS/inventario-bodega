<?php

namespace App\Services;

use App\Models\Movimiento;

final readonly class ResultadoMovimiento
{
    /**
     * @param  bool  $reenvio  El formulario ya se había registrado: no se registró nada nuevo (§5.6).
     */
    public function __construct(
        public Movimiento $movimiento,
        public bool $reenvio,
    ) {}
}
