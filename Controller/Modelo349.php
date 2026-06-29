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

namespace FacturaScripts\Plugins\Modelo349\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\DataSrc\Ejercicios;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Txt349Export;
use FacturaScripts\Dinamic\Model\Serie;

/**
 * @author Esteban Sánchez Martínez <esteban@factura.city>
 */
class Modelo349 extends Controller
{
    /** @var string */
    public $codejercicio;

    /** @var string */
    public $declarationType = '';

    /** @var string */
    public $justificante = '';

    /** @var string */
    public $justificanteAnterior = '';

    /** @var string */
    public $periodo;

    /** @var array */
    public $purchasesData = [];

    /** @var array */
    public $purchasesTotals = ['total' => 0.0];

    /** @var array */
    public $salesData = [];

    /** @var array */
    public $salesTotals = ['total' => 0.0];

    /** @var string */
    public $codserie = '';

    /** @var bool */
    public $searched = false;

    public function getCountryName(string $codpais): string
    {
        $pais = Paises::get($codpais);
        return $pais->nombre ?? $codpais;
    }

    public function getSeries(): array
    {
        $serie = new Serie();
        $list = ['' => Tools::trans('all')];
        foreach ($serie->all([], ['codserie' => 'ASC']) as $s) {
            $list[$s->codserie] = $s->codserie . ' - ' . $s->descripcion;
        }
        return $list;
    }

    public function getEjercicios(): array
    {
        $list = [];
        $year = (int)date('Y');
        for ($i = 0; $i < 5; $i++) {
            $value = (string)($year - $i);
            $list[$value] = $value;
        }
        return $list;
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-349';
        $data['icon'] = 'fa-solid fa-globe';
        return $data;
    }

    public function getPeriodOptions(): array
    {
        return [
            '1T' => Tools::trans('first-trimester'),
            '2T' => Tools::trans('second-trimester'),
            '3T' => Tools::trans('third-trimester'),
            '4T' => Tools::trans('fourth-trimester'),
            '01' => Tools::trans('january'),
            '02' => Tools::trans('february'),
            '03' => Tools::trans('march'),
            '04' => Tools::trans('april'),
            '05' => Tools::trans('may'),
            '06' => Tools::trans('june'),
            '07' => Tools::trans('july'),
            '08' => Tools::trans('august'),
            '09' => Tools::trans('september'),
            '10' => Tools::trans('october'),
            '11' => Tools::trans('november'),
            '12' => Tools::trans('december'),
        ];
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->codejercicio = $this->request->input('codejercicio', date('Y'));
        $this->periodo = $this->request->input('periodo', $this->getDefaultPeriod());
        $this->codserie = $this->request->input('codserie', '');

        $this->declarationType = $this->request->input('declarationtype', '');
        if (!in_array($this->declarationType, ['C', 'S'], true)) {
            $this->declarationType = '';
        }

        $this->justificante = preg_replace('/[^0-9]/', '', $this->request->input('justificante', ''));
        $this->justificanteAnterior = preg_replace('/[^0-9]/', '', $this->request->input('justificanteanterior', ''));

        $action = $this->request->input('action', '');
        if ($action === 'download-txt') {
            $this->loadData();
            $this->downloadTxt();
            return;
        }

        if ($this->request->method() === 'POST') {
            $this->loadData();
        }
    }

    protected function downloadTxt(): void
    {
        $this->setTemplate(false);

        $fileName = 'modelo_349_' . $this->codejercicio . '_' . $this->periodo . '.349';
        $content = Txt349Export::export(
            (int)$this->codejercicio,
            $this->periodo,
            $this->salesData,
            $this->purchasesData,
            $this->justificante,
            $this->declarationType,
            $this->justificanteAnterior
        );

        $this->response
            ->header('Content-Type', 'text/plain; charset=ISO-8859-1')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->setContent($content);
    }

    protected function getDefaultPeriod(): string
    {
        $month = (int)date('m');
        if ($month <= 3) return '1T';
        if ($month <= 6) return '2T';
        if ($month <= 9) return '3T';
        return '4T';
    }

    protected function getEuCountriesIn(): string
    {
        $codes = [
            'AUT', 'BEL', 'BGR', 'CYP', 'CZE', 'DEU', 'DNK', 'EST', 'FIN', 'FRA',
            'GRC', 'HRV', 'HUN', 'IRL', 'ITA', 'LTU', 'LUX', 'LVA', 'MLT', 'NLD',
            'POL', 'PRT', 'ROU', 'SWE', 'SVN', 'SVK',
        ];
        return implode(', ', array_map(fn($c) => $this->dataBase->var2str($c), $codes));
    }

    protected function loadData(): void
    {
        $this->searched = true;

        $year = (int)$this->codejercicio;

        $exercise = Ejercicios::get((string)$year);
        $company = Empresas::get($exercise->idempresa ?? 1);
        if (empty($company->telefono1)) {
            Tools::log()->warning('company-phone-no-data', ['%company%' => $company->nombre]);
        }
        if (empty($company->administrador)) {
            Tools::log()->warning('company-admin-no-data', ['%company%' => $company->nombre]);
        }

        $dates = Txt349Export::getPeriodDates($this->periodo, $year);
        $euIn = $this->getEuCountriesIn();

        $this->salesData = $this->queryOperators(
            'facturascli', 'clientes', 'codcliente', 'idcontactofact', $dates, $euIn, 'E'
        );
        $this->purchasesData = $this->queryOperators(
            'facturasprov', 'proveedores', 'codproveedor', 'idcontacto', $dates, $euIn, 'A'
        );

        $this->salesTotals = ['total' => array_sum(array_column($this->salesData, 'base'))];
        $this->purchasesTotals = ['total' => array_sum(array_column($this->purchasesData, 'base'))];

        if (empty($this->salesData) && empty($this->purchasesData)) {
            Tools::log()->warning('349-no-data');
        }
    }

    protected function queryOperators(
        string $facturasTable,
        string $terceroTable,
        string $codField,
        string $contactField,
        array $dates,
        string $euIn,
        string $clave
    ): array {
        $sql = 'SELECT t.cifnif, t.razonsocial, ct.codpais, SUM(f.neto) AS base'
            . ' FROM ' . $facturasTable . ' f'
            . ' INNER JOIN ' . $terceroTable . ' t ON t.' . $codField . ' = f.' . $codField
            . ' LEFT JOIN contactos ct ON ct.idcontacto = t.' . $contactField
            . ' WHERE f.fecha >= ' . $this->dataBase->var2str($dates['from'])
            . ' AND f.fecha <= ' . $this->dataBase->var2str($dates['to'])
            . ' AND f.editable = ' . $this->dataBase->var2str(false)
            . ' AND ct.codpais IN (' . $euIn . ')'
            . ' AND t.cifnif IS NOT NULL AND t.cifnif <> \'\'';

        if (!empty($this->codserie)) {
            $sql .= ' AND f.codserie = ' . $this->dataBase->var2str($this->codserie);
        }

        $sql .= ' GROUP BY t.cifnif, t.razonsocial, ct.codpais'
            . ' HAVING SUM(f.neto) > 0'
            . ' ORDER BY t.cifnif ASC;';

        $result = [];
        foreach ($this->dataBase->select($sql) as $row) {
            $result[] = [
                'cifnif'  => $row['cifnif'],
                'nombre'  => $row['razonsocial'],
                'codpais' => $row['codpais'],
                'base'    => round((float)$row['base'], 2),
                'clave'   => $clave,
            ];
        }
        return $result;
    }
}
