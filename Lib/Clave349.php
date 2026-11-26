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
 * Solo las facturas con una operación intracomunitaria forman parte del modelo: las del core (bienes y
 * servicios) y las que añade este plugin para las ventas con clave T, M, H, R y D (ver register()).
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

    /**
     * Claves por operación de la factura para las compras.
     *
     * @var array
     */
    const COMPRAS = [
        InvoiceOperation::INTRA_COMMUNITY => self::ADQUISICION_BIENES,
        InvoiceOperation::INTRA_COMMUNITY_SERVICES => self::ADQUISICION_SERVICIOS,
    ];

    /** @var string Devolución de bienes enviados en consigna a otro estado miembro (ventas). */
    const DEVOLUCION_CONSIGNA = 'D';

    /** @var string Entrega intracomunitaria de bienes (ventas). */
    const ENTREGA_BIENES = 'E';

    /** @var string Entrega subsiguiente a una importación exenta, por representante fiscal (ventas). */
    const ENTREGA_POST_IMPORTACION_REPRESENTANTE = 'H';

    /** @var string Entrega intracomunitaria subsiguiente a una importación exenta (ventas). */
    const ENTREGA_POST_IMPORTACION = 'M';

    /** @var string Operación del plugin: devolución de bienes enviados en consigna (clave D). */
    const OPERACION_CONSIGNA_DEVOLUCION = 'intracom-consigna-devol';

    /** @var string Operación del plugin: transferencia de bienes en consigna (clave R). */
    const OPERACION_CONSIGNA = 'intracom-consigna';

    /** @var string Operación del plugin: entrega subsiguiente a una importación exenta (clave M). */
    const OPERACION_POST_IMPORTACION = 'intracom-post-importacion';

    /** @var string Operación del plugin: entrega subsiguiente a una importación exenta por representante fiscal (clave H). */
    const OPERACION_POST_IMPORTACION_REPRESENTANTE = 'intracom-post-import-repr';

    /** @var string Operación del plugin: entrega en una operación triangular (clave T). */
    const OPERACION_TRIANGULAR = 'intracom-triangular';

    /**
     * Operaciones que añade el plugin al campo "Operación" de las facturas de venta, con su clave de traducción.
     *
     * @var array
     */
    const OPERACIONES_PLUGIN = [
        self::OPERACION_TRIANGULAR => 'intra-community-triangular',
        self::OPERACION_POST_IMPORTACION => 'intra-community-post-import',
        self::OPERACION_POST_IMPORTACION_REPRESENTANTE => 'intra-community-post-import-tax-rep',
        self::OPERACION_CONSIGNA => 'intra-community-consignment',
        self::OPERACION_CONSIGNA_DEVOLUCION => 'intra-community-consignment-return',
    ];

    /** @var string Prestación intracomunitaria de servicios (ventas). */
    const PRESTACION_SERVICIOS = 'S';

    /** @var string Transferencia de bienes en el marco de un acuerdo de venta en consigna (ventas). */
    const TRANSFERENCIA_CONSIGNA = 'R';

    /** @var string Entrega en otro estado miembro dentro de una operación triangular (ventas). */
    const TRIANGULAR = 'T';

    /**
     * Todas las claves de operación del diseño de registro de la AEAT. El plugin calcula todas salvo la C
     * (sustitución del destinatario en consigna), que necesita el NIF y el nombre del sustituto.
     *
     * @var string[]
     */
    const TODAS = ['A', 'C', 'D', 'E', 'H', 'I', 'M', 'R', 'S', 'T'];

    /**
     * Claves por operación de la factura para las ventas.
     *
     * @var array
     */
    const VENTAS = [
        InvoiceOperation::INTRA_COMMUNITY => self::ENTREGA_BIENES,
        InvoiceOperation::INTRA_COMMUNITY_SERVICES => self::PRESTACION_SERVICIOS,
        self::OPERACION_TRIANGULAR => self::TRIANGULAR,
        self::OPERACION_POST_IMPORTACION => self::ENTREGA_POST_IMPORTACION,
        self::OPERACION_POST_IMPORTACION_REPRESENTANTE => self::ENTREGA_POST_IMPORTACION_REPRESENTANTE,
        self::OPERACION_CONSIGNA => self::TRANSFERENCIA_CONSIGNA,
        self::OPERACION_CONSIGNA_DEVOLUCION => self::DEVOLUCION_CONSIGNA,
    ];

    /**
     * Indica si una clave es una de las claves de operación del diseño de registro de la AEAT.
     *
     * @param string|null $clave Clave de operación.
     * @return bool
     */
    public static function esValida(?string $clave): bool
    {
        return in_array($clave, self::TODAS, true);
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

    /**
     * Añade al campo "Operación" de las facturas de venta las operaciones del plugin, para que el usuario
     * pueda marcar las ventas con clave T, M, H, R y D. Solo cambia las opciones del campo: el IVA de
     * esas facturas no se calcula automáticamente.
     */
    public static function register(): void
    {
        foreach (self::OPERACIONES_PLUGIN as $operacion => $traduccion) {
            InvoiceOperation::add($operacion, $traduccion, InvoiceOperation::TYPE_SALE);
        }
    }

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
}
