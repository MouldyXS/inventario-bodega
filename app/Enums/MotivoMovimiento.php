<?php

namespace App\Enums;

/**
 * Motivos como lista cerrada (Requerimientos RF-06, §5.2). Las combinaciones
 * válidas con cada tipo también las comprueba un CHECK en la base de datos.
 */
enum MotivoMovimiento: string
{
    case Compra = 'compra';
    case CargaInicial = 'carga_inicial';
    case VentaEntrega = 'venta_entrega';
    case Danado = 'danado';
    case Vencido = 'vencido';
    case DevolucionProveedor = 'devolucion_proveedor';
    case ConteoFisico = 'conteo_fisico';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Compra => 'Compra',
            self::CargaInicial => 'Carga inicial',
            self::VentaEntrega => 'Venta o entrega',
            self::Danado => 'Producto dañado',
            self::Vencido => 'Producto vencido',
            self::DevolucionProveedor => 'Devolución al proveedor',
            self::ConteoFisico => 'Conteo físico',
            self::Otro => 'Otro motivo',
        };
    }

    /**
     * @return list<self>
     */
    public static function paraTipo(TipoMovimiento $tipo): array
    {
        return match ($tipo) {
            TipoMovimiento::Entrada => [self::Compra, self::CargaInicial, self::Otro],
            TipoMovimiento::Salida => [self::VentaEntrega, self::Danado, self::Vencido, self::DevolucionProveedor, self::Otro],
            TipoMovimiento::Ajuste => [self::ConteoFisico, self::Danado, self::Otro],
            TipoMovimiento::Conversion, TipoMovimiento::Reversion => [],
        };
    }
}
