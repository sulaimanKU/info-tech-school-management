<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MobileTestCase;

class PrivateFileTest extends MobileTestCase
{
    public function test_private_photo_authorization_and_invalid_upload(): void
    {
        Storage::fake('local');
        $one = $this->student();
        $other = $this->student($this->otherSection);
        [$g,$parent] = $this->guardian($one);
        $this->asUser($this->admin);
        // Decode-valid fixture: signature-only/corrupt PNGs must now be rejected.
        $upload = UploadedFile::fake()->image('photo.png', 32, 32);
        $id = $this->post('/api/v1/files', ['file' => $upload, 'student_id' => $one, 'purpose' => 'photo'], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->asUser($parent)->get('/api/v1/files/'.$id)->assertOk();
        $this->asUser($this->teacher($this->otherSection))->get('/api/v1/files/'.$id)->assertNotFound();
        $this->asUser($this->admin)->post('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('attack.png', '<script>bad</script>'), 'student_id' => $one, 'purpose' => 'photo'], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_teachers_cannot_download_admission_documents_or_financial_evidence(): void
    {
        Storage::fake('local');
        $student = $this->student();
        $teacher = $this->teacher();
        $this->asUser($this->admin);
        $id = $this->post('/api/v1/files', ['file' => UploadedFile::fake()->createWithContent('fictional.pdf', "%PDF-1.4\nfictional test document"), 'student_id' => $student, 'purpose' => 'document'], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->asUser($teacher)->get('/api/v1/files/'.$id, ['Accept' => 'application/json'])->assertForbidden();
    }
}
