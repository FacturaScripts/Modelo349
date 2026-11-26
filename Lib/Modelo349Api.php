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

use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Modelo349 as Modelo349Lib;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Serie;

/**
 * Construye la respuesta del endpoint de solo lectura GET /api/3/modelo349.
 *
 * Resuelve la empresa y el ejercicio a partir de los parámetros de la petición, ejecuta el mismo
 * cálculo que la pantalla del plugin (Lib/Modelo349) y devuelve los operadores intracomunitarios
 * del periodo, sus totales por clave, las casillas del resumen y los avisos. No crea, modifica ni
 * borra ningún registro.
 *
 * Parámetros (como texto de la query string):
 *   - codejercicio: ejercicio a declarar. Si se indica, manda sobre idempresa y anio.
 *   - idempresa: empresa (por defecto, la predeterminada).
 *   - anio: año natural del ejercicio de esa empresa (por defecto, el actual).
 *   - periodo (obligatorio): T1-T4 (o 1T-4T) y 01-12 (o 1-12).
 *   - codserie: serie de facturas; vacío para todas.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo349Api
{
    /** Número del modelo. */
    const MODEL = '349';

    /** Periodos que admite el modelo. */
    const PERIODS = ['T1', 'T2', 'T3', 'T4', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];

    /** Nombre del plugin, para obtener su versión. */
    const PLUGIN_NAME = 'Modelo349';

    /**
     * Construye la respuesta del endpoint a partir de los parámetros de la petición.
     *
     * @param array $params parámetros de la query string (valores string o null si no se indican).
     *
     * @return array ['code' => código HTTP, 'body' => cuerpo JSON de la respuesta].
     */
    public static function build(array $params): array
    {
        // periodo obligatorio: trimestres y meses
        if (self::isEmptyParam($params['periodo'] ?? null)) {
            return self::error(400, 'missing-period', ['allowed' => self::PERIODS]);
        }
        $periodo = self::normalizePeriod($params['periodo']);
        if (false === in_array($periodo, self::PERIODS, true)) {
            return self::error(400, 'invalid-period', ['allowed' => self::PERIODS]);
        }

        // serie de facturas
        $codserie = self::isEmptyParam($params['codserie'] ?? null) ? '' : trim($params['codserie']);
        if ($codserie !== '' && false === (new Serie())->load($codserie)) {
            return self::error(404, 'serie-not-found', ['codserie' => $codserie]);
        }

        // empresa y ejercicio
        $resolved = self::resolveExercise($params['codejercicio'] ?? null, $params['idempresa'] ?? null, $params['anio'] ?? null);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        /** @var Ejercicio $exercise */
        $exercise = $resolved['exercise'];
        /** @var Empresa $company */
        $company = $resolved['company'];

        // mismo cálculo que la pantalla del plugin, con el periodo en su formato (1T-4T, 01-12)
        $result = Modelo349Lib::calculate($exercise->codejercicio, self::internalPeriod($periodo), $codserie);
        $sales = self::detailRows($result['sales']);
        $purchases = self::detailRows($result['purchases']);

        $byKey = ['E' => 0.0, 'S' => 0.0, 'A' => 0.0, 'I' => 0.0];
        foreach (array_merge($sales, $purchases) as $row) {
            if (isset($byKey[$row['clave']])) {
                $byKey[$row['clave']] += $row['base'];
            }
        }
        $totalSales = round(array_sum(array_column($sales, 'base')), 2);
        $totalPurchases = round(array_sum(array_column($purchases, 'base')), 2);
        $numOperators = count($sales) + count($purchases);

        return [
            'code' => 200,
            'body' => [
                'modelo' => self::MODEL,
                'plugin_version' => self::pluginVersion(),
                'empresa' => self::companyData($company),
                'ejercicio' => self::exerciseData($exercise),
                'periodo' => $periodo,
                'fechainicio' => self::isoDate($result['dates']['from'] ?? null),
                'fechafin' => self::isoDate($result['dates']['to'] ?? null),
                'parametros' => [
                    'codserie' => $codserie,
                ],
                'resumen' => [
                    'num_operadores' => $numOperators,
                    'total_entregas' => round($byKey['E'], 2),
                    'total_servicios_prestados' => round($byKey['S'], 2),
                    'total_adquisiciones' => round($byKey['A'], 2),
                    'total_servicios_adquiridos' => round($byKey['I'], 2),
                    'total_ventas' => $totalSales,
                    'total_compras' => $totalPurchases,
                ],
                // casillas del resumen de la declaración: 01 nº total de operadores intracomunitarios,
                // 02 importe de las operaciones intracomunitarias (las del registro de tipo 1 del TXT;
                // el plugin no calcula rectificaciones)
                'casillas' => [
                    '01' => $numOperators,
                    '02' => round($totalSales + $totalPurchases, 2),
                ],
                'resultado' => null,
                'detalle' => [
                    'ventas' => $sales,
                    'compras' => $purchases,
                ],
                'avisos' => self::warnings($result['warnings']),
            ],
        ];
    }

    /**
     * Convierte un periodo normalizado de la API al formato de la pantalla y del TXT:
     * T1-T4 → 1T-4T; los meses 01-12 no cambian.
     *
     * @param string $periodo periodo normalizado (T1-T4 o 01-12).
     *
     * @return string
     */
    public static function internalPeriod(string $periodo): string
    {
        if (preg_match('/^T([1-4])$/', $periodo, $matches)) {
            return $matches[1] . 'T';
        }
        return $periodo;
    }

    /**
     * Normaliza un periodo: mayúsculas, sin espacios y con los alias admitidos
     * (1T-4T → T1-T4, 1-12 → 01-12, ANNUAL/Y/A/0A → ANUAL).
     *
     * @param string|null $periodo periodo tal y como llega en la petición.
     *
     * @return string periodo normalizado, o cadena vacía si no se indica.
     */
    public static function normalizePeriod(?string $periodo): string
    {
        $value = strtoupper(preg_replace('/\s+/', '', (string)$periodo));
        if (in_array($value, ['ANUAL', 'ANNUAL', 'Y', 'A', '0A'], true)) {
            return 'ANUAL';
        }

        if (preg_match('/^([1-4])T$/', $value, $matches)) {
            return 'T' . $matches[1];
        }

        if (preg_match('/^\d{1,2}$/', $value) && (int)$value >= 1 && (int)$value <= 12) {
            return sprintf('%02d', (int)$value);
        }

        return $value;
    }

    /**
     * Resuelve el ejercicio y la empresa: por código de ejercicio o, si no se indica, por empresa y año
     * (el ejercicio de la empresa cuya fecha de inicio cae en ese año).
     *
     * @param string|null $codejercicio código del ejercicio.
     * @param string|null $idempresa    empresa; por defecto, la predeterminada.
     * @param string|null $anio         año natural; por defecto, el actual.
     *
     * @return array ['exercise' => Ejercicio, 'company' => Empresa] o ['error' => respuesta de error].
     */
    public static function resolveExercise(?string $codejercicio, ?string $idempresa, ?string $anio): array
    {
        // el código de ejercicio manda: la empresa es la del ejercicio
        if (false === self::isEmptyParam($codejercicio)) {
            $exercise = new Ejercicio();
            if (false === $exercise->load(trim($codejercicio))) {
                return ['error' => self::error(404, 'exercise-not-found', ['codejercicio' => trim($codejercicio)])];
            }

            $company = new Empresa();
            if (false === $company->load($exercise->idempresa)) {
                return ['error' => self::error(404, 'company-not-found', ['idempresa' => $exercise->idempresa])];
            }

            return ['exercise' => $exercise, 'company' => $company];
        }

        // empresa indicada o predeterminada
        $id = self::isEmptyParam($idempresa) ? (string)Tools::settings('default', 'idempresa') : trim($idempresa);
        $company = new Empresa();
        if (false === ctype_digit($id) || false === $company->load((int)$id)) {
            return ['error' => self::error(404, 'company-not-found', ['idempresa' => $id])];
        }

        // año indicado o actual
        $year = self::isEmptyParam($anio) ? date('Y') : trim($anio);
        if (1 !== preg_match('/^\d{4}$/', $year)) {
            return ['error' => self::error(400, 'invalid-year', ['anio' => $year])];
        }

        // ejercicio de la empresa cuya fecha de inicio cae en ese año
        $where = [Where::eq('idempresa', $company->idempresa)];
        foreach (Ejercicio::all($where, ['fechainicio' => 'ASC'], 0, 0) as $exercise) {
            if (date('Y', strtotime($exercise->fechainicio)) === $year) {
                return ['exercise' => $exercise, 'company' => $company];
            }
        }

        return ['error' => self::error(404, 'exercise-not-found', ['idempresa' => $company->idempresa, 'anio' => $year])];
    }

    /**
     * Devuelve el periodo de la pantalla y del TXT (1T-4T o 01-12) a partir del que llega en la petición,
     * en cualquiera de los formatos admitidos por la API (T1, 1T, 3, 03...). Si no es un periodo válido
     * devuelve el indicado por defecto, para no calcular ni generar un fichero con un periodo inexistente.
     *
     * @param string|null $periodo periodo tal y como llega en la petición.
     * @param string $default periodo por defecto, en el formato de la pantalla.
     *
     * @return string
     */
    public static function screenPeriod(?string $periodo, string $default): string
    {
        $normalized = self::normalizePeriod($periodo);
        return in_array($normalized, self::PERIODS, true) ? self::internalPeriod($normalized) : $default;
    }

    /**
     * Datos básicos de la empresa para la respuesta.
     *
     * @param Empresa $company empresa declarante.
     *
     * @return array
     */
    protected static function companyData(Empresa $company): array
    {
        return [
            'idempresa' => (int)$company->idempresa,
            'nombre' => $company->nombre,
            'cifnif' => $company->cifnif,
        ];
    }

    /**
     * Convierte las filas del cálculo en filas planas para el detalle de la respuesta.
     *
     * @param array $data operadores del cálculo.
     *
     * @return array
     */
    protected static function detailRows(array $data): array
    {
        $rows = [];
        foreach ($data as $row) {
            $rows[] = [
                'cifnif' => $row['cifnif'],
                'nombre' => $row['nombre'],
                'codpais' => $row['codpais'],
                'clave' => $row['clave'],
                'base' => round((float)$row['base'], 2),
            ];
        }
        return $rows;
    }

    /**
     * Construye una respuesta de error en el formato de los endpoints del core.
     *
     * @param int    $code    código HTTP.
     * @param string $message clave del error.
     * @param array  $extra   datos adicionales.
     *
     * @return array
     */
    protected static function error(int $code, string $message, array $extra = []): array
    {
        return [
            'code' => $code,
            'body' => array_merge(['status' => 'error', 'message' => $message], $extra),
        ];
    }

    /**
     * Datos básicos del ejercicio para la respuesta, con las fechas en formato ISO.
     *
     * @param Ejercicio $exercise ejercicio declarado.
     *
     * @return array
     */
    protected static function exerciseData(Ejercicio $exercise): array
    {
        return [
            'codejercicio' => $exercise->codejercicio,
            'nombre' => $exercise->nombre,
            'fechainicio' => self::isoDate($exercise->fechainicio),
            'fechafin' => self::isoDate($exercise->fechafin),
            'estado' => $exercise->estado,
        ];
    }

    /**
     * Indica si un parámetro no se ha indicado o está vacío.
     *
     * @param mixed $value valor del parámetro.
     *
     * @return bool
     */
    protected static function isEmptyParam($value): bool
    {
        return null === $value || '' === trim((string)$value);
    }

    /**
     * Convierte una fecha del modelo (d-m-Y) a formato ISO (Y-m-d).
     *
     * @param string|null $date fecha.
     *
     * @return string|null
     */
    protected static function isoDate(?string $date): ?string
    {
        return empty($date) ? null : date('Y-m-d', strtotime($date));
    }

    /**
     * Devuelve la versión instalada del plugin.
     *
     * @return float
     */
    protected static function pluginVersion(): float
    {
        $plugin = Plugins::get(self::PLUGIN_NAME);
        if (null !== $plugin && $plugin->version > 0) {
            return (float)$plugin->version;
        }

        // respaldo: leemos el facturascripts.ini del plugin
        $ini = parse_ini_file(dirname(__DIR__) . '/facturascripts.ini');
        return (float)($ini['version'] ?? 0);
    }

    /**
     * Traduce al español los avisos del cálculo.
     *
     * @param array $warnings avisos con la clave de traducción ('key') y su contexto ('context').
     *
     * @return string[]
     */
    protected static function warnings(array $warnings): array
    {
        $translator = Tools::lang('es_ES');
        $list = [];
        foreach ($warnings as $warning) {
            $list[] = rtrim($translator->trans($warning['key'], $warning['context']), '.') . '.';
        }
        return $list;
    }
}
