<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ReportRenderer
{
    private function scalar(string $key, mixed $value): string
    {
        if ($value === null) {
            return '';
        }if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (str_ends_with($key, '_minor')) {
            return 'PKR '.number_format(((int) $value) / 100, 2, '.', ',');
        }

        return (string) $value;
    }

    private function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function columns(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys((array) $row) as $key) {
                if (! in_array($key, ['id', 'kind'], true) && ! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    public function render(array $data, string $format): array
    {
        $cols = $this->columns($data['rows']);
        $rows = [];
        foreach ($data['rows'] as $row) {
            $row = (array) $row;
            $rows[] = array_map(fn ($k) => $this->scalar($k, $row[$k] ?? null), $cols);
        }
        $headers = array_map(fn ($k) => ucwords(str_replace(['_minor', '_'], ['', ' '], $k)), $cols);
        if ($format === 'csv') {
            $h = fopen('php://temp', 'w+');
            fwrite($h, "\xEF\xBB\xBF");
            foreach ([['INFO-TECH High School & Educational Campus'], ['Sangao Campus · Mardan'], [$data['title']], ...array_map(fn ($k, $v) => [$k, (string) $v], array_keys($data['meta']), array_values($data['meta'])), $headers, ...$rows] as $row) {
                fputcsv($h, array_map(fn ($v) => preg_match('/^[\s]*[=+@-]/u', (string) $v) ? "'".$v : $v, $row), ',', '"', '');
            }rewind($h);
            $out = stream_get_contents($h);
            fclose($h);

            return [$out, 'text/csv; charset=UTF-8'];
        }
        if ($format === 'xlsx') {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $all = [['INFO-TECH High School & Educational Campus'], ['Sangao Campus · Mardan'], [$data['title']], ...array_map(fn ($k, $v) => [$k, (string) $v], array_keys($data['meta']), array_values($data['meta'])), $headers, ...$rows];
            foreach ($all as $ri => $row) {
                foreach ($row as $ci => $value) {
                    $sheet->setCellValueExplicit([$ci + 1, $ri + 1], $value, DataType::TYPE_STRING);
                }
            }$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->setRightToLeft(false);
            $h = fopen('php://temp', 'w+');
            try {
                (new Xlsx($book))->save($h);
                rewind($h);
                $out = stream_get_contents($h);
            } finally {
                fclose($h);
                $book->disconnectWorksheets();
            }

            return [$out, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        }
        $temp = storage_path('app/private/pdf-tmp');
        if (! is_dir($temp)) {
            mkdir($temp, 0700, true);
        }
        $isCard = $data['title'] === 'Student Id';
        $pdf = new Mpdf(['tempDir' => $temp, 'default_font' => 'dejavusans', 'format' => $isCard ? [86, 54] : (count($cols) > 7 ? 'A4-L' : 'A4'), 'margin_left' => $isCard ? 4 : 15, 'margin_right' => $isCard ? 4 : 15, 'margin_top' => $isCard ? 3 : 16, 'margin_bottom' => $isCard ? 3 : 16, 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $logo = realpath(base_path('../mobile/assets/1.jpeg'));
        if ($isCard) {
            $row = (array) ($data['rows'][0] ?? []);
            $photoHtml = '';
            if ($data['photo_file_id']) {
                $f = DB::table('private_files')->where('id', $data['photo_file_id'])->where('purpose', 'photo')->first();
                if ($f && str_starts_with($f->mime, 'image/')) {
                    $path = Storage::disk('local')->path($f->path);
                    if (is_file($path)) {
                        $photoHtml = '<img src="'.$this->e($path).'" style="width:18mm;max-height:24mm">';
                    }
                }
            }
            $html = '<style>body{font-size:7pt;color:#142b49}table{width:100%;border-collapse:collapse}td{vertical-align:top}h1{font-size:10pt;margin:0}h2{font-size:11pt;margin:3mm 0 1mm}small{font-size:6pt;color:#526577}</style><table><tr><td style="width:13mm">'.($logo ? '<img src="'.$this->e($logo).'" style="width:11mm">' : '').'</td><td><h1>INFO-TECH</h1>High School &amp; Educational Campus<br><small>Sangao Campus · Mardan</small></td></tr></table><hr style="color:#087f8c"><table><tr><td><small>STUDENT IDENTITY CARD</small><h2>'.$this->e($row['full_name'] ?? '').'</h2><b>'.$this->e($row['registration_number'] ?? '').'</b><br>'.$this->e($row['class'] ?? '').' · '.$this->e($row['section'] ?? '').'<br>Roll: '.$this->e($row['roll_number'] ?? '').'</td><td style="width:20mm;text-align:right">'.$photoHtml.'</td></tr></table><p><small>Issued '.date('Y-m-d').' · School record, verify with office</small></p>';
            $pdf->SetTitle('INFO-TECH Student ID');
            $pdf->WriteHTML($html);

            return [$pdf->Output('', 'S'), 'application/pdf'];
        }
        $html = '<style>body{font-size:10pt;color:#142b49}h1{font-size:16pt}h2{color:#087f8c}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccd7df;padding:6px;vertical-align:top}th{background:#eaf3f6}small{color:#526577}</style>';
        if ($logo) {
            $html .= '<img src="'.$this->e($logo).'" style="width:70px;float:left;margin-right:15px">';
        }
        $html .= '<h1>INFO-TECH High School &amp; Educational Campus</h1><p>Sangao Campus · Mardan</p><h2>'.$this->e($data['title']).'</h2>';
        foreach ($data['meta'] as $k => $v) {
            $html .= '<p><b>'.$this->e($k).':</b> '.$this->e($v).'</p>';
        }
        if ($data['photo_file_id']) {
            $photo = DB::table('private_files')->where('id', $data['photo_file_id'])->where('purpose', 'photo')->first();
            if ($photo && str_starts_with($photo->mime, 'image/')) {
                $path = Storage::disk('local')->path($photo->path);
                if (is_file($path)) {
                    $html .= '<img src="'.$this->e($path).'" style="height:100px">';
                }
            }
        }
        if (! $rows) {
            $html .= '<p>No authorized records for this selection.</p>';
        } else {
            $html .= '<table><thead><tr>';
            foreach ($headers as $h) {
                $html .= '<th>'.$this->e($h).'</th>';
            }$html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>'.$this->e($value).'</td>';
                }$html .= '</tr>';
            }$html .= '</tbody></table>';
        }
        $pdf->SetTitle('INFO-TECH '.$data['title']);
        $pdf->SetHTMLFooter('<small>Private school record · INFO-TECH · Page {PAGENO} / {nbpg}</small>');
        $pdf->WriteHTML($html);

        return [$pdf->Output('', 'S'), 'application/pdf'];
    }
}
