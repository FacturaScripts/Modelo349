<?php
/**
 * This file is part of Modelo349 plugin for FacturaScripts
 * Copyright (C) 2026 Esteban Sánchez Martínez <esteban@factura.city>
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
    public function testVentasDeBienesSonClaveE(): void
    {
        $this->assertSame('E', Clave349::porOperacion(true, InvoiceOperation::INTRA_COMMUNITY));
    }

    public function testVentasDeServiciosSonClaveS(): void
    {
        $this->assertSame('S', Clave349::porOperacion(true, InvoiceOperation::INTRA_COMMUNITY_SERVICES));
    }

    public function testComprasDeBienesSonClaveA(): void
    {
        $this->assertSame('A', Clave349::porOperacion(false, InvoiceOperation::INTRA_COMMUNITY));
    }

    public function testComprasDeServiciosSonClaveI(): void
    {
        $this->assertSame('I', Clave349::porOperacion(false, InvoiceOperation::INTRA_COMMUNITY_SERVICES));
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

    public function testOperacionesDeclarablesSoloSonLasIntracomunitarias(): void
    {
        $esperado = [InvoiceOperation::INTRA_COMMUNITY, InvoiceOperation::INTRA_COMMUNITY_SERVICES];
        $this->assertSame($esperado, Clave349::operaciones(true));
        $this->assertSame($esperado, Clave349::operaciones(false));
    }
}
