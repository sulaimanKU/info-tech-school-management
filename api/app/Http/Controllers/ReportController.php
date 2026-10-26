<?php

namespace App\Http\Controllers;

use App\Services\ReportData;
use App\Services\ReportRenderer;
use App\Support\Audit;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function show(Request $r, string $type, ReportData $source, ReportRenderer $renderer)
    {
        abort_unless(in_array($type, ReportData::TYPES, true), 404);
        $v = $r->validate(['format' => 'sometimes|in:pdf,csv,xlsx']);
        $format = $v['format'] ?? 'pdf';
        $data = $source->get($r, $type);
        [$bytes,$mime] = $renderer->render($data, $format);
        Audit::record('report_downloaded', $r->user()->id, null, null, ['type' => $type, 'count' => count($data['rows'])]);

        return response($bytes, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'attachment; filename="infotech-'.$type.'.'.$format.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
