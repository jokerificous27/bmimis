<?php

namespace App\Support\Spreadsheet;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first worksheet of an .xlsx file, or a .csv file, into rows keyed by the header row.
 */
class SpreadsheetReader
{
    /**
     * @return list<array<string, string>>
     */
    public function read(string $path, string $extension): array
    {
        $rows = match (strtolower($extension)) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new RuntimeException('Unsupported file type. Upload an .xlsx or .csv file.'),
        };

        $rows = array_values(array_filter($rows, fn (array $row) => implode('', array_map('trim', $row)) !== ''));

        if ($rows === []) {
            return [];
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), array_shift($rows));

        return array_map(function (array $row) use ($headers) {
            $assoc = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $assoc[$header] = trim((string) ($row[$index] ?? ''));
                }
            }

            return $assoc;
        }, $rows);
    }

    public function normalizeHeader(string $header): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))), '_');
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $rows = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $rows[] = array_map(fn ($value) => (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The uploaded file is not a valid Excel workbook.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedXml !== false) {
            $shared = $this->xml($sharedXml);

            foreach ($shared->si as $item) {
                $sharedStrings[] = $this->stringItem($item);
            }
        }

        $sheetPath = $this->firstSheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The workbook has no worksheet.');
        }

        $rows = [];

        foreach ($this->xml($sheetXml)->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];

                $values[$column] = match ($type) {
                    's' => $sharedStrings[(int) $cell->v] ?? '',
                    'inlineStr' => $this->stringItem($cell->is),
                    default => (string) $cell->v,
                };
            }

            if ($values !== []) {
                $rows[] = array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values);
            }
        }

        return $rows;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $relationships === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $firstSheet = $this->xml($workbook)->sheets->sheet[0] ?? null;
        $relationshipId = $firstSheet ? (string) $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : null;

        foreach ($this->xml($relationships)->Relationship as $relationship) {
            if ((string) $relationship['Id'] === $relationshipId) {
                $target = ltrim((string) $relationship['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function stringItem(?SimpleXMLElement $item): string
    {
        if ($item === null) {
            return '';
        }

        if (isset($item->t)) {
            return (string) $item->t;
        }

        $text = '';

        foreach ($item->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d+/', '', $reference) ?? 'A';
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function xml(string $contents): SimpleXMLElement
    {
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

        if ($xml === false) {
            throw new RuntimeException('The workbook could not be parsed.');
        }

        return $xml;
    }
}
