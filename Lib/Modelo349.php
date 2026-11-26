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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Clave349;
use FacturaScripts\Dinamic\Lib\Modelo349File;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;

/**
 * Cálculo de los datos del Modelo 349 (declaración recapitulativa de operaciones intracomunitarias).
 *
 * Obtiene los operadores intracomunitarios de una empresa en un periodo, acumulados por operador y
 * clave de operación. La empresa y el año se obtienen del ejercicio: el código del ejercicio no tiene
 * por qué ser el año (puede ser 0001, AL25...) y cada empresa tiene sus propios ejercicios.
 * Lo usan la pantalla del plugin (vista y TXT) y el endpoint de la API.
 *
 * @author Esteban Sánchez Martínez <esteban@factura.city>
 */
class Modelo349
{
    /** Países de la UE distintos de España (códigos alfa-3). */
    const EU_COUNTRIES = [
        'AUT', 'BEL', 'BGR', 'CYP', 'CZE', 'DEU', 'DNK', 'EST', 'FIN', 'FRA',
        'GRC', 'HRV', 'HUN', 'IRL', 'ITA', 'LTU', 'LUX', 'LVA', 'MLT', 'NLD',
        'POL', 'PRT', 'ROU', 'SWE', 'SVN', 'SVK',
    ];

    /**
     * Calcula los datos del modelo para un ejercicio y periodo.
     *
     * @param string $codejercicio código del ejercicio (determina la empresa y el año).
     * @param string $periodo      periodo: '1T'-'4T' o '01'-'12'.
     * @param string $codserie     serie de facturas; vacío para todas.
     *
     * @return array con las claves:
     *   - exercise: Ejercicio|null, el ejercicio (null si no existe).
     *   - company: Empresa, la empresa del ejercicio.
     *   - year: int, año del ejercicio (de su fecha de inicio).
     *   - dates: array ['from', 'to'] con las fechas del periodo (d-m-Y).
     *   - sales: array, ventas intracomunitarias (claves E y S).
     *   - purchases: array, compras intracomunitarias (claves A e I).
     *   - warnings: array, avisos con la clave de traducción ('key') y su contexto ('context').
     */
    public static function calculate(string $codejercicio, string $periodo, string $codserie = ''): array
    {
        $result = [
            'exercise' => null,
            'company' => new Empresa(),
            'year' => 0,
            'dates' => [],
            'sales' => [],
            'purchases' => [],
            'warnings' => [],
        ];

        $exercise = new Ejercicio();
        if (empty($codejercicio) || false === $exercise->load($codejercicio)) {
            $result['warnings'][] = ['key' => 'accounting-exercise-not-found', 'context' => []];
            return $result;
        }

        $company = new Empresa();
        $company->load($exercise->idempresa);

        $result['exercise'] = $exercise;
        $result['company'] = $company;
        $result['year'] = self::getYear($exercise);
        $result['dates'] = Modelo349File::getPeriodDates($periodo, $result['year']);
        $result['warnings'] = self::checkCompany($company);

        $idempresa = (int)$exercise->idempresa;
        $result['sales'] = self::queryOperators(
            'facturascli', 'clientes', 'codcliente', 'idcontactofact', $result['dates'], $idempresa, $codserie, true
        );
        $result['purchases'] = self::queryOperators(
            'facturasprov', 'proveedores', 'codproveedor', 'idcontacto', $result['dates'], $idempresa, $codserie, false
        );

        if (empty($result['sales']) && empty($result['purchases'])) {
            $result['warnings'][] = ['key' => '349-no-data', 'context' => []];
        }

        return $result;
    }

    /**
     * Devuelve el ejercicio predeterminado: el de la empresa predeterminada que contiene la fecha de hoy
     * o, si no hay, el más reciente de esa empresa.
     *
     * @return string código del ejercicio, o el año actual si la empresa no tiene ejercicios.
     */
    public static function getDefaultExercise(): string
    {
        $where = [Where::eq('idempresa', Tools::settings('default', 'idempresa'))];
        $exercises = Ejercicio::all($where, ['fechainicio' => 'DESC'], 0, 0);
        foreach ($exercises as $exercise) {
            if ($exercise->inRange(date('d-m-Y'))) {
                return $exercise->codejercicio;
            }
        }

        return empty($exercises) ? date('Y') : $exercises[0]->codejercicio;
    }

