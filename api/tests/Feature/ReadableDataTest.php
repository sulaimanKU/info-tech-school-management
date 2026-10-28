<?php

namespace Tests\Feature;

use App\Contracts\SmsProvider;
use App\Services\GuardianIdentity;
use App\Support\Domain;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\MobileTestCase;

class ReadableDataTest extends MobileTestCase
{
    private function legacy(string $id): void
    {
        $g = DB::table('parent_guardians')->find($id);
        DB::table('parent_guardians')->where('id', $id)->update([
            'cnic_ciphertext' => Crypt::encryptString($g->cnic),
            'mobile_ciphertext' => Crypt::encryptString($g->mobile),
            'cnic_lookup' => app(GuardianIdentity::class)->lookup($g->cnic),
            'cnic_last4' => substr($g->cnic, -4), 'cnic' => null, 'mobile' => null,
        ]);
    }

    public function test_conversion_preserves_relationships_and_provider_target_and_parent_login(): void
    {
        $student = $this->student();
        [$id, $parent] = $this->guardian($student);
        $links = DB::table('parent_student_links')->where('parent_guardian_id', $id)->get()->toJson();
        $this->legacy($id);
        $rows = json_encode(['headers' => ['full_name'], 'rows' => [['Fictional Guardian']]]);
        $batch = Domain::insert('import_batches', ['created_by' => $this->admin->id, 'kind' => 'parents', 'fingerprint' => str_repeat('a', 64), 'encrypted_rows' => Crypt::encryptString($rows), 'expires_at' => now()->addDay()]);
        $this->artisan('school:convert-readable-data --check')->assertSuccessful();
        $this->assertNull(DB::table('parent_guardians')->find($id)->cnic);
        $this->artisan('school:convert-readable-data')->assertSuccessful();
        $this->artisan('school:convert-readable-data')->assertSuccessful();
        $this->assertDatabaseHas('parent_guardians', ['id' => $id, 'user_id' => $parent->id, 'cnic' => '0000000000001', 'mobile' => '+923000000001']);
        $this->assertSame($rows, DB::table('import_batches')->find($batch)->source_rows);
        $this->assertSame($links, DB::table('parent_student_links')->where('parent_guardian_id', $id)->get()->toJson());
        $this->assertTrue(Hash::check('fictional-password-only', $this->admin->fresh()->password));
        $this->assertNotSame('fictional-password-only', $this->admin->fresh()->password);
        $provider = \Mockery::mock(SmsProvider::class);
        $challenge = $code = null;
        $provider->shouldReceive('sendOtp')->once()->withArgs(function ($challengeId, $mobile, $otp) use (&$challenge, &$code) {
            $challenge = $challengeId;
            $code = $otp;

            return $mobile === '+923000000001';
        });
        $this->app->instance(SmsProvider::class, $provider);
        $this->postJson('/api/v1/parent-auth/request', ['cnic' => '00000-0000000-1'])->assertStatus(202)->assertDontSee('+923000000001');
        $hash = DB::table('otp_challenges')->find($challenge)->otp_hash;
        $this->assertNotSame($code, $hash);
        $this->assertTrue(Hash::check($code, $hash));
        $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $challenge, 'otp' => $code])->assertOk()->assertJsonPath('data.user.id', $parent->id);
        $this->asUser($parent)->getJson('/api/v1/guardians/'.$id)->assertForbidden();
        $this->asUser($this->admin)->getJson('/api/v1/guardians/'.$id)->assertOk()->assertJsonMissingPath('data.cnic')->assertJsonMissingPath('data.mobile')->assertDontSee('+923000000001');
    }

    public function test_corrupt_ciphertext_rolls_back_and_never_drops_legacy_columns(): void
    {
        [$id] = $this->guardian($this->student());
        $this->legacy($id);
        DB::table('parent_guardians')->where('id', $id)->update(['mobile_ciphertext' => 'corrupt']);
        $this->artisan('school:convert-readable-data --drop-legacy')->assertFailed();
        $this->assertNull(DB::table('parent_guardians')->find($id)->cnic);
        $this->assertTrue(Schema::hasColumn('parent_guardians', 'cnic_ciphertext'));
        $this->assertSame('corrupt', DB::table('parent_guardians')->find($id)->mobile_ciphertext);
    }

    public function test_destination_conflict_is_rejected_without_overwriting(): void
    {
        [$id] = $this->guardian($this->student());
        $this->legacy($id);
        DB::table('parent_guardians')->where('id', $id)->update(['mobile' => '+923000000099']);
        $this->artisan('school:convert-readable-data')->assertFailed();
        $this->assertDatabaseHas('parent_guardians', ['id' => $id, 'cnic' => null, 'mobile' => '+923000000099']);
    }

    public function test_bad_import_rolls_back_guardian_conversion(): void
    {
        [$id] = $this->guardian($this->student());
        $this->legacy($id);
        Domain::insert('import_batches', ['created_by' => $this->admin->id, 'kind' => 'parents', 'fingerprint' => str_repeat('b', 64), 'encrypted_rows' => Crypt::encryptString('invalid-json'), 'expires_at' => now()->addDay()]);
        $this->artisan('school:convert-readable-data')->assertFailed();
        $this->assertNull(DB::table('parent_guardians')->find($id)->cnic);
    }

    public function test_duplicate_decrypted_identities_abort_without_data_loss(): void
    {
        [$one] = $this->guardian($this->student());
        [$two] = $this->guardian($this->student(roll: 2), '0000000000002');
        $this->legacy($one);
        $this->legacy($two);
        DB::table('parent_guardians')->where('id', $two)->update([
            'cnic_ciphertext' => Crypt::encryptString('0000000000001'),
            'cnic_lookup' => null, 'cnic_last4' => '0001',
        ]);
        $this->artisan('school:convert-readable-data --drop-legacy')->assertFailed();
        $this->assertSame(2, DB::table('parent_guardians')->whereNull('cnic')->count());
        $this->assertTrue(Schema::hasColumn('parent_guardians', 'cnic_ciphertext'));
        $this->assertSame(2, DB::table('parent_student_links')->count());
    }
}
