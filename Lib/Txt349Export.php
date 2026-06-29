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

use FacturaScripts\Core\DataSrc\Ejercicios;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Tools;

/**
 * Genera el fichero de texto de ancho fijo para el Modelo 349 (AEAT).
 * Diseño lógico: Tipo 1 (declarante, 500 pos) + Tipo 2 (operadores, 500 pos por registro).
 *
 * @author Esteban Sánchez Martínez <esteban@factura.city>
 */
class Txt349Export
{
    /**
     * Punto de entrada principal. Devuelve el contenido del fichero en ISO-8859-1.
     *
     * @param int    $year
     * @param string $periodo          '1T'|'2T'|'3T'|'4T'|'01'-'12'
     * @param array  $salesData        Entregas intracomunitarias (clave E)
     * @param array  $purchasesData    Adquisiciones intracomunitarias (clave A)
     * @param string $justificante     Número de 13 dígitos; si vacío se genera automáticamente
     * @param string $declarationType  ''|'C'|'S'
     * @param string $justificanteAnterior
     * @return string
     */
    public static function export(
        int $year,
        string $periodo,
        array $salesData,
        array $purchasesData,
        string $justificante = '',
        string $declarationType = '',
        string $justificanteAnterior = ''
    ): string {
        $exercise = Ejercicios::get((string)$year);
        $company = Empresas::get($exercise->idempresa ?? 1);

        if (empty($justificante)) {
            $justificante = '349' . $year . '000001';
        }

        $operators = array_merge($salesData, $purchasesData);
        $totalBase = array_sum(array_column($salesData, 'base'))
                   + array_sum(array_column($purchasesData, 'base'));

        $content = self::buildTipo1(
            $year,
            $company,
            $periodo,
            count($operators),
            $totalBase,
            $justificante,
            $declarationType,
            $justificanteAnterior
        );

        $nifDeclarante = self::sanitize($company->cifnif ?? '');
        foreach ($operators as $op) {
            $content .= self::buildTipo2($year, $nifDeclarante, $op);
        }

        return mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Calcula las fechas de inicio y fin para el período dado.
     */
    public static function getPeriodDates(string $periodo, int $year): array
    {
        switch ($periodo) {
            case '1T':
                return ['from' => '01-01-' . $year, 'to' => '31-03-' . $year];
            case '2T':
                return ['from' => '01-04-' . $year, 'to' => '30-06-' . $year];
            case '3T':
                return ['from' => '01-07-' . $year, 'to' => '30-09-' . $year];
            case '4T':
                return ['from' => '01-10-' . $year, 'to' => '31-12-' . $year];
            default:
                $month = (int)$periodo;
                if ($month < 1 || $month > 12) {
                    $month = 1;
                }
                $isoFrom = sprintf('%04d-%02d-01', $year, $month);
                $lastDay = date('d', strtotime('last day of ' . $isoFrom));
                return [
                    'from' => sprintf('%02d-%02d-%04d', 1, $month, $year),
                    'to' => sprintf('%s-%02d-%04d', $lastDay, $month, $year),
                ];
        }
    }

    /**
     * Registro tipo 1: declarante (500 posiciones + CRLF).
     *
     * Pos  1     : tipo registro = "1"
     * Pos  2-4   : modelo = "349"
     * Pos  5-8   : ejercicio YYYY
     * Pos  9-17  : NIF declarante (9, derecha, ceros)
     * Pos  18-57 : nombre/razón social (40, izquierda, espacios)
     * Pos  58    : blanco
     * Pos  59-67 : teléfono (9, derecha, ceros)
     * Pos  68-107: persona contacto (40, izquierda, espacios)
     * Pos  108-120: número justificante (13, derecha, ceros)
     * Pos  121   : declaración complementaria ("C" o " ")
     * Pos  122   : declaración sustitutiva ("S" o " ")
     * Pos  123-135: número justificante anterior (13, derecha, ceros o espacios)
     * Pos  136-137: período (2, "1T"-"4T" o "01"-"12")
     * Pos  138-146: nº total operadores (9, derecha, ceros)
     * Pos  147-161: importe total operaciones (15 = 13 enteros + 2 decimales)
     * Pos  162-170: nº operadores con rectificaciones (9, derecha, ceros)
     * Pos  171-185: importe rectificaciones (15 = 13 enteros + 2 decimales)
     * Pos  186   : indicador cambio periodicidad (" ")
     * Pos  187-390: blancos (204)
     * Pos  391-399: NIF representante legal (9, espacios)
     * Pos  400-500: blancos (101)
     */
    public static function buildTipo1(
        int $year,
        $company,
        string $periodo,
        int $numOperators,
        float $totalBase,
        string $justificante,
        string $declarationType,
        string $justificanteAnterior
    ): string {
        $nif      = self::formatString($company->cifnif ?? '', 9, ' ', STR_PAD_LEFT);
        $nombre   = self::formatString($company->nombre ?? '', 40, ' ', STR_PAD_RIGHT);
        $telefono = self::formatString(preg_replace('/[^0-9]/', '', $company->telefono1 ?? ''), 9, '0', STR_PAD_LEFT);
        $contacto = self::formatString($company->administrador ?? '', 40, ' ', STR_PAD_RIGHT);
        $justNum  = str_pad(substr(preg_replace('/[^0-9]/', '', $justificante), 0, 13), 13, '0', STR_PAD_LEFT);
        $declC    = ($declarationType === 'C') ? 'C' : ' ';
        $declS    = ($declarationType === 'S') ? 'S' : ' ';
        $justAnt  = in_array($declarationType, ['C', 'S'], true)
            ? str_pad(substr(preg_replace('/[^0-9]/', '', $justificanteAnterior), 0, 13), 13, '0', STR_PAD_LEFT)
            : str_repeat('0', 13);
        $per      = self::formatString($periodo, 2, ' ', STR_PAD_RIGHT);
        $nOps     = str_pad((string)$numOperators, 9, '0', STR_PAD_LEFT);
        $impOps   = self::formatAmountSplit($totalBase, 13, 2);
        $nRectif  = str_repeat('0', 9);
        $impRectif = self::formatAmountSplit(0.0, 13, 2);

        $record = '1'
            . '349'
            . sprintf('%04d', $year)
            . $nif
            . $nombre
            . ' '
            . $telefono
            . $contacto
            . $justNum
            . $declC
            . $declS
            . $justAnt
            . $per
            . $nOps
            . $impOps
            . $nRectif
            . $impRectif
            . ' '
            . str_repeat(' ', 204)
            . str_repeat(' ', 9)
            . str_repeat(' ', 101);

        return $record . "\r\n";
    }

    /**
     * Registro tipo 2: operador intracomunitario (500 posiciones + CRLF).
     *
     * Pos  1     : tipo registro = "2"
     * Pos  2-4   : modelo = "349"
     * Pos  5-8   : ejercicio YYYY
     * Pos  9-17  : NIF declarante (9, derecha, espacios)
     * Pos  18-75 : blancos (58)
     * Pos  76-92 : NIF operador (17 = 2 codiso + 15 número)
     * Pos  93-132: nombre/razón social (40, izquierda, espacios)
     * Pos  133   : clave operación (1, E/A/S/I/T/M/H/R/D/C)
     * Pos  134-146: base imponible (13 = 11 enteros + 2 decimales)
     * Pos  147-178: blancos (32)
     * Pos  179-195: NIF sustituto (17, solo clave C)
     * Pos  196-235: nombre sustituto (40, solo clave C)
     * Pos  236-500: blancos (265)
     */
    public static function buildTipo2(int $year, string $nifDeclarante, array $op): string
    {
        $nifDecl  = self::formatString($nifDeclarante, 9, ' ', STR_PAD_LEFT);
        $nifOp    = self::getNifOperador($op);
        $nombre   = self::formatString($op['nombre'] ?? '', 40, ' ', STR_PAD_RIGHT);
        $clave    = self::formatString($op['clave'] ?? 'E', 1, ' ', STR_PAD_RIGHT);
        $base     = self::formatAmountSplit((float)($op['base'] ?? 0.0), 11, 2);

        $record = '2'
            . '349'
            . sprintf('%04d', $year)
            . $nifDecl
            . str_repeat(' ', 58)
            . $nifOp
            . $nombre
            . $clave
            . $base
            . str_repeat(' ', 32)
            . str_repeat(' ', 17)
            . str_repeat(' ', 40)
            . str_repeat(' ', 265);

        return $record . "\r\n";
    }

    /**
     * Construye el campo NIF del operador comunitario (17 chars = 2 codiso + 15 número).
     */
    public static function getNifOperador(array $op): string
    {
        $codpais = $op['codpais'] ?? '';
        $cifnif  = self::sanitize($op['cifnif'] ?? '');

        $pais   = Paises::get($codpais);
        $codiso = strtoupper($pais->codiso ?? '');

        $nifNum = $cifnif;
        if (!empty($codiso) && stripos($cifnif, $codiso) === 0) {
            $nifNum = substr($cifnif, strlen($codiso));
        }

        $countryCode = self::formatString($codiso, 2, ' ', STR_PAD_RIGHT);
        $nifPart     = self::formatString($nifNum, 15, ' ', STR_PAD_RIGHT);

        return $countryCode . $nifPart;
    }

    /**
     * Formatea un importe separando parte entera y decimal en campos de ancho fijo.
     * Ejemplo: 1234.56, intLen=13, decLen=2 → "0000000001234" + "56" = "000000000123456" (15 chars)
     */
    public static function formatAmountSplit(float $amount, int $intLen, int $decLen): string
    {
        $amount  = round(abs($amount), 2);
        $intPart = (int)floor($amount);
        $decPart = (int)round(($amount - floor($amount)) * 100);

        return str_pad((string)$intPart, $intLen, '0', STR_PAD_LEFT)
             . str_pad((string)$decPart, $decLen, '0', STR_PAD_LEFT);
    }

    /**
     * Formatea una cadena a la longitud indicada: sanitiza, convierte a mayúsculas, trunca y rellena.
     */
    public static function formatString(string $string, int $length, string $padChar, int $align): string
    {
        $string = self::sanitize($string);
        $string = mb_strtoupper($string);

        if (mb_strlen($string) > $length) {
            $string = mb_substr($string, 0, $length);
        }

        return str_pad($string, $length, $padChar, $align);
    }

    /**
     * Elimina tildes y caracteres no ASCII válidos para ficheros AEAT.
     */
    public static function sanitize(?string $txt): string
    {
        if (null === $txt) {
            return '';
        }

        $from = ['á', 'à', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï',
                 'ó', 'ò', 'ô', 'ö', 'ú', 'ù', 'û', 'ü', 'ñ', 'ç',
                 'Á', 'À', 'Â', 'Ä', 'É', 'È', 'Ê', 'Ë', 'Í', 'Ì', 'Î', 'Ï',
                 'Ó', 'Ò', 'Ô', 'Ö', 'Ú', 'Ù', 'Û', 'Ü', 'Ñ', 'Ç'];
        $to   = ['A', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I',
                 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'N', 'C',
                 'A', 'A', 'A', 'A', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I',
                 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'N', 'C'];

        return Tools::noHtml(str_replace($from, $to, $txt));
    }
}
