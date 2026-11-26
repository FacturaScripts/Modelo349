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
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Modelo349 as Modelo349Lib;
use FacturaScripts\Dinamic\Lib\Modelo349Api;
use FacturaScripts\Dinamic\Lib\Modelo349File;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Serie;

/**
 * Pantalla del Modelo 349: muestra las operaciones intracomunitarias de la empresa del ejercicio
 * seleccionado y descarga el TXT para la AEAT. El cálculo está en Lib/Modelo349, compartido con
 * el endpoint de la API.
 *
 * @author Esteban Sánchez Martínez <esteban@factura.city>
 */
class Modelo349 extends Controller
{
    /** @var string Código del ejercicio seleccionado; determina la empresa y el año declarados. */
    public $codejercicio;

    /** @var string Serie de facturas a filtrar; vacío para todas. */
    public $codserie = '';

    /**
     * Empresa declarante: la del ejercicio seleccionado.
     *
     * @var Empresa|null
     */
    public $company;

    /** @var string Tipo de declaración: '' (normal), 'C' (complementaria) o 'S' (sustitutiva). */
    public $declarationType = '';

    /** @var string Número de justificante de 13 dígitos (solo números); vacío para que se genere uno. */
    public $justificante = '';

    /** @var string Número de justificante de la declaración anterior (solo números). */
    public $justificanteAnterior = '';

    /** @var string Periodo seleccionado en formato de pantalla: '1T'-'4T' o '01'-'12'. */
    public $periodo;

    /** @var array Compras intracomunitarias (claves A e I): cifnif, nombre, codpais, base y clave. */
    public $purchasesData = [];

    /** @var array Totales de las compras; 'total' es la suma de las bases. */
    public $purchasesTotals = ['total' => 0.0];

    /** @var array Ventas intracomunitarias (claves E y S): cifnif, nombre, codpais, base y clave. */
    public $salesData = [];

    /** @var array Totales de las ventas; 'total' es la suma de las bases. */
    public $salesTotals = ['total' => 0.0];

    /** @var bool Indica si ya se han calculado los datos (la vista muestra los resultados). */
    public $searched = false;

    /**
     * Año declarado: el de la fecha de inicio del ejercicio seleccionado.
     *
     * @var int
     */
    public $year = 0;

    /**
     * Empresas disponibles, para el selector de la vista.
     *
     * @return array
     */
    public function getCompanies(): array
    {
        return Empresas::all();
    }

    /**
     * Nombre del país para mostrarlo en la vista; si no existe, devuelve el propio código.
     *
     * @param string $codpais código del país (alfa-3).
     *
     * @return string
     */
    public function getCountryName(string $codpais): string
    {
        $pais = Paises::get($codpais);
        return $pais->nombre ?? $codpais;
    }

    /**
     * Datos de la página: menú Informes, submenú de modelos fiscales.
     *
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['submenu'] = 'tax-models';
        $data['title'] = 'model-349';
        $data['icon'] = 'fa-solid fa-globe';
        return $data;
    }

    /**
     * Periodos del selector (trimestres 1T-4T y meses 01-12) con su texto traducido.
     *
     * @return array periodo => texto.
     */
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

    /**
     * Series de facturas para el selector, con la opción «todas» (clave vacía) al principio.
     *
     * @return array codserie => texto.
     */
    public function getSeries(): array
    {
        $serie = new Serie();
        $list = ['' => Tools::trans('all')];
        foreach ($serie->all([], ['codserie' => 'ASC']) as $s) {
            $list[$s->codserie] = $s->codserie . ' - ' . $s->descripcion;
        }
        return $list;
    }

    /**
     * Lee los filtros del formulario y calcula los datos o descarga el TXT.
     *
     * @param mixed $response
     * @param mixed $user
     * @param mixed $permissions
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        // el ejercicio determina la empresa y el año declarados
        $this->codejercicio = $this->request->input('codejercicio', Modelo349Lib::getDefaultExercise());
        // admite también el formato de la API (T1-T4) y descarta los periodos no válidos
        $this->periodo = Modelo349Api::screenPeriod($this->request->input('periodo'), $this->getDefaultPeriod());
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

    /**
     * Descarga el TXT para la AEAT con el año y la empresa del ejercicio seleccionado.
     */
    protected function downloadTxt(): void
    {
        $this->setTemplate(false);

        $fileName = 'modelo_349_' . $this->codejercicio . '_' . $this->periodo . '.349';
        $content = Modelo349File::generate(
            $this->year,
            $this->periodo,
            $this->salesData,
            $this->purchasesData,
            $this->justificante,
            $this->declarationType,
            $this->justificanteAnterior,
            $this->company->idempresa ?? null
        );

        $this->response
            ->header('Content-Type', 'text/plain; charset=ISO-8859-1')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->setContent($content);
    }

    /**
     * Trimestre en curso según la fecha de hoy.
     *
     * @return string '1T'-'4T'.
     */
    protected function getDefaultPeriod(): string
    {
        $month = (int)date('m');
        if ($month <= 3) return '1T';
        if ($month <= 6) return '2T';
        if ($month <= 9) return '3T';
        return '4T';
    }

    /**
     * Calcula los operadores intracomunitarios de la empresa del ejercicio en el periodo seleccionado
     * y muestra los avisos (empresa sin teléfono o administrador, periodo sin operaciones).
     */
    protected function loadData(): void
    {
        $this->searched = true;

        $result = Modelo349Lib::calculate((string)$this->codejercicio, (string)$this->periodo, (string)$this->codserie);
        $this->company = $result['company'];
        $this->year = $result['year'];
        $this->salesData = $result['sales'];
        $this->purchasesData = $result['purchases'];

        $this->salesTotals = ['total' => array_sum(array_column($this->salesData, 'base'))];
        $this->purchasesTotals = ['total' => array_sum(array_column($this->purchasesData, 'base'))];

        foreach ($result['warnings'] as $warning) {
            Tools::log()->warning($warning['key'], $warning['context']);
        }
    }
}
