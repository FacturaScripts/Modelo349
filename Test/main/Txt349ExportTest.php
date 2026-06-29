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

use FacturaScripts\Dinamic\Lib\Txt349Export;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

/**
 * @author Esteban Sánchez Martínez <esteban@factura.city>
 */
final class Txt349ExportTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
    }

    private function getFirstExercise(): ?Ejercicio
    {
        $ejercicio = new Ejercicio();
        $exercises = $ejercicio->all();
        return empty($exercises) ? null : $exercises[0];
    }

    private function sampleSalesOperator(): array
    {
        return [
            'cifnif' => 'DE123456789',
            'nombre' => 'Muller GmbH',
            'codpais' => 'DEU',
            'base'   => 8200.00,
            'clave'  => 'E',
        ];
    }

    private function samplePurchasesOperator(): array
    {
        return [
            'cifnif' => 'FR12345678901',
            'nombre' => 'Dupont SA',
            'codpais' => 'FRA',
            'base'   => 4000.00,
            'clave'  => 'A',
        ];
    }

    private function exportLines(array $sales = [], array $purchases = []): array
    {
        $exercise = $this->getFirstExercise();
        if ($exercise === null) {
            return [];
        }
        $result = Txt349Export::export((int)$exercise->codejercicio, '2T', $sales, $purchases);
        return array_values(array_filter(explode("\r\n", $result)));
    }

    public function testFormatAmountSplitTipo1(): void
    {
        // Tipo 1: 13 enteros + 2 decimales = 15 chars
        $result = Txt349Export::formatAmountSplit(27450.0, 13, 2);
        $this->assertEquals(15, strlen($result));
        $this->assertEquals('000000002745000', $result);
    }

    public function testFormatAmountSplitTipo2(): void
    {
        // Tipo 2: 11 enteros + 2 decimales = 13 chars
        $result = Txt349Export::formatAmountSplit(8200.0, 11, 2);
        $this->assertEquals(13, strlen($result));
        $this->assertEquals('0000000820000', $result);
    }

    public function testFormatAmountSplitWithCents(): void
    {
        $result = Txt349Export::formatAmountSplit(1234.56, 11, 2);
        $this->assertEquals(13, strlen($result));
        $this->assertEquals('0000000123456', $result);
    }

    public function testFormatAmountSplitZero(): void
    {
        $result = Txt349Export::formatAmountSplit(0.0, 11, 2);
        $this->assertEquals('0000000000000', $result);
        $this->assertEquals(13, strlen($result));
    }

    public function testFormatAmountSplitAlwaysCorrectLength(): void
    {
        foreach ([0.0, 1.0, 9999.99, 100000.0, 0.01] as $amount) {
            $result = Txt349Export::formatAmountSplit($amount, 11, 2);
            $this->assertEquals(13, strlen($result), "formatAmountSplit($amount) debe retornar 13 caracteres");
        }
    }

    public function testFormatStringPadsRight(): void
    {
        $result = Txt349Export::formatString('hola', 10, ' ', STR_PAD_RIGHT);
        $this->assertEquals('HOLA      ', $result);
        $this->assertEquals(10, strlen($result));
    }

    public function testFormatStringPadsLeft(): void
    {
        $result = Txt349Export::formatString('42', 9, '0', STR_PAD_LEFT);
        $this->assertEquals('000000042', $result);
    }

    public function testFormatStringTruncates(): void
    {
        $result = Txt349Export::formatString('abcdefghij', 5, ' ', STR_PAD_RIGHT);
        $this->assertEquals('ABCDE', $result);
        $this->assertEquals(5, strlen($result));
    }

    public function testFormatStringEmpty(): void
    {
        $result = Txt349Export::formatString('', 9, ' ', STR_PAD_RIGHT);
        $this->assertEquals('         ', $result);
        $this->assertEquals(9, strlen($result));
    }

    public function testFormatStringRemovesAccents(): void
    {
        $result = Txt349Export::formatString('café résumé', 15, ' ', STR_PAD_RIGHT);
        $this->assertStringStartsWith('CAFE RESUME', $result);
    }

    public function testFormatStringConvertsToUppercase(): void
    {
        $result = Txt349Export::formatString('empresa sl', 12, ' ', STR_PAD_RIGHT);
        $this->assertStringStartsWith('EMPRESA SL', $result);
    }

    public function testSanitizeRemovesAccents(): void
    {
        // Los acentos se sustituyen por su equivalente en mayúscula
        $this->assertEquals('cafE', Txt349Export::sanitize('café'));
        $this->assertEquals('AEIOU', Txt349Export::sanitize('áéíóú'));
        $this->assertEquals('AEIOU', Txt349Export::sanitize('ÁÉÍÓÚ'));
    }

    public function testSanitizeConvertsEnyeToN(): void
    {
        // En Modelo349 la Ñ se convierte a N (a diferencia del Modelo347)
        $this->assertStringNotContainsString('ñ', Txt349Export::sanitize('señor'));
        $this->assertStringNotContainsString('Ñ', Txt349Export::sanitize('España'));
    }

    public function testSanitizeConvertsUmlautToU(): void
    {
        // sanitize sustituye ü→U pero no hace uppercase del resto (eso lo hace formatString)
        $this->assertEquals('MUller', Txt349Export::sanitize('Müller'));
        // A través de formatString el resultado es completamente en mayúsculas
        $this->assertEquals('MULLER    ', Txt349Export::formatString('Müller', 10, ' ', STR_PAD_RIGHT));
    }

    public function testSanitizeNull(): void
    {
        $this->assertEquals('', Txt349Export::sanitize(null));
    }

    public function testSanitizeEmpty(): void
    {
        $this->assertEquals('', Txt349Export::sanitize(''));
    }

    public function testGetNifOperadorExtractsCountryPrefix(): void
    {
        $op = ['cifnif' => 'DE123456789', 'codpais' => 'DEU'];
        $result = Txt349Export::getNifOperador($op);
        $this->assertEquals(17, strlen($result));
        $this->assertStringStartsWith('DE', $result);
        $this->assertStringContainsString('123456789', $result);
    }

    public function testGetNifOperadorAlwaysReturns17Chars(): void
    {
        $operators = [
            ['cifnif' => 'DE123456789',   'codpais' => 'DEU'],
            ['cifnif' => 'FR12345678901', 'codpais' => 'FRA'],
            ['cifnif' => 'IT01234567890', 'codpais' => 'ITA'],
        ];
        foreach ($operators as $op) {
            $result = Txt349Export::getNifOperador($op);
            $this->assertEquals(17, strlen($result), "getNifOperador debe retornar 17 caracteres para {$op['cifnif']}");
        }
    }

    public function testGetPeriodDates1T(): void
    {
        $dates = Txt349Export::getPeriodDates('1T', 2026);
        $this->assertEquals('01-01-2026', $dates['from']);
        $this->assertEquals('31-03-2026', $dates['to']);
    }

    public function testGetPeriodDates2T(): void
    {
        $dates = Txt349Export::getPeriodDates('2T', 2026);
        $this->assertEquals('01-04-2026', $dates['from']);
        $this->assertEquals('30-06-2026', $dates['to']);
    }

    public function testGetPeriodDates3T(): void
    {
        $dates = Txt349Export::getPeriodDates('3T', 2026);
        $this->assertEquals('01-07-2026', $dates['from']);
        $this->assertEquals('30-09-2026', $dates['to']);
    }

    public function testGetPeriodDates4T(): void
    {
        $dates = Txt349Export::getPeriodDates('4T', 2026);
        $this->assertEquals('01-10-2026', $dates['from']);
        $this->assertEquals('31-12-2026', $dates['to']);
    }

    public function testGetPeriodDatesJanuary(): void
    {
        $dates = Txt349Export::getPeriodDates('01', 2026);
        $this->assertEquals('01-01-2026', $dates['from']);
        $this->assertEquals('31-01-2026', $dates['to']);
    }

    public function testGetPeriodDatesJune(): void
    {
        $dates = Txt349Export::getPeriodDates('06', 2026);
        $this->assertEquals('01-06-2026', $dates['from']);
        $this->assertEquals('30-06-2026', $dates['to']);
    }

    public function testGetPeriodDatesFebruary(): void
    {
        $dates = Txt349Export::getPeriodDates('02', 2026);
        $this->assertEquals('01-02-2026', $dates['from']);
        $this->assertEquals('28-02-2026', $dates['to']);
    }

    public function testGetPeriodDatesFebruaryLeapYear(): void
    {
        $dates = Txt349Export::getPeriodDates('02', 2024);
        $this->assertEquals('01-02-2024', $dates['from']);
        $this->assertEquals('29-02-2024', $dates['to']);
    }


    public function testExportRecordLength(): void
    {
        $lines = $this->exportLines([$this->sampleSalesOperator()], [$this->samplePurchasesOperator()]);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        foreach ($lines as $line) {
            $this->assertEquals(500, strlen($line), 'Cada registro debe tener exactamente 500 caracteres');
        }
    }

    public function testExportRecordTypes(): void
    {
        $lines = $this->exportLines([$this->sampleSalesOperator()], [$this->samplePurchasesOperator()]);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        $this->assertCount(3, $lines, 'Debe haber 3 registros: 1 Tipo 1 + 2 Tipo 2');
        $this->assertEquals('1', $lines[0][0], 'El primer registro debe ser Tipo 1');
        $this->assertEquals('2', $lines[1][0], 'El segundo registro debe ser Tipo 2');
        $this->assertEquals('2', $lines[2][0], 'El tercer registro debe ser Tipo 2');
    }

    public function testExportModeloNumber(): void
    {
        $lines = $this->exportLines([$this->sampleSalesOperator()], []);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        $this->assertEquals('349', substr($lines[0], 1, 3), 'El modelo debe ser 349 en el Tipo 1');
        $this->assertEquals('349', substr($lines[1], 1, 3), 'El modelo debe ser 349 en el Tipo 2');
    }

    public function testExportEmptyData(): void
    {
        $lines = $this->exportLines([], []);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        $this->assertCount(1, $lines, 'Sin operadores solo debe existir el registro Tipo 1');
        $this->assertEquals(500, strlen($lines[0]));
        $this->assertEquals('1', $lines[0][0]);
    }

    public function testExportOperatorsCount(): void
    {
        $lines = $this->exportLines([$this->sampleSalesOperator()], [$this->samplePurchasesOperator()]);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        // Nº total operadores: pos 138-146 → índice 137, 9 chars
        $numOps = substr($lines[0], 137, 9);
        $this->assertEquals('000000002', $numOps, 'El Tipo 1 debe reflejar 2 operadores');
    }

    public function testExportTotalBase(): void
    {
        $lines = $this->exportLines([$this->sampleSalesOperator()], [$this->samplePurchasesOperator()]);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        // Importe total: pos 147-161 → índice 146, 15 chars (8200 + 4000 = 12200)
        $totalField = substr($lines[0], 146, 15);
        $this->assertEquals('000000001220000', $totalField, 'El importe total debe ser 12.200,00 €');
    }

    public function testExportClaveSalesIsE(): void
    {
        $lines = $this->exportLines([$this->sampleSalesOperator()], []);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        // Clave operación: pos 133 → índice 132
        $this->assertEquals('E', $lines[1][132], 'La clave de entrega intracomunitaria debe ser E');
    }

    public function testExportClavePurchasesIsA(): void
    {
        $lines = $this->exportLines([], [$this->samplePurchasesOperator()]);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        $this->assertEquals('A', $lines[1][132], 'La clave de adquisición intracomunitaria debe ser A');
    }

    public function testExportNormalDeclaracionType(): void
    {
        $lines = $this->exportLines([$this->sampleSalesOperator()], []);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        // Tipo declaración: pos 121 → índice 120
        $this->assertEquals(' ', $lines[0][120], 'Una declaración normal debe tener espacio en pos 121');
    }

    public function testExportPeriodField(): void
    {
        $lines = $this->exportLines([$this->sampleSalesOperator()], []);
        if (empty($lines)) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        // Período: pos 136-137 → índice 135, 2 chars
        $period = substr($lines[0], 135, 2);
        $this->assertEquals('2T', $period, 'El período debe ser 2T');
    }

    public function testExportTotalReset(): void
    {
        $exercise = $this->getFirstExercise();
        if ($exercise === null) {
            $this->markTestSkipped('No hay ejercicios disponibles');
        }
        $year = (int)$exercise->codejercicio;

        $result1 = Txt349Export::export($year, '2T', [$this->sampleSalesOperator()], []);
        $result2 = Txt349Export::export($year, '2T', [$this->sampleSalesOperator()], []);

        $lines1 = array_values(array_filter(explode("\r\n", $result1)));
        $lines2 = array_values(array_filter(explode("\r\n", $result2)));

        $this->assertEquals($lines1[0], $lines2[0], 'El total no debe acumularse entre llamadas a export()');
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
