<?php

namespace App\Http\Controllers;

use App\Services\AcademicCalendar;
use App\Services\ProfilePhotoService;
use App\Services\SchoolAccess;
use App\Services\TicketAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PrivateFileController extends Controller
{
    public function __construct(private SchoolAccess $access) {}

    public function store(Request $r)
    {
        if ($r->input('purpose') === 'photo') {
            $v = $r->validate(['file' => 'required|file', 'student_id' => 'nullable|uuid', 'parent_guardian_id' => 'nullable|uuid']);
            abort_unless(! empty($v['student_id']) xor ! empty($v['parent_guardian_id']), 422);

            return Domain::response(app(ProfilePhotoService::class)->save($r->user(), $r->file('file'), $v['student_id'] ?? $v['parent_guardian_id'], ! empty($v['parent_guardian_id'])), 201);
        }
        $v = $r->validate(['file' => 'required|file|mimetypes:image/jpeg,image/png,image/webp,application/pdf|max:'.config('school.upload_max_kb'),
            'student_id' => 'nullable|uuid|exists:students,id', 'section_id' => 'nullable|uuid|exists:sections,id', 'purpose' => 'required|in:photo,diary,message,payment_proof,receipt,document,expense_proof,payroll_document']);
        if ($this->access->isParent($r->user())) {
            abort_unless(in_array($v['purpose'], ['message', 'payment_proof']), 403);
        }
        if ($v['student_id'] ?? null) {
            $this->access->student($r->user(), $v['student_id']);
        }
        if ($v['section_id'] ?? null) {
            $this->access->section($r->user(), $v['section_id']);
        }
        if ($v['purpose'] === 'diary') {
            $this->access->academic($r->user());
        }
        if (in_array($v['purpose'], ['expense_proof', 'payroll_document'])) {
            $this->access->require($r->user(), 'finance.write');
        }
        if (! ($v['student_id'] ?? null) && ! ($v['section_id'] ?? null) && ! in_array($v['purpose'], ['expense_proof', 'payroll_document'])) {
            abort(422);
        }
        if ($v['purpose'] === 'document') {
            $this->access->require($r->user(), 'students.manage');
        }
        if (in_array($v['purpose'], ['receipt', 'payroll_document', 'expense_proof']) || ($v['purpose'] === 'payment_proof' && ! $this->access->isParent($r->user()))) {
            $this->access->require($r->user(), 'finance.write');
        }
        $file = $r->file('file');
        $mime = $file->getMimeType();
        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($file->getRealPath());
            abort_unless($dimensions && $dimensions[0] * $dimensions[1] <= 24000000, 422);
        }
        $path = $file->store('school/'.date('Y/m'), 'local');
        try {
            $id = DB::transaction(function () use ($r, $v, $mime, $path, $file) {
                $id = Domain::insert('private_files', ['uploaded_by' => $r->user()->id, 'student_id' => $v['student_id'] ?? null, 'section_id' => $v['section_id'] ?? null, 'path' => $path, 'mime' => $mime, 'purpose' => $v['purpose'], 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())]);
                Audit::record('private_file_uploaded', $r->user()->id, 'private_files', $id);

                return $id;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return Domain::response(['id' => $id, 'mime' => $mime, 'size' => $file->getSize()], 201);
    }

    public function removeStudentPhoto(Request $r, string $student)
    {
        app(ProfilePhotoService::class)->remove($r->user(), $student);

        return Domain::response(['saved' => true]);
    }

    public function removeGuardianPhoto(Request $r, string $guardian)
    {
        app(ProfilePhotoService::class)->remove($r->user(), $guardian, true);

        return Domain::response(['saved' => true]);
    }

    public function show(Request $r, string $file)
    {
        $f = DB::table('private_files')->where('id', $file)->firstOrFail();
        if ($f->purpose === 'photo') {
            if ($f->parent_guardian_id) {
                $owner = app(ProfilePhotoService::class)->guardian($r->user(), $f->parent_guardian_id);
            } else {
                $owner = $this->access->student($r->user(), $f->student_id);
            }
            abort_unless($owner->photo_file_id === $file, 404);
        }
        if ($f->purpose === 'diary') {
            $this->access->academic($r->user());
        }
        if ($f->student_id) {
            $this->access->student($r->user(), $f->student_id);
        } elseif ($f->section_id) {
            if ($this->access->isParent($r->user())) {
                $ids = $this->access->students($r->user())->pluck('students.id');
                abort_unless(DB::table('enrollments')->whereIn('student_id', $ids)->where('section_id', $f->section_id)->exists(), 404);
                // Class files become parent-visible only through published diary references (added by academic module).
                $visibleDiary = (new DiaryController(app(SchoolAccess::class), app(AcademicCalendar::class)))->visible($r)->select('d.id');
                abort_unless(DB::table('diary_attachments')->where('private_file_id', $file)->whereIn('diary_entry_id', $visibleDiary)->exists(), 404);
            } else {
                $this->access->section($r->user(), $f->section_id);
            }
        } elseif (! $f->parent_guardian_id) {
            abort_unless(in_array($f->purpose, ['expense_proof', 'payroll_document']), 404);
            $this->access->require($r->user(), 'finance.read');
        }
        if (in_array($f->purpose, ['payment_proof', 'receipt']) && $f->uploaded_by !== $r->user()->id) {
            $this->access->require($r->user(), 'finance.read');
        }

        if ($f->purpose === 'document') {
            $this->access->require($r->user(), 'students.manage');
        }
        if (in_array($f->purpose, ['expense_proof', 'payroll_document'])) {
            $this->access->require($r->user(), 'finance.read');
        }
        $sharedMessage = false;
        if ($f->purpose === 'message' && $f->uploaded_by !== $r->user()->id) {
            $tickets = app(TicketAccess::class)->query($r->user())->select('t.id');
            $messages = DB::table('support_message_attachments as a')->join('support_messages as m', 'm.id', '=', 'a.support_message_id')->where('a.private_file_id', $file)->whereIn('m.support_ticket_id', $tickets);
            if ($this->access->isParent($r->user())) {
                $messages->where('m.is_internal', false);
            }
            abort_unless($messages->exists(), 404);
            $sharedMessage = true;
        }
        if ($f->purpose === 'diary' && $f->uploaded_by !== $r->user()->id && ! $r->user()->hasPermission('academics.review')) {
            $visibleDiary = (new DiaryController(app(SchoolAccess::class), app(AcademicCalendar::class)))->visible($r)->where('d.status', 'published')->select('d.id');
            abort_unless(DB::table('diary_attachments')->where('private_file_id', $file)->whereIn('diary_entry_id', $visibleDiary)->exists(), 404);
        }
        // Student-linked evidence/documents require uploader or privileged staff; parents only see photos here.
        if ($this->access->isParent($r->user()) && $f->student_id && $f->purpose !== 'photo' && ! $sharedMessage) {
            abort_unless($f->uploaded_by === $r->user()->id, 404);
        }
        Audit::record('private_file_accessed', $r->user()->id, 'private_files', $file);
        abort_unless(Storage::disk('local')->exists($f->path), 404);

        return Storage::disk('local')->response($f->path, $file.($f->mime === 'application/pdf' ? '.pdf' : '.image'), ['Content-Type' => $f->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"], $f->mime === 'application/pdf' ? 'attachment' : 'inline');
    }
}
