<?php

namespace App\Support\Spreadsheet;

use RuntimeException;
use ZipArchive;

/**
 * Minimal Office Open XML (.xlsx) writer: one or more worksheets of plain rows with a bold header row.
 * Uses PHP's zip extension only, so no additional dependency is required.
 */
class XlsxWriter
{
    /**
     * @var list<array{name: string, title: string|null, headers: list<string>, rows: list<list<mixed>>, plain: bool, width: float}>
     */
    private array $sheets = [];

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public function addSheet(string $name, array $headers, iterable $rows, ?string $title = null): self
    {
        $this->sheets[] = [
            'name' => mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $name) ?: 'Sheet', 0, 31),
            'title' => $title,
            'headers' => $headers,
            'rows' => is_array($rows) ? array_values($rows) : iterator_to_array($rows, false),
            'plain' => false,
            'width' => 20.0,
        ];

        return $this;
    }

    /**
     * A data sheet for import into another system: the header in row 1 without any styling, then the
     * rows. No title row.
     *
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public function addPlainSheet(string $name, array $headers, iterable $rows, float $columnWidth = 20.0): self
    {
        $this->addSheet($name, $headers, $rows);
        $last = array_key_last($this->sheets);
        $this->sheets[$last]['plain'] = true;
        $this->sheets[$last]['width'] = $columnWidth;

        return $this;
    }

    /**
     * Write the workbook to a temporary file and return its path.
     */
    public function save(?string $path = null): string
    {
        if ($this->sheets === []) {
            $this->addSheet('Sheet1', [], []);
        }

        $path ??= tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the Excel file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet));
        }

        $zip->close();

        return $path;
    }

    private function contentTypes(): string
    {
        $overrides = '';

        foreach (array_keys($this->sheets) as $index) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';

        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        $relationships = '';

        foreach (array_keys($this->sheets) as $index) {
            $relationships .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }

        $relationships .= '<Relationship Id="rId'.(count($this->sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="13"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1D3461"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @param  array{name: string, title: string|null, headers: list<string>, rows: list<list<mixed>>}  $sheet
     */
    private function worksheet(array $sheet): string
    {
        $xml = '';
        $rowNumber = 1;

        if ($sheet['title']) {
            $xml .= $this->row($rowNumber++, [$sheet['title']], 2);
            $rowNumber++;
        }

        if ($sheet['headers'] !== []) {
            $xml .= $this->row($rowNumber++, $sheet['headers'], $sheet['plain'] ? 0 : 1);
        }

        foreach ($sheet['rows'] as $row) {
            $xml .= $this->row($rowNumber++, array_values($row));
        }

        $columns = max(count($sheet['headers']), 1);
        $cols = '<cols><col min="1" max="'.$columns.'" width="'.$sheet['width'].'" customWidth="1"/></cols>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$cols.'<sheetData>'.$xml.'</sheetData></worksheet>';
    }

    /**
     * @param  list<mixed>  $values
     */
    private function row(int $number, array $values, int $style = 0): string
    {
        $cells = '';

        foreach ($values as $column => $value) {
            $reference = $this->columnLetter($column).$number;
            $styleAttribute = $style ? ' s="'.$style.'"' : '';

            if ($value === null || $value === '') {
                continue;
            }

            if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
                $cells .= '<c r="'.$reference.'"'.$styleAttribute.'><v>'.$value.'</v></c>';

                continue;
            }

            $text = $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
            $cells .= '<c r="'.$reference.'" t="inlineStr"'.$styleAttribute.'><is><t xml:space="preserve">'.$this->escape($text).'</t></is></c>';
        }

        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letter = chr(65 + ($index - 1) % 26).$letter;
        }

        return $letter;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        // Neutralize spreadsheet formula injection from user-entered text.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) && ! is_numeric($value)) {
            $value = "'".$value;
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
