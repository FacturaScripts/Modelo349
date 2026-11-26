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

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Lib\Clave349;
use PHPUnit\Framework\TestCase;

/**
 * Comprueba que la clave del Modelo 349 se deduce de la operación de la factura.
 *
 * @author Esteban Sánchez Martínez <esteban@factura.city>
 */
final class Clave349Test extends TestCase
{
    public function testClavesValidasSonLasDelDisenoDeLaAeat(): void
    {
        foreach (['A', 'C', 'D', 'E', 'H', 'I', 'M', 'R', 'S', 'T'] as $clave) {
            $this->assertTrue(Clave349::esValida($clave), $clave);
        }

        foreach ([null, '', 'e', 'X', 'EE'] as $clave) {
            $this->assertFalse(Clave349::esValida($clave), (string)$clave);
        }
    }

    public function testComprasDeBienesSonClaveA(): void
    {
        $this->assertSame('A', Clave349::porOperacion(false, InvoiceOperation::INTRA_COMMUNITY));
    }

    public function testComprasDeServiciosSonClaveI(): void
    {
        $this->assertSame('I', Clave349::porOperacion(false, InvoiceOperation::INTRA_COMMUNITY_SERVICES));
    }

    public function testOperacionesDeclarablesSoloSonLasIntracomunitarias(): void
    {
        $core = [InvoiceOperation::INTRA_COMMUNITY, InvoiceOperation::INTRA_COMMUNITY_SERVICES];
        $plugin = array_keys(Clave349::OPERACIONES_PLUGIN);
        $this->assertSame(array_merge($core, $plugin), Clave349::operaciones(true));
        $this->assertSame($core, Clave349::operaciones(false));
    }

    public function testOperacionesDelPluginSoloSonDeVentas(): void
    {
        $esperado = [
            Clave349::OPERACION_TRIANGULAR => 'T',
            Clave349::OPERACION_POST_IMPORTACION => 'M',
            Clave349::OPERACION_POST_IMPORTACION_REPRESENTANTE => 'H',
            Clave349::OPERACION_CONSIGNA => 'R',
            Clave349::OPERACION_CONSIGNA_DEVOLUCION => 'D',
        ];
        foreach ($esperado as $operacion => $clave) {
            $this->assertSame($clave, Clave349::porOperacion(true, $operacion), $operacion);
            $this->assertNull(Clave349::porOperacion(false, $operacion), $operacion);
            $this->assertLessThanOrEqual(InvoiceOperation::MAX_KEY_LENGTH, strlen($operacion), $operacion);
        }
    }

    public function testRegisterAnadeLasOperacionesALasVentas(): void
    {
        Clave349::register();
        foreach (Clave349::OPERACIONES_PLUGIN as $operacion => $traduccion) {
            $this->assertSame($traduccion, InvoiceOperation::get($operacion), $operacion);
            $this->assertArrayHasKey($operacion, InvoiceOperation::allForSales());
            $this->assertArrayNotHasKey($operacion, InvoiceOperation::allForPurchases());
        }
    }

    public function testOperacionesNoIntracomunitariasNoTienenClave(): void
    {
        foreach ([true, false] as $esVenta) {
            $this->assertNull(Clave349::porOperacion($esVenta, null));
            $this->assertNull(Clave349::porOperacion($esVenta, ''));
            $this->assertNull(Clave349::porOperacion($esVenta, InvoiceOperation::EXPORT));
            $this->assertNull(Clave349::porOperacion($esVenta, InvoiceOperation::REVERSE_CHARGE));
        }
    }

    public function testVentasDeBienesSonClaveE(): void
    {
        $this->assertSame('E', Clave349::porOperacion(true, InvoiceOperation::INTRA_COMMUNITY));
    }

    public function testVentasDeServiciosSonClaveS(): void
    {
        $this->assertSame('S', Clave349::porOperacion(true, InvoiceOperation::INTRA_COMMUNITY_SERVICES));
    }
}
