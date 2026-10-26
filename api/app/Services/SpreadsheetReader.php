<?php

namespace App\Services;

use App\Support\Domain;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use ZipArchive;

final class SpreadsheetReader
{
    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            Domain::fail('file', 'Use CSV, XLSX or binary XLS.');
        }
        $path = $file->getRealPath();
        if ($ext === 'csv') {
            $h = fopen($path, 'rb');
            $rows = [];
            try {
                while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
                    if (count($rows) > 1000 || count($row) > 50) {
                        Domain::fail('file', 'Maximum 1000 data rows and 50 columns.');
                    }$rows[] = array_map(fn ($v) => trim((string) $v), $row);
                }
            } finally {
                fclose($h);
            }
        } else {
            if ($ext === 'xlsx') {
                $zip = new ZipArchive;
                if ($zip->open($path) !== true) {
                    Domain::fail('file', 'Invalid workbook.');
                }try {
                    $size = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $s = $zip->statIndex($i);
                        $size += $s['size'];
                        if ($zip->numFiles > 2000 || $size > 20000000) {
                            Domain::fail('file', 'Workbook expands beyond the allowed limit.');
                        }
                    }
                } finally {
                    $zip->close();
                }
            }
            $reader = IOFactory::createReader($ext === 'xlsx' ? 'Xlsx' : 'Xls');
            if (! $reader->canRead($path)) {
                Domain::fail('file', 'File content does not match its extension.');
            }
            $info = $reader->listWorksheetInfo($path);
            if (count($info) !== 1 || $info[0]['totalRows'] > 1001 || $info[0]['totalColumns'] > 50) {
                Domain::fail('file', 'Use one sheet, up to 1000 data rows and 50 columns.');
            }
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class implements IReadFilter
            {
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= 1001 && Coordinate::columnIndexFromString($columnAddress) <= 50;
                }
            });
            $book = $reader->load($path);
            $rows = [];
            try {
                foreach ($book->getActiveSheet()->getRowIterator() as $row) {
                    $values = [];
                    foreach ($row->getCellIterator() as $cell) {
                        if ($cell->getDataType() === 'f') {
                            Domain::fail('file', 'Formula cells are not accepted. Export values only.');
                        }$values[] = trim((string) $cell->getValue());
                    }$rows[] = $values;
                }
            } finally {
                $book->disconnectWorksheets();
            }
        }
        $headers = array_shift($rows) ?? [];
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        if (! $headers || count($headers) !== count(array_unique($headers)) || in_array('', $headers, true)) {
            Domain::fail('file', 'Provide distinct nonempty column headers.');
        }
        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (strlen($value) > 5000 || ! mb_check_encoding($value, 'UTF-8')) {
                    Domain::fail('file', 'Use UTF-8 values up to 5000 bytes.');
                }
            }
        }

        return ['headers' => $headers, 'rows' => array_values(array_filter($rows, fn ($v) => count(array_filter($v, fn ($x) => $x !== '')) > 0))];
    }
}
