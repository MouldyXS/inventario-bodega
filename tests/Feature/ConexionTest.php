<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Requerimientos §5.1 y §5.6: configuración de la conexión de la aplicación.
 */
class ConexionTest extends TestCase
{
    /** Caso 27. */
    public function test_la_conexion_funciona_en_read_committed(): void
    {
        $this->assertSame('READ-COMMITTED', DB::selectOne('SELECT @@transaction_isolation AS nivel')->nivel);
    }

    public function test_la_sesion_de_mysql_usa_la_hora_de_ecuador(): void
    {
        $this->assertSame('-05:00', DB::selectOne('SELECT @@session.time_zone AS zona')->zona);
    }

    public function test_la_aplicacion_usa_la_zona_horaria_de_guayaquil(): void
    {
        $this->assertSame('America/Guayaquil', config('app.timezone'));
        $this->assertSame('America/Guayaquil', now()->tzName);
    }

    public function test_el_modo_estricto_de_mysql_esta_activo(): void
    {
        $this->assertStringContainsString('STRICT_TRANS_TABLES', DB::selectOne('SELECT @@session.sql_mode AS modo')->modo);
    }
}