    /**
     * Devuelve el año natural de un ejercicio: el de su fecha de inicio.
     *
     * @param Ejercicio $exercise ejercicio.
     *
     * @return int
     */
    public static function getYear(Ejercicio $exercise): int
    {
        return (int)date('Y', strtotime($exercise->fechainicio));
    }

    /**
     * Comprueba que la empresa tiene los datos obligatorios del declarante: teléfono y administrador.
     *
     * @param Empresa $company empresa declarante.
     *
     * @return array avisos con la clave de traducción y su contexto.
     */
    protected static function checkCompany(Empresa $company): array
    {
        $warnings = [];
        if (empty($company->telefono1)) {
            $warnings[] = ['key' => 'company-phone-no-data', 'context' => ['%company%' => $company->nombre]];
        }
        if (empty($company->administrador)) {
            $warnings[] = ['key' => 'company-admin-no-data', 'context' => ['%company%' => $company->nombre]];
        }
        return $warnings;
    }

    /**
     * Obtiene los operadores intracomunitarios del periodo y la empresa, acumulados por operador y clave
     * de operación. Solo se incluyen las facturas no editables cuya operación es intracomunitaria (bienes
     * o servicios), ya que la clave se deduce del campo operacion de la factura.
     *
     * @param string $facturasTable tabla de facturas (facturascli o facturasprov).
     * @param string $terceroTable  tabla del tercero (clientes o proveedores).
     * @param string $codField      campo que relaciona la factura con el tercero.
     * @param string $contactField  campo del tercero con el contacto del que se toma el país.
     * @param array  $dates         fechas de inicio ('from') y fin ('to') del periodo.
     * @param int    $idempresa     empresa declarante.
     * @param string $codserie      serie de facturas; vacío para todas.
     * @param bool   $esVenta       true para ventas, false para compras.
     *
     * @return array filas con cifnif, nombre, codpais, base y clave.
     */
    protected static function queryOperators(
        string $facturasTable,
        string $terceroTable,
        string $codField,
        string $contactField,
        array $dates,
        int $idempresa,
        string $codserie,
        bool $esVenta
    ): array {
        $db = new DataBase();

        $operaciones = array_map(
            fn($operacion) => $db->var2str($operacion),
            Clave349::operaciones($esVenta)
        );
        $euIn = implode(', ', array_map(fn($c) => $db->var2str($c), self::EU_COUNTRIES));

        $sql = 'SELECT t.cifnif, t.razonsocial, ct.codpais, f.operacion, SUM(f.neto) AS base'
            . ' FROM ' . $facturasTable . ' f'
            . ' INNER JOIN ' . $terceroTable . ' t ON t.' . $codField . ' = f.' . $codField
            . ' LEFT JOIN contactos ct ON ct.idcontacto = t.' . $contactField
            . ' WHERE f.idempresa = ' . $db->var2str($idempresa)
            . ' AND f.fecha >= ' . $db->var2str($dates['from'])
            . ' AND f.fecha <= ' . $db->var2str($dates['to'])
            . ' AND f.editable = ' . $db->var2str(false)
            . ' AND f.operacion IN (' . implode(', ', $operaciones) . ')'
            . ' AND ct.codpais IN (' . $euIn . ')'
            . ' AND t.cifnif IS NOT NULL AND t.cifnif <> \'\'';

        if (!empty($codserie)) {
            $sql .= ' AND f.codserie = ' . $db->var2str($codserie);
        }

        $sql .= ' GROUP BY t.cifnif, t.razonsocial, ct.codpais, f.operacion'
            . ' HAVING SUM(f.neto) > 0'
            . ' ORDER BY t.cifnif ASC, f.operacion ASC;';

        $result = [];
        foreach ($db->select($sql) as $row) {
            $result[] = [
                'cifnif' => $row['cifnif'],
                'nombre' => $row['razonsocial'],
                'codpais' => $row['codpais'],
                'base' => round((float)$row['base'], 2),
                'clave' => Clave349::porOperacion($esVenta, $row['operacion']),
            ];
        }
        return $result;
    }
}
