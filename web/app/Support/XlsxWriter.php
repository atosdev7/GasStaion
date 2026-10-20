<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

// Minimal export-only .xlsx writer (a zip of XML parts). PHP 7.2 cannot run any maintained spreadsheet package.
// A sheet is ['title' => string, 'headings' => array, 'rows' => array of arrays, 'money' => 0-based column indexes shown with 2 decimals].
// 'headings' may also be a list of heading rows (arrays of arrays), and 'merges' a list of cell ranges to merge (for example 'C1:E1'); the
// heading rows are bold and stay in view when the sheet is scrolled. 'borders' => true draws a line round every cell of the table (the
// empty cells, and the empty cells of a merged heading, too) and centres the headings.
// Numbers are written as numbers, everything else as text (inline strings are never evaluated as formulas).
class XlsxWriter
{
    const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    // Returns the path of a temporary file; the caller sends it and deletes it.
    public static function build(array $sheets)
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the export file.');
        }

        $names = self::sheetNames($sheets);
        $count = count($sheets);

        $zip->addFromString('[Content_Types].xml', self::contentTypes($count));
        $zip->addFromString('_rels/.rels', self::xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . self::REL_NS . '/officeDocument" Target="xl/workbook.xml"/></Relationships>'));
        $zip->addFromString('xl/workbook.xml', self::workbook($names));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels($count));
        $zip->addFromString('xl/styles.xml', self::styles());
        foreach (array_values($sheets) as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', self::sheet($sheet));
        }
        $zip->close();

        return $path;
    }

    private static function xml($body)
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . $body;
    }

    private static function contentTypes($count)
    {
        $main = 'application/vnd.openxmlformats-officedocument.spreadsheetml';
        $x = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="' . $main . '.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="' . $main . '.styles+xml"/>';
        for ($i = 1; $i <= $count; $i++) {
            $x .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="' . $main . '.worksheet+xml"/>';
        }

        return self::xml($x . '</Types>');
    }

    private static function workbook(array $names)
    {
        $x = '<workbook xmlns="' . self::NS . '" xmlns:r="' . self::REL_NS . '"><sheets>';
        foreach ($names as $i => $name) {
            $x .= '<sheet name="' . self::esc($name) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }

        return self::xml($x . '</sheets></workbook>');
    }

    private static function workbookRels($count)
    {
        $x = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $count; $i++) {
            $x .= '<Relationship Id="rId' . $i . '" Type="' . self::REL_NS . '/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $x .= '<Relationship Id="rId' . ($count + 1) . '" Type="' . self::REL_NS . '/styles" Target="styles.xml"/>';

        return self::xml($x . '</Relationships>');
    }

    // Cell styles: 0 plain, 1 bold heading, 2 number with 2 decimals (built-in format 4 = #,##0.00).
    private static function styles()
    {
        return self::xml('<styleSheet xmlns="' . self::NS . '">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right>'
            . '<top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');
    }

    private static function sheet(array $sheet)
    {
        $money = array_flip(isset($sheet['money']) ? $sheet['money'] : []);
        $headings = $sheet['headings'];
        $headRows = (isset($headings[0]) && is_array($headings[0])) ? $headings : [$headings];
        $headCount = count($headRows);
        $borders = !empty($sheet['borders']);
        $columns = count($headRows[0]);
        $rows = array_merge($headRows, $sheet['rows']);

        $widths = [];
        $data = '';
        foreach ($rows as $r => $row) {
            $data .= '<row r="' . ($r + 1) . '">';
            $row = array_values($row);
            $cells = $borders ? max($columns, count($row)) : count($row);
            for ($c = 0; $c < $cells; $c++) {
                $value = isset($row[$c]) ? $row[$c] : null;
                if ($value === null || $value === '') {
                    if ($borders) {
                        $data .= '<c r="' . self::column($c) . ($r + 1) . '" s="' . ($r < $headCount ? 3 : 4) . '"/>';
                    }
                    continue;
                }
                $ref = self::column($c) . ($r + 1);
                if (is_int($value) || is_float($value)) {
                    $style = ($r >= $headCount && isset($money[$c])) ? ($borders ? 5 : 2) : ($borders ? 4 : 0);
                    $data .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
                    $len = strlen(number_format($value, isset($money[$c]) ? 2 : 0));
                } else {
                    $text = self::clean((string) $value);
                    $data .= '<c r="' . $ref . '" t="inlineStr" s="' . ($r < $headCount ? ($borders ? 3 : 1) : ($borders ? 4 : 0)) . '"><is><t xml:space="preserve">' . self::esc($text) . '</t></is></c>';
                    $len = mb_strwidth($text, 'UTF-8');
                }
                $widths[$c] = max(isset($widths[$c]) ? $widths[$c] : 0, $len);
            }
            $data .= '</row>';
        }

        $merges = '';
        if (!empty($sheet['merges'])) {
            $merges = '<mergeCells count="' . count($sheet['merges']) . '">';
            foreach ($sheet['merges'] as $range) {
                $merges .= '<mergeCell ref="' . self::esc($range) . '"/>';
            }
            $merges .= '</mergeCells>';
        }

        $cols = '';
        foreach ($widths as $c => $w) {
            $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . min(60, $w + 3) . '" customWidth="1"/>';
        }

        return self::xml('<worksheet xmlns="' . self::NS . '">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $headCount . '" topLeftCell="A' . ($headCount + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols === '' ? '' : '<cols>' . $cols . '</cols>')
            . '<sheetData>' . $data . '</sheetData>' . $merges . '</worksheet>');
    }

    // 0 -> A, 25 -> Z, 26 -> AA
    private static function column($index)
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26) . $name;
        }

        return $name;
    }

    // Excel sheet names: at most 31 characters, none of []:*?/\ and unique.
    private static function sheetNames(array $sheets)
    {
        $names = [];
        foreach (array_values($sheets) as $i => $sheet) {
            $base = trim(mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $sheet['title']), 0, 28, 'UTF-8'));
            $name = $base === '' ? 'Sheet' . ($i + 1) : $base;
            for ($n = 2; in_array($name, $names, true); $n++) {
                $name = $base . ' ' . $n;
            }
            $names[] = $name;
        }

        return $names;
    }

    // Drops characters that are not allowed in XML 1.0.
    private static function clean($text)
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
    }

    private static function esc($text)
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
