<?php
/**
 * This file is part of Modelo349 plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\Modelo349\Lib;

use FacturaScripts\Core\Lib\InvoiceOperation;

/**
 * Determina la clave de operación del Modelo 349 a partir del campo "Operación" de la factura.
 *
 * Solo las facturas marcadas como intracomunitarias (bienes o servicios) forman parte del modelo.
 * El resto, aunque el tercero sea de la UE, no tiene clave y queda fuera de la declaración.
 *
 * @author Esteban Sánchez Martínez <esteban@factura.city>
 */
class Clave349
{
    /** @var string Adquisición intracomunitaria de bienes (compras). */
    const ADQUISICION_BIENES = 'A';

    /** @var string Adquisición intracomunitaria de servicios (compras). */
    const ADQUISICION_SERVICIOS = 'I';

    /** @var string Entrega intracomunitaria de bienes (ventas). */
    const ENTREGA_BIENES = 'E';

    /** @var string Prestación intracomunitaria de servicios (ventas). */
    const PRESTACION_SERVICIOS = 'S';

    /**
     * Claves por operación de la factura para las ventas.
     *
     * @var array
     */
    const VENTAS = [
        InvoiceOperation::INTRA_COMMUNITY => self::ENTREGA_BIENES,
        InvoiceOperation::INTRA_COMMUNITY_SERVICES => self::PRESTACION_SERVICIOS,
    ];

    /**
     * Claves por operación de la factura para las compras.
     *
     * @var array
     */
    const COMPRAS = [
        InvoiceOperation::INTRA_COMMUNITY => self::ADQUISICION_BIENES,
        InvoiceOperation::INTRA_COMMUNITY_SERVICES => self::ADQUISICION_SERVICIOS,
    ];

    /**
     * Devuelve la clave de operación que corresponde a la operación de una factura,
     * o null si esa operación no se declara en el Modelo 349.
     *
     * @param bool $esVenta true para facturas de cliente, false para facturas de proveedor.
     * @param string|null $operacion Valor del campo operacion de la factura.
     * @return string|null
     */
    public static function porOperacion(bool $esVenta, ?string $operacion): ?string
    {
        $mapa = $esVenta ? self::VENTAS : self::COMPRAS;
        return $mapa[$operacion ?? ''] ?? null;
    }

    /**
     * Devuelve las operaciones de factura que se declaran en el Modelo 349.
     *
     * @param bool $esVenta true para facturas de cliente, false para facturas de proveedor.
     * @return array
     */
    public static function operaciones(bool $esVenta): array
    {
        return array_keys($esVenta ? self::VENTAS : self::COMPRAS);
    }
}
