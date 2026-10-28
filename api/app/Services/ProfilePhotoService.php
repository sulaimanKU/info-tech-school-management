<?php

namespace App\Services;

use App\Models\User;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class ProfilePhotoService
{
    public function guardian(User $user, string $id, bool $write = false): object
    {
        $access = app(SchoolAccess::class);
        if (! $write && $access->isParent($user)) {
            return DB::table('parent_guardians')->where('id', $id)->where('user_id', $user->id)->whereNotNull('mobile_verified_at')->firstOrFail();
        }
        $access->require($user, 'guardians.manage');
        $q = DB::table('parent_guardians')->where('id', $id);
        if (! $user->hasPermission('students.read.all')) {
            $q->where(fn ($q) => $q->where('verified_by', $user->id)->orWhereIn('id', DB::table('parent_student_links')->whereIn('student_id', $access->students($user)->select('students.id'))->select('parent_guardian_id')));
        }

        return $q->firstOrFail();
    }

    public function validate(UploadedFile $file): void
    {
        Validator::make(['photo' => $file], ['photo' => 'required|file|image|mimetypes:image/jpeg,image/png,image/webp|extensions:jpg,jpeg,png,webp|max:'.config('school.upload_max_kb')])->validate();
        $d = @getimagesize($file->getRealPath());
        if (! $d || $d[0] * $d[1] > 24000000) {
            Domain::fail('photo', 'Select a valid image no larger than 24 megapixels.');
        }
    }

    public function save(User $user, UploadedFile $file, string $id, bool $guardian = false, bool $staff = false): array
    {
        $access = app(SchoolAccess::class);
        if ($staff) {
            abort_unless($user->isStaff(), 403);
            abort_unless($id === $user->id, 403);
        } elseif ($guardian) {
            $this->guardian($user, $id, true);
        } else {
            $access->require($user, 'students.manage');
            $access->student($user, $id);
        }
        $this->validate($file);
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            Domain::fail('photo', 'The image could not be decoded.');
        }
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1, 768 / max($w, $h));
        $target = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $w, $h);
        ob_start();
        imagejpeg($target, null, 84);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);
        $path = 'school/photos/'.Str::uuid().'.jpg';
        abort_unless(Storage::disk('local')->put($path, $bytes), 503);
        try {
            $fileId = DB::transaction(function () use ($user, $id, $guardian, $staff, $path, $bytes) {
                $table = $staff ? 'users' : ($guardian ? 'parent_guardians' : 'students');
                DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
                $fileId = Domain::insert('private_files', ['uploaded_by' => $user->id, 'student_id' => ($guardian || $staff) ? null : $id, 'parent_guardian_id' => $guardian ? $id : null, 'owner_user_id' => $staff ? $id : null, 'path' => $path, 'mime' => 'image/jpeg', 'purpose' => 'photo', 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)]);
                DB::table($table)->where('id', $id)->update(['photo_file_id' => $fileId, 'updated_at' => now()]);
                Audit::record('profile_photo_saved', $user->id, $table, $id);

                return $fileId;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return ['id' => $fileId, 'mime' => 'image/jpeg', 'size' => strlen($bytes)];
    }

    public function remove(User $user, string $id, bool $guardian = false): void
    {
        if ($guardian) {
            $this->guardian($user, $id, true);
        } else {
            app(SchoolAccess::class)->require($user, 'students.manage');
            app(SchoolAccess::class)->student($user, $id);
        }
        DB::transaction(function () use ($user, $id, $guardian) {
            $table = $guardian ? 'parent_guardians' : 'students';
            DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
            DB::table($table)->where('id', $id)->update(['photo_file_id' => null, 'updated_at' => now()]);
            Audit::record('profile_photo_removed', $user->id, $table, $id);
        });
    }
}
