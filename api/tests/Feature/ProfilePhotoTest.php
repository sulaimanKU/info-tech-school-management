<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MobileTestCase;

class ProfilePhotoTest extends MobileTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->asUser($this->admin);
    }

    private function photo(string $name = 'test.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1200, 900);
    }

    private function upload(string $id, bool $guardian = false): string
    {
        return $this->post('/api/v1/files', ['purpose' => 'photo', $guardian ? 'parent_guardian_id' : 'student_id' => $id, 'file' => $this->photo()], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.path')->json('data.id');
    }

    public function test_student_create_optional_photo_and_list_detail_references(): void
    {
        $one = $this->postJson('/api/v1/students', $this->admission())->assertCreated()->json('data.id');
        $this->getJson('/api/v1/students/'.$one)->assertJsonPath('data.photo_file_id', null);
        $two = $this->post('/api/v1/students', [...$this->admission(roll: 2), 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $id = $this->getJson('/api/v1/students/'.$two)->assertOk()->json('data.photo_file_id');
        $this->assertNotNull($id);
        $this->getJson('/api/v1/students')->assertJsonFragment(['photo_file_id' => $id]);
        $response = $this->get('/api/v1/files/'.$id)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $file = DB::table('private_files')->find($id);
        $size = getimagesize(Storage::disk('local')->path($file->path));
        $this->assertLessThanOrEqual(768, max($size[0], $size[1]));
    }

    public function test_student_replace_remove_and_old_image_denial(): void
    {
        $student = $this->student();
        $old = $this->upload($student);
        $next = $this->upload($student);
        $this->assertNotEquals($old, $next);
        $this->get('/api/v1/files/'.$old)->assertNotFound();
        $this->get('/api/v1/files/'.$next)->assertOk();
        $this->deleteJson('/api/v1/students/'.$student.'/photo')->assertOk();
        $this->get('/api/v1/files/'.$next)->assertNotFound();
    }

    public function test_student_photo_parent_and_dated_staff_scope(): void
    {
        $student = $this->student();
        [$g,$parent] = $this->guardian($student);
        [$other,$otherParent] = $this->guardian($this->student($this->otherSection), '0000000000002');
        $id = $this->upload($student);
        $this->asUser($parent)->get('/api/v1/files/'.$id)->assertOk();
        $this->asUser($otherParent)->get('/api/v1/files/'.$id)->assertNotFound();
        $this->asUser($this->teacher())->get('/api/v1/files/'.$id)->assertOk();
        $this->asUser($this->teacher($this->otherSection))->get('/api/v1/files/'.$id)->assertNotFound();
        $this->asUser($parent)->post('/api/v1/files', ['purpose' => 'photo', 'student_id' => $student, 'file' => $this->photo()], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_guardian_optional_create_and_photo_create_later_replace_remove(): void
    {
        $student = $this->student();
        [$g,$parent] = $this->guardian($student);
        $this->getJson('/api/v1/guardians/'.$g)->assertJsonPath('data.photo_file_id', null);
        $old = $this->upload($g, true);
        $next = $this->upload($g, true);
        $this->get('/api/v1/files/'.$old)->assertNotFound();
        $this->getJson('/api/v1/guardians/'.$g)->assertJsonPath('data.photo_file_id', $next);
        $this->asUser($parent)->get('/api/v1/files/'.$next)->assertOk();
        $this->asUser($this->admin)->deleteJson('/api/v1/guardians/'.$g.'/photo')->assertOk();
        $this->get('/api/v1/files/'.$next)->assertNotFound();
        $id = $this->post('/api/v1/guardians', ['full_name' => 'Fictional Photo Guardian', 'cnic' => '0000000000003', 'mobile' => '+923000000003', 'mobile_verified' => '1', 'photo' => $this->photo()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->assertNotNull(DB::table('parent_guardians')->where('id', $id)->value('photo_file_id'));
    }

    public function test_guardian_photo_other_parents_and_staff_without_permission_denied(): void
    {
        [$g,$parent] = $this->guardian($this->student());
        [$g2,$parent2] = $this->guardian($this->student($this->otherSection), '0000000000002');
        $id = $this->upload($g, true);
        $this->asUser($parent2)->get('/api/v1/files/'.$id)->assertNotFound();
        $this->asUser($this->teacher())->get('/api/v1/files/'.$id)->assertForbidden();
        $this->asUser($this->user('accountant_clerk'))->get('/api/v1/files/'.$id)->assertForbidden();
        $this->asUser($parent)->deleteJson('/api/v1/guardians/'.$g.'/photo')->assertForbidden();
    }

    public function test_invalid_corrupt_extension_pdf_and_oversized_photos_rejected_without_records(): void
    {
        $student = $this->student();
        foreach ([UploadedFile::fake()->createWithContent('corrupt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=')), UploadedFile::fake()->createWithContent('bad.png', 'invalid'), UploadedFile::fake()->createWithContent('bad.pdf', '%PDF-test'), $this->photo('bad.gif'), UploadedFile::fake()->image('large.jpg')->size(6000)] as $file) {
            $this->post('/api/v1/files', ['purpose' => 'photo', 'student_id' => $student, 'file' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('private_files', 0);
        $this->post('/api/v1/students', [...$this->admission(roll: 2), 'photo' => UploadedFile::fake()->createWithContent('bad.png', 'bad')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('students', 1);
    }
}
