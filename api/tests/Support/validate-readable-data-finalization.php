<?php

// Explicit standalone DDL check: never run against an ordinary application database.
require __DIR__.'/assert-validation-database.php';

use App\Models\User;
use App\Services\GuardianIdentity;
use App\Support\Domain;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! app()->environment('testing')) {
    throw new RuntimeException('Testing environment required.');
}
Artisan::call('migrate', ['--force' => true]);
$user = User::create(['name' => 'Fictional conversion check', 'email' => 'conversion-'.bin2hex(random_bytes(6)).'@example.test', 'password' => bin2hex(random_bytes(32))]);
$cnic = '999'.str_pad((string) random_int(1, 999999999), 10, '0', STR_PAD_LEFT);
$id = Domain::insert('parent_guardians', ['user_id' => $user->id, 'full_name' => $user->name, 'cnic_ciphertext' => Crypt::encryptString($cnic), 'mobile_ciphertext' => Crypt::encryptString('+923000000001'), 'cnic_lookup' => app(GuardianIdentity::class)->lookup($cnic), 'cnic_last4' => substr($cnic, -4)]);
foreach ([[], ['--drop-legacy' => true], ['--drop-legacy' => true], ['--check' => true]] as $options) {
    if (Artisan::call('school:convert-readable-data', $options) !== 0) {
        throw new RuntimeException('Conversion/finalization failed.');
    }
}
$g = DB::table('parent_guardians')->find($id);
if ($g->cnic !== $cnic || $g->mobile !== '+923000000001' || $g->user_id !== $user->id) {
    throw new RuntimeException('Converted values changed.');
}
foreach (['cnic_ciphertext', 'mobile_ciphertext', 'cnic_lookup', 'cnic_last4'] as $column) {
    if (Schema::hasColumn('parent_guardians', $column)) {
        throw new RuntimeException('Legacy column remains.');
    }
}
if (Schema::hasColumn('import_batches', 'encrypted_rows')) {
    throw new RuntimeException('Legacy import column remains.');
}
DB::table('parent_guardians')->where('id', $id)->delete();
$user->delete();
echo "Conversion, finalization DDL and repeat invocation: PASS.\n";
