<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Valores de configuración del sistema (Requerimientos §5.2). Se leen y
 * cambian mediante ConfiguracionService.
 */
#[Table('configuracion', key: 'clave', keyType: 'string', incrementing: false)]
#[Fillable(['clave', 'valor'])]
class Configuracion extends Model
{
    public const CREATED_AT = null;
}
