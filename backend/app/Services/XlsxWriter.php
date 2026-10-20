<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Minimal, dependency-free .xlsx writer. Produces a real Office Open XML
 * workbook (one worksheet per sheet) using only PHP's zip + xml support,
 * so reports can be exported to Excel without pulling in a spreadsheet
 * package. Every sheet's first row is rendered bold as a header row.
 */
class XlsxWriter
{
    /**
     * @param  array<int, array{name: string, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public function __construct(private readonly array $sheets) {}

    /** Write the workbook to the given file path. */
    public function save(string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the spreadsheet archive.');
        }

        $count = count($this->sheets);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes($count));
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels($count));
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($i + 1).'.xml',
                $this->worksheet($sheet['rows'])
            );
        }

        $zip->close();
    }

    private function contentTypes(int $count): string
    {
        $overrides = '';

        for ($i = 1; $i <= $count; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml"'
                .' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';

        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<sheet name="'.$this->escapeSheetName($sheet['name']).'"'
                .' sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private function workbookRels(int $count): string
    {
        $rels = '';

        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'"'
                .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                .' Target="worksheets/sheet'.$i.'.xml"/>';
        }

        $rels .= '<Relationship Id="rId'.($count + 1).'"'
            .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            .' Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="2">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'</fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function worksheet(array $rows): string
    {
        $body = '';
        $rowNumber = 0;

        foreach ($rows as $row) {
            $rowNumber++;
            $cells = '';
            $column = 0;

            foreach ($row as $value) {
                $cells .= $this->cell($this->columnLetter($column).$rowNumber, $value, $rowNumber === 1);
                $column++;
            }

            $body .= '<row r="'.$rowNumber.'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$body.'</sheetData>'
            .'</worksheet>';
    }

    private function cell(string $ref, mixed $value, bool $header): string
    {
        $style = $header ? ' s="1"' : '';

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
        }

        if ($value === null || $value === '') {
            return '<c r="'.$ref.'"'.$style.'/>';
        }

        return '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'
            .$this->escape((string) $value)
            .'</t></is></c>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr(65 + ($i % 26)).$letter;
        }

        return $letter;
    }

    private function escapeSheetName(string $name): string
    {
        $name = str_replace(['\\', '/', '?', '*', ':', '[', ']'], ' ', $name);

        return $this->escape(mb_substr($name, 0, 31));
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
