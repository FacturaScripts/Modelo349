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

use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Lib\Clave349;
use FacturaScripts\Dinamic\Lib\Modelo349;
use FacturaScripts\Dinamic\Lib\Modelo349Api;
use FacturaScripts\Dinamic\Lib\Modelo349File;
use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\PagoCliente;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas del endpoint de la API del Modelo 349 (Lib/Modelo349Api) y del cálculo por empresa (Lib/Modelo349).
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo349ApiTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    /**
     * Prepara la configuración por defecto y las tablas de recibos y pagos.
     */
    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();

        // creamos las tablas de recibos y pagos antes de guardar facturas: al guardarlas, el core genera los
        // recibos dentro de una transacción y no se pueden crear tablas dentro de ella
        new ReciboCliente();
        new PagoCliente();
    }

    /**
     * Comprueba los errores de empresa, ejercicio y serie.
     */
    public function testErrors(): void
    {
        $result = Modelo349Api::build(['codejercicio' => 'ZZZZ', 'periodo' => 'T1']);
        $this->assertEquals(404, $result['code']);
        $this->assertEquals('exercise-not-found', $result['body']['message']);

        $result = Modelo349Api::build(['idempresa' => '999999', 'periodo' => 'T1']);
        $this->assertEquals(404, $result['code']);
        $this->assertEquals('company-not-found', $result['body']['message']);

        $result = Modelo349Api::build(['anio' => '1901', 'periodo' => 'T1']);
        $this->assertEquals(404, $result['code']);
        $this->assertEquals('exercise-not-found', $result['body']['message']);

        $result = Modelo349Api::build(['periodo' => 'T1', 'codserie' => 'ZZZ']);
        $this->assertEquals(404, $result['code']);
        $this->assertEquals('serie-not-found', $result['body']['message']);
    }

    /**
     * Comprueba la normalización de periodos y sus errores.
     */
    public function testPeriods(): void
    {
        $this->assertEquals('T1', Modelo349Api::normalizePeriod('1t'));
        $this->assertEquals('T4', Modelo349Api::normalizePeriod(' t4 '));
        $this->assertEquals('03', Modelo349Api::normalizePeriod('3'));
        $this->assertEquals('12', Modelo349Api::normalizePeriod('12'));
        $this->assertEquals('2T', Modelo349Api::internalPeriod('T2'));
        $this->assertEquals('07', Modelo349Api::internalPeriod('07'));

        // periodo de la pantalla: acepta los formatos de la API y usa el de por defecto si no es válido
        $this->assertEquals('1T', Modelo349Api::screenPeriod('T1', '4T'));
        $this->assertEquals('2T', Modelo349Api::screenPeriod('2T', '4T'));
        $this->assertEquals('05', Modelo349Api::screenPeriod('5', '4T'));
        $this->assertEquals('4T', Modelo349Api::screenPeriod('T5', '4T'));
        $this->assertEquals('4T', Modelo349Api::screenPeriod('ANUAL', '4T'));
        $this->assertEquals('4T', Modelo349Api::screenPeriod(null, '4T'));

        $result = Modelo349Api::build([]);
        $this->assertEquals(400, $result['code']);
        $this->assertEquals('missing-period', $result['body']['message']);

        foreach (['ANUAL', '13', 'T5', 'XX'] as $periodo) {
            $result = Modelo349Api::build(['periodo' => $periodo]);
            $this->assertEquals(400, $result['code'], $periodo);
            $this->assertEquals('invalid-period', $result['body']['message'], $periodo);
            $this->assertContains('T1', $result['body']['allowed']);
            $this->assertContains('12', $result['body']['allowed']);
        }

        // un mes y un trimestre válidos, con las fechas del periodo
        [$customer, $contact, $invoice, $status] = $this->createIntraInvoice(100);
        $year = date('Y', strtotime($invoice->fecha));
        $result = Modelo349Api::build(['codejercicio' => $invoice->codejercicio, 'periodo' => '2']);
        $this->assertEquals(200, $result['code']);
        $this->assertEquals('02', $result['body']['periodo']);
        $this->assertEquals($year . '-02-01', $result['body']['fechainicio']);

        $result = Modelo349Api::build(['codejercicio' => $invoice->codejercicio, 'periodo' => '3T']);
        $this->assertEquals(200, $result['code']);
        $this->assertEquals('T3', $result['body']['periodo']);
        $this->assertEquals($year . '-07-01', $result['body']['fechainicio']);
        $this->assertEquals($year . '-09-30', $result['body']['fechafin']);

        $this->deleteIntraInvoice($customer, $contact, $invoice, $status);
    }

    /**
     * Comprueba la respuesta con una entrega intracomunitaria del trimestre actual.
     */
    public function testResponseWithSale(): void
    {
        [$customer, $contact, $invoice, $status] = $this->createIntraInvoice(1000);

        $result = Modelo349Api::build(['codejercicio' => $invoice->codejercicio, 'periodo' => $this->currentQuarter()]);
        $this->assertEquals(200, $result['code']);

        $body = $result['body'];
        $this->assertEquals('349', $body['modelo']);
        $this->assertArrayNotHasKey('modelo_anual', $body);
        $this->assertNull($body['resultado']);
        $this->assertEquals(['codserie' => ''], $body['parametros']);
        $this->assertEquals((int)$invoice->idempresa, $body['empresa']['idempresa']);

        $row = $this->findRow($body['detalle']['ventas'], $customer->cifnif);
        $this->assertNotNull($row, 'el cliente debe aparecer en las ventas');
        $this->assertEquals(['cifnif', 'nombre', 'codpais', 'clave', 'base'], array_keys($row));
        $this->assertEquals('E', $row['clave']);
        $this->assertEquals('DEU', $row['codpais']);
        $this->assertEquals(round($invoice->neto, 2), $row['base']);

        // resumen y casillas coherentes con el detalle
        $numOperators = count($body['detalle']['ventas']) + count($body['detalle']['compras']);
        $this->assertEquals($numOperators, $body['resumen']['num_operadores']);
        $this->assertEquals($numOperators, $body['casillas']['01']);
        $this->assertGreaterThanOrEqual(round($invoice->neto, 2), $body['resumen']['total_entregas']);
        $this->assertEquals(
            round($body['resumen']['total_entregas'] + $body['resumen']['total_servicios_prestados'], 2),
            $body['resumen']['total_ventas']
        );
        $this->assertEquals(
            round($body['resumen']['total_ventas'] + $body['resumen']['total_compras'], 2),
            $body['casillas']['02']
        );

        $this->deleteIntraInvoice($customer, $contact, $invoice, $status);
    }

    /**
     * Una venta con una operación del plugin (triangular) se declara con su clave (T), sale en los totales
     * por clave y no en los de entregas ni servicios, y el TXT la escribe con esa clave.
     */
    public function testTriangularSaleHasKeyT(): void
    {
        [$customer, $contact, $invoice, $status] = $this->createIntraInvoice(500, '', Clave349::OPERACION_TRIANGULAR);

        $result = Modelo349Api::build(['codejercicio' => $invoice->codejercicio, 'periodo' => $this->currentQuarter()]);
        $this->assertEquals(200, $result['code']);

        $body = $result['body'];
        $row = $this->findRow($body['detalle']['ventas'], $customer->cifnif);
        $this->assertNotNull($row, 'la venta triangular debe aparecer en las ventas');
        $this->assertEquals('T', $row['clave']);
        $this->assertEquals(round($invoice->neto, 2), $body['resumen']['totales_por_clave']['T']);
        $this->assertEquals(
            round($body['resumen']['total_ventas'], 2),
            round(array_sum(array_intersect_key($body['resumen']['totales_por_clave'], array_flip(['E', 'S', 'T']))), 2)
        );

        $calc = Modelo349::calculate($invoice->codejercicio, Modelo349Api::internalPeriod($this->currentQuarter()));
        $txt = Modelo349File::generate($calc['year'], '1T', $calc['sales'], [], '', '', '', (int)$invoice->idempresa);
        $found = false;
        foreach (array_filter(explode("\r\n", $txt)) as $line) {
            if ($line[0] === '2' && str_contains($line, substr($customer->cifnif, 2))) {
                $this->assertEquals('T', $line[132]);
                $found = true;
            }
        }
        $this->assertTrue($found, 'el TXT debe llevar el registro del operador triangular');

        $this->deleteIntraInvoice($customer, $contact, $invoice, $status);
    }

    /**
     * Fallo corregido: el 349 sumaba las facturas de todas las empresas y tomaba el código del ejercicio
     * como año. Con dos empresas con facturas en el mismo año, cada una solo ve las suyas, y el año del
     * ejercicio de la segunda empresa (con código 00AA) se obtiene de su fecha de inicio.
     */
    public function testTwoCompaniesSameYear(): void
    {
        // factura de la empresa predeterminada
        [$customer1, $contact1, $invoice1, $status1] = $this->createIntraInvoice(1000);

        // segunda empresa con una factura en la misma fecha
        $company2 = new Empresa();
        $company2->nombre = 'Company 349 ' . mt_rand(1, 9999);
        $company2->nombrecorto = 'C349-' . mt_rand(1, 9999);
        $company2->cifnif = 'B' . mt_rand(10000000, 99999999);
        $this->assertTrue($company2->save(), 'company-cant-save');
        $warehouse = new Almacen();
        $this->assertTrue($warehouse->loadWhereEq('idempresa', $company2->idempresa), 'warehouse-not-found');
        [$customer2, $contact2, $invoice2, $status2] = $this->createIntraInvoice(2000, $warehouse->codalmacen);
        $this->assertEquals($company2->idempresa, $invoice2->idempresa);
        $this->assertNotEquals($invoice1->codejercicio, $invoice2->codejercicio);

        // el cálculo de la pantalla con el ejercicio de la segunda empresa usa su año, no su código
        $year = (int)date('Y', strtotime($invoice2->fecha));
        $calc = Modelo349::calculate($invoice2->codejercicio, Modelo349Api::internalPeriod($this->currentQuarter()));
        $this->assertEquals($year, $calc['year']);
        $this->assertEquals($company2->idempresa, $calc['company']->idempresa);
        $this->assertCount(1, $calc['sales']);
        $this->assertEquals($customer2->cifnif, $calc['sales'][0]['cifnif']);

        // por la API, por empresa y año: cada empresa ve solo su operación
        $periodo = $this->currentQuarter();
        $result1 = Modelo349Api::build(['idempresa' => (string)$invoice1->idempresa, 'anio' => (string)$year, 'periodo' => $periodo]);
        $result2 = Modelo349Api::build(['idempresa' => (string)$company2->idempresa, 'anio' => (string)$year, 'periodo' => $periodo]);
        $this->assertEquals(200, $result1['code']);
        $this->assertEquals(200, $result2['code']);
        $this->assertEquals($invoice2->codejercicio, $result2['body']['ejercicio']['codejercicio']);

        $this->assertNotNull($this->findRow($result1['body']['detalle']['ventas'], $customer1->cifnif));
        $this->assertNull($this->findRow($result1['body']['detalle']['ventas'], $customer2->cifnif));
        $this->assertNotNull($this->findRow($result2['body']['detalle']['ventas'], $customer2->cifnif));
        $this->assertNull($this->findRow($result2['body']['detalle']['ventas'], $customer1->cifnif));
        $this->assertEquals(2000.0, $result2['body']['resumen']['total_entregas']);

        // el TXT usa el NIF de la empresa indicada
        $txt = Modelo349File::generate($year, '1T', $calc['sales'], [], '', '', '', (int)$company2->idempresa);
        $this->assertStringContainsString($company2->cifnif, substr($txt, 0, 20));
        $this->assertEquals(sprintf('%04d', $year), substr($txt, 4, 4));

        // limpiamos
        $this->deleteIntraInvoice($customer2, $contact2, $invoice2, $status2);
        $this->deleteIntraInvoice($customer1, $contact1, $invoice1, $status1);
        $exercise2 = new Ejercicio();
        if ($exercise2->load($invoice2->codejercicio)) {
            $this->assertTrue($exercise2->delete(), 'exercise-cant-delete');
        }
        $this->assertTrue($company2->delete(), 'company-cant-delete');
    }

    /**
     * Crea un cliente alemán con NIF-IVA y una factura intracomunitaria de bienes de hoy, ya no editable.
     *
     * @param float  $amount     precio unitario de la línea.
     * @param string $codalmacen almacén (y por tanto empresa) de la factura; vacío para el predeterminado.
     *
     * @return array [Cliente, Contacto, FacturaCliente, estado editable anterior]
     */
    private function createIntraInvoice(float $amount, string $codalmacen = '', string $operacion = InvoiceOperation::INTRA_COMMUNITY): array
    {
        $customer = new Cliente();
        $customer->cifnif = 'DE' . mt_rand(100000000, 999999999);
        $customer->nombre = 'Cliente 349 ' . mt_rand(1, 99999);
        $customer->razonsocial = 'GmbH 349 ' . mt_rand(1, 99999);
        $customer->tipoidfiscal = 'VAT';
        $this->assertTrue($customer->save(), 'cant-create-customer');

        $contact = new Contacto();
        $this->assertTrue($contact->load($customer->idcontactofact), 'cant-load-contact');
        $contact->codpais = 'DEU';
        $this->assertTrue($contact->save(), 'cant-save-contact');

        $invoice = new FacturaCliente();
        $invoice->setSubject($customer);
        if ($codalmacen) {
            $invoice->codalmacen = $codalmacen;
            $this->assertTrue($invoice->setDate(date('d-m-Y'), $invoice->hora), 'cant-set-date');
        }
        $invoice->operacion = $operacion;
        $this->assertTrue($invoice->save(), 'cant-create-invoice');

        $line = $invoice->getNewLine();
        $line->descripcion = 'Test 349';
        $line->cantidad = 1;
        $line->pvpunitario = $amount;
        $this->assertTrue($line->save(), 'cant-create-line');

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'cant-calculate-invoice');

        // solo se declaran las facturas no editables
        $previous = $invoice->idestado;
        foreach ($invoice->getAvailableStatus() as $status) {
            if (false === $status->editable) {
                $invoice->idestado = $status->idestado;
                break;
            }
        }
        $this->assertTrue($invoice->save(), 'cant-lock-invoice');
        $this->assertFalse($invoice->editable, 'invoice-still-editable');

        return [$customer, $contact, $invoice, $previous];
    }

    /**
     * Devuelve el trimestre actual en formato de la API (T1-T4).
     *
     * @return string
     */
    private function currentQuarter(): string
    {
        return 'T' . (int)ceil((int)date('n') / 3);
    }

    /**
     * Desbloquea y elimina la factura, el contacto y el cliente creados por createIntraInvoice().
     *
     * @param Cliente        $customer cliente.
     * @param Contacto       $contact  contacto de facturación.
     * @param FacturaCliente $invoice  factura.
     * @param int            $status   estado editable al que volver antes de borrar.
     */
    private function deleteIntraInvoice(Cliente $customer, Contacto $contact, FacturaCliente $invoice, int $status): void
    {
        $invoice->idestado = $status;
        $this->assertTrue($invoice->save(), 'cant-unlock-invoice');
        $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        $this->assertTrue($contact->delete(), 'cant-delete-contact');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
    }

    /**
     * Busca una fila del detalle por CIF/NIF.
     *
     * @param array  $rows   filas del detalle.
     * @param string $cifnif CIF/NIF del operador.
     *
     * @return array|null
     */
    private function findRow(array $rows, string $cifnif): ?array
    {
        foreach ($rows as $row) {
            if ($row['cifnif'] === $cifnif) {
                return $row;
            }
        }
        return null;
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
