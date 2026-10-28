<?php

namespace App\Http\Controllers;

use App\Services\ImportDispatcher;
use App\Services\SchoolAccess;
use App\Services\SpreadsheetReader;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ImportController extends Controller
{
    public function __construct(private SchoolAccess $access, private ImportDispatcher $dispatcher) {}

    private function authorize(Request $r): void
    {
        $this->access->require($r->user(), 'imports.manage');
    }

    private function batch(Request $r, string $id, bool $lock = false)
    {
        $this->authorize($r);
        $q = DB::table('import_batches')->where('id', $id)->when(! $r->user()->isSuperAdmin(), fn ($q) => $q->where('created_by', $r->user()->id));
        if ($lock) {
            $q->lockForUpdate();
        }$b = $q->firstOrFail();
        abort_if($b->status !== 'confirmed' && now()->greaterThan($b->expires_at), 410);

        return $b;
    }

    public function index(Request $r)
    {
        $this->authorize($r);

        return Domain::response(['kinds' => ImportDispatcher::FIELDS, 'items' => DB::table('import_batches')->when(! $r->user()->isSuperAdmin(), fn ($q) => $q->where('created_by', $r->user()->id))->orderByDesc('created_at')->limit(50)->get(['id', 'kind', 'status', 'report', 'created_at', 'expires_at'])]);
    }

    public function upload(Request $r)
    {
        $this->authorize($r);
        $v = $r->validate(['kind' => 'required|in:'.implode(',', array_keys(ImportDispatcher::FIELDS)), 'file' => 'required|file|max:5120']);
        $rows = app(SpreadsheetReader::class)->read($v['file']);
        if (! $rows['rows']) {
            Domain::fail('file', 'No data rows.');
        }$hash = hash('sha256', json_encode($rows));
        $id = DB::transaction(function () use ($r, $v, $rows, $hash) {
            $old = DB::table('import_batches')->when(! $r->user()->isSuperAdmin(), fn ($q) => $q->where('created_by', $r->user()->id))->where('kind', $v['kind'])->where('fingerprint', $hash)->first();
            if ($old) {
                if ($old->status !== 'confirmed' && now()->greaterThan($old->expires_at)) {
                    DB::table('import_batches')->where('id', $old->id)->update(['source_rows' => json_encode($rows, JSON_THROW_ON_ERROR), 'status' => 'uploaded', 'mapping' => null, 'report' => null, 'expires_at' => now()->addDay(), 'updated_at' => now()]);
                }

                return $old->id;
            }$id = Domain::insert('import_batches', ['created_by' => $r->user()->id, 'kind' => $v['kind'], 'fingerprint' => $hash, 'source_rows' => json_encode($rows, JSON_THROW_ON_ERROR), 'expires_at' => now()->addDay()]);
            Audit::record('import_uploaded', $r->user()->id, 'import_batches', $id, ['count' => count($rows['rows'])]);

            return $id;
        });

        return Domain::response(['id' => $id, 'headers' => $rows['headers'], 'row_count' => count($rows['rows']), 'fields' => ImportDispatcher::FIELDS[$v['kind']]], 201);
    }

    private function mapped(object $b, array $mapping): array
    {
        $raw = json_decode($b->source_rows, true);
        $allowed = ImportDispatcher::FIELDS[$b->kind];
        foreach ($mapping as $field => $header) {
            if (! in_array($field, $allowed, true) || ! in_array($header, $raw['headers'], true)) {
                Domain::fail('mapping', 'Unknown field or column.');
            }
        }$rows = [];
        $seen = [];
        foreach ($raw['rows'] as $i => $row) {
            $v = [];
            foreach ($mapping as $field => $header) {
                $value = $row[array_search($header, $raw['headers'], true)] ?? '';
                if ($value !== '') {
                    $v[$field] = $value;
                }
            }$hash = hash('sha256', json_encode($v));
            if (isset($seen[$hash])) {
                Domain::fail('rows', 'Duplicate rows in this upload.');
            }$seen[$hash] = true;
            $rows[] = $v;
        }

        return $rows;
    }

    public function show(Request $r, string $batch)
    {
        $b = $this->batch($r, $batch);
        $raw = $b->source_rows ? json_decode($b->source_rows, true) : null;
        $preview = $raw ? array_slice($raw['rows'], 0, 10) : [];
        if ($raw) {
            foreach ($preview as &$row) {
                foreach ($row as &$value) {
                    if (preg_match('/\d{13}/', preg_replace('/[\s-]/', '', $value))) {
                        $value = '[identity masked]';
                    }
                }
            }
        }

        return Domain::response(['id' => $b->id, 'kind' => $b->kind, 'status' => $b->status, 'mapping' => json_decode($b->mapping ?? '{}', true), 'report' => json_decode($b->report ?? 'null', true), 'headers' => $raw['headers'] ?? [], 'preview' => $preview, 'fields' => ImportDispatcher::FIELDS[$b->kind]]);
    }

    public function preview(Request $r, string $batch)
    {
        return DB::transaction(function () use ($r, $batch) {
            $b = $this->batch($r, $batch, true);
            abort_if($b->status === 'confirmed', 409);
            $v = $r->validate(['mapping' => 'required|array|min:1|max:30']);
            $rows = $this->mapped($b, $v['mapping']);
            $level = DB::transactionLevel();
            $report = ['valid' => true, 'row_count' => count($rows), 'warnings' => ['Confirmation revalidates every row atomically. Existing data is never silently overwritten.'], 'errors' => []];
            DB::beginTransaction();
            try {
                $this->dispatcher->apply($r->user(), $b->kind, $rows, $batch);
            } catch (ValidationException $e) {
                $report['valid'] = false;
                $report['errors'] = array_keys($e->errors());
            } catch (\Throwable $e) {
                $report['valid'] = false;
                $report['errors'] = ['authorization_conflict_or_reference'];
            } finally {
                while (DB::transactionLevel() > $level) {
                    DB::rollBack();
                }
            }DB::table('import_batches')->where('id', $batch)->update(['mapping' => json_encode($v['mapping']), 'report' => json_encode($report), 'status' => $report['valid'] ? 'validated' : 'invalid', 'updated_at' => now()]);
            Audit::record('import_previewed', $r->user()->id, 'import_batches', $batch, ['count' => count($rows)]);

            return Domain::response($report);
        });
    }

    public function confirm(Request $r, string $batch)
    {
        $r->validate(['confirm' => 'required|accepted']);

        return DB::transaction(function () use ($r, $batch) {
            $b = $this->batch($r, $batch, true);
            if ($b->status === 'confirmed') {
                return Domain::response(['confirmed' => true, 'report' => json_decode($b->report, true)]);
            }abort_unless($b->status === 'validated', 409);
            $rows = $this->mapped($b, json_decode($b->mapping, true));
            $ids = $this->dispatcher->apply($r->user(), $b->kind, $rows, $batch);
            $report = ['valid' => true, 'row_count' => count($rows), 'records' => $ids];
            DB::table('import_batches')->where('id', $batch)->update(['status' => 'confirmed', 'source_rows' => null, 'report' => json_encode($report), 'updated_at' => now()]);
            Audit::record('import_confirmed', $r->user()->id, 'import_batches', $batch, ['count' => count($rows)]);

            return Domain::response(['confirmed' => true, 'report' => $report]);
        });
    }

    public function destroy(Request $r, string $batch)
    {
        $this->authorize($r);

        return DB::transaction(function () use ($r, $batch) {
            $b = DB::table('import_batches')->where('id', $batch)->when(! $r->user()->isSuperAdmin(), fn ($q) => $q->where('created_by', $r->user()->id))->lockForUpdate()->firstOrFail();
            abort_if($b->status === 'confirmed', 409);
            DB::table('import_batches')->where('id', $batch)->delete();
            Audit::record('import_staging_removed', $r->user()->id, 'import_batches', $batch);

            return Domain::response(['removed' => true]);
        });
    }

    public function mappings(Request $r)
    {
        $this->authorize($r);

        return Domain::response(['items' => DB::table('biometric_mappings as m')->join('students as s', 's.id', '=', 'm.student_id')->whereIn('s.id', $this->access->students($r->user())->select('students.id'))->orderBy('m.device_code')->get(['m.id', 'm.device_code', 'm.external_id', 'm.student_id', 's.registration_number', 's.full_name'])]);
    }

    public function revokeMapping(Request $r, string $mapping)
    {
        $this->authorize($r);
        $this->access->require($r->user(), 'students.manage');
        $old = DB::table('biometric_mappings')->where('id', $mapping)->firstOrFail();
        $this->access->student($r->user(), $old->student_id);
        DB::table('biometric_mappings')->where('id', $mapping)->delete();
        Audit::record('biometric_mapping_revoked', $r->user()->id, 'biometric_mappings', $mapping);

        return Domain::response(['revoked' => true]);
    }

    public function mapping(Request $r)
    {
        $this->authorize($r);
        $this->access->require($r->user(), 'students.manage');
        $v = $r->validate(['device_code' => 'required|string|max:80', 'external_id' => 'required|string|max:80', 'student_id' => 'required|uuid|exists:students,id']);
        $this->access->student($r->user(), $v['student_id']);
        if (DB::table('biometric_mappings')->where('device_code', $v['device_code'])->where('external_id', $v['external_id'])->exists()) {
            Domain::fail('external_id', 'Mapping already exists; revoke it explicitly before reassignment.');
        }$id = Domain::insert('biometric_mappings', [...$v, 'created_by' => $r->user()->id]);
        Audit::record('biometric_mapping_created', $r->user()->id, 'biometric_mappings', $id);

        return Domain::response(['id' => $id], 201);
    }

    public function biometric(Request $r)
    {
        $this->authorize($r);
        $v = $r->validate(['month' => 'required|date_format:Y-m']);
        // Punches are stored as UTC wall time. Convert in PHP: no database timezone tables required.
        $start = \Carbon\Carbon::createFromFormat('!Y-m', $v['month'], 'Asia/Karachi')->utc();
        $end = $start->copy()->timezone('Asia/Karachi')->addMonth()->utc();
        $punches = DB::table('biometric_punches')->whereIn('student_id', $this->access->students($r->user())->select('students.id'))
            ->where('punched_at', '>=', $start)->where('punched_at', '<', $end)->orderBy('punched_at')->get();
        $days = $punches->groupBy(fn ($p) => $p->student_id.'/'.\Carbon\Carbon::parse($p->punched_at, 'UTC')->timezone('Asia/Karachi')->toDateString())
            ->map(function ($rows) {
                $first = $rows->first();
                $in = $rows->where('direction', 'in');
                $out = $rows->where('direction', 'out');

                return ['student_id' => $first->student_id, 'date' => \Carbon\Carbon::parse($first->punched_at, 'UTC')->timezone('Asia/Karachi')->toDateString(),
                    'first_punch' => $first->punched_at, 'last_punch' => $rows->last()->punched_at, 'punch_count' => $rows->count(),
                    'in_count' => $in->count(), 'out_count' => $out->count(), 'first_in' => $in->first()?->punched_at, 'last_out' => $out->last()?->punched_at,
                    'needs_review' => $in->count() !== 1 || $out->count() !== 1 || $in->first()?->punched_at >= $out->last()?->punched_at, 'attendance_submitted' => false];
            })->sortBy('date')->values();

        return Domain::response(['items' => $days, 'notice' => 'Punches are evidence only. Review missing/conflicting punches and submit attendance explicitly through the attendance register. No absence is inferred from a missing punch.']);
    }
}
