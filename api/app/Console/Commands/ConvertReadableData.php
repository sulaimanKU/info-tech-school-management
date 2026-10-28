<?php

namespace App\Console\Commands;

use App\Services\GuardianIdentity;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ConvertReadableData extends Command
{
    protected $signature = 'school:convert-readable-data {--check : Validate without writing} {--drop-legacy : Remove obsolete columns after validation}';

    protected $description = 'Validate and convert legacy guardian and import data using the unchanged application key';

    public function handle(GuardianIdentity $identity): int
    {
        if (! app()->environment('testing') && ! app()->isDownForMaintenance()) {
            $this->error('Maintenance mode required. Stop workers, scheduler and all database writers first.');

            return self::FAILURE;
        }
        if ($this->option('check') && $this->option('drop-legacy')) {
            $this->error('Use --check separately from --drop-legacy.');

            return self::FAILURE;
        }
        try {
            DB::transaction(function () use ($identity) {
                $seen = [];
                $legacy = Schema::hasColumn('parent_guardians', 'cnic_ciphertext');
                foreach (DB::table('parent_guardians')->orderBy('id')->lockForUpdate()->cursor() as $g) {
                    $cnic = $identity->normalize($legacy && $g->cnic_ciphertext !== null ? Crypt::decryptString($g->cnic_ciphertext) : ($g->cnic ?? ''));
                    $mobile = Schema::hasColumn('parent_guardians', 'mobile_ciphertext') && $g->mobile_ciphertext !== null ? Crypt::decryptString($g->mobile_ciphertext) : ($g->mobile ?? '');
                    if (! preg_match('/^\+923[0-9]{9}$/D', $mobile) || isset($seen[$cnic])) {
                        throw new RuntimeException('Invalid or duplicate identity.');
                    }
                    $seen[$cnic] = true;
                    if (($g->cnic !== null && $g->cnic !== $cnic) || ($g->mobile !== null && $g->mobile !== $mobile)) {
                        throw new RuntimeException('Conflicting destination.');
                    }
                    if (Schema::hasColumn('parent_guardians', 'cnic_lookup') && $g->cnic_lookup !== null && ! hash_equals($identity->lookup($cnic), $g->cnic_lookup)) {
                        throw new RuntimeException('Legacy lookup mismatch.');
                    }
                    if (Schema::hasColumn('parent_guardians', 'cnic_last4') && $g->cnic_last4 !== null && $g->cnic_last4 !== substr($cnic, -4)) {
                        throw new RuntimeException('Legacy suffix mismatch.');
                    }
                    if (! $this->option('check')) {
                        DB::table('parent_guardians')->where('id', $g->id)->update(['cnic' => $cnic, 'mobile' => $mobile]);
                        $saved = DB::table('parent_guardians')->find($g->id);
                        if ($saved->cnic !== $cnic || $saved->mobile !== $mobile) {
                            throw new RuntimeException('Read-back validation failed.');
                        }
                    }
                }
                $legacyImports = Schema::hasColumn('import_batches', 'encrypted_rows');
                foreach (DB::table('import_batches')->orderBy('id')->lockForUpdate()->cursor() as $b) {
                    $rows = $legacyImports && $b->encrypted_rows !== null ? Crypt::decryptString($b->encrypted_rows) : $b->source_rows;
                    if ($rows !== null) {
                        $decoded = json_decode($rows, true, 512, JSON_THROW_ON_ERROR);
                        if (! is_array($decoded) || ! isset($decoded['headers'], $decoded['rows']) || ! is_array($decoded['headers']) || ! is_array($decoded['rows'])) {
                            throw new RuntimeException('Invalid import data.');
                        }
                        if ($b->source_rows !== null && json_decode($b->source_rows, true, 512, JSON_THROW_ON_ERROR) !== $decoded) {
                            throw new RuntimeException('Conflicting import destination.');
                        }
                        if (! $this->option('check')) {
                            DB::table('import_batches')->where('id', $b->id)->update(['source_rows' => $rows]);
                            if (DB::table('import_batches')->where('id', $b->id)->value('source_rows') !== $rows) {
                                throw new RuntimeException('Import read-back validation failed.');
                            }
                        }
                    }
                }
            });
            if ($this->option('drop-legacy')) {
                // DDL is deliberately outside the conversion transaction (MySQL implicit commits).
                // Re-running after an interrupted drop safely checks each remaining column.
                Schema::table('parent_guardians', function (Blueprint $t) {
                    $t->char('cnic', 13)->nullable(false)->change();
                    $t->string('mobile', 13)->nullable(false)->change();
                });
                foreach (['cnic_ciphertext', 'mobile_ciphertext', 'cnic_lookup', 'cnic_last4'] as $column) {
                    if (Schema::hasColumn('parent_guardians', $column)) {
                        Schema::table('parent_guardians', function (Blueprint $t) use ($column) {
                            if ($column === 'cnic_lookup' && Schema::hasIndex('parent_guardians', 'parent_guardians_cnic_lookup_unique')) {
                                $t->dropUnique('parent_guardians_cnic_lookup_unique');
                            }
                            $t->dropColumn($column);
                        });
                    }
                }
                if (Schema::hasColumn('import_batches', 'encrypted_rows')) {
                    Schema::table('import_batches', fn (Blueprint $t) => $t->dropColumn('encrypted_rows'));
                }
            }
        } catch (\Throwable $e) {
            // Never print decrypted identities, payloads, keys or SQL bindings.
            $this->error('Conversion failed; no legacy data was intentionally discarded. Keep maintenance enabled, restore the original key/configuration and resolve invalid, duplicate or conflicting records before retrying.');

            return self::FAILURE;
        }
        $this->info($this->option('check') ? 'All source values validated; no changes written.' : 'Conversion and read-back validation completed.');

        return self::SUCCESS;
    }
}
