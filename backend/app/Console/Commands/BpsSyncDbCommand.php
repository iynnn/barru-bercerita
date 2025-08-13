<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\BpsClientService;

/**
 * BpsSyncDbCommand (NO-REGION VERSION)
 *
 * Tujuan:
 * - Tarik data dari Web API BPS via BpsClientService.
 * - Mapping ke skema MySQL sesuai ERD kamu, TANPA menyentuh tabel `region`.
 * - `indicator_value.region_id` diset ke 0 (konstanta), jadi kamu bisa lanjut walau tabel `region` belum ada.
 *
 * Cara pakai:
 *   # specific VAR
 *   php artisan bps:sync-db --vars=81,34 --start=100 --end=125 --domain=7310
 *
 *   # semua VAR (option opsional)
 *   php artisan bps:sync-db --allvars --start=100 --end=125 --domain=7310
 *
 * Catatan:
 * - Pastikan environment:
 *     BPS_API_KEY=xxx
 *     BPS_DOMAIN=7310
 * - Pastikan tabel: category, indicator, indicator_value sudah ada.
 * - Unique index `indicator_value` sebaiknya di (indicator_id, region_id, year).
 *   Karena kita set region_id=0, uniqueness tetap aman per indicator+year.
 */
class BpsSyncDbCommand extends Command
{
    /**
     * Signature command
     * - Opsi:
     *   --vars=81,34   : daftar VAR ID (dipisah koma)
     *   --allvars      : tarik semua VAR untuk domain
     *   --start, --end : rentang th_id
     *   --domain       : override domain (default ambil dari .env)
     *   --key          : override API key (default ambil dari .env)
     */
    protected $signature = 'bps:sync-db
        {--vars= : Daftar VAR ID, contoh: 81,34}
        {--allvars : Ambil semua VAR dari domain}
        {--start=100 : Start TH ID}
        {--end=125 : End TH ID}
        {--domain= : Domain BPS (default dari .env)}
        {--key= : API key (override .env)}';

    /** Deskripsi untuk listing artisan */
    protected $description = 'Sinkron data BPS ke database (category, indicator, indicator_value) TANPA tabel region';

    /**
     * Konstruktor
     * - Inject BpsClientService via DI.
     */
    public function __construct(private BpsClientService $bps)
    {
        parent::__construct();
    }

    /**
     * handle
     * - Entry point command.
     * - Ambil opsi, tarik data dari BPS, mapping, dan upsert ke DB.
     */
    public function handle(): int
    {
        // -------- Ambil opsi dasar --------
        $domain = (string) ($this->option('domain') ?: config('services.bps.domain', env('BPS_DOMAIN', '7310')));
        $key    = (string) ($this->option('key') ?: config('services.bps.key', env('BPS_API_KEY', '')));
        $start  = (int) $this->option('start');
        $end    = (int) $this->option('end');

        // -------- Init service (boleh override key) --------
        if ($key !== '') {
            $this->bps = new BpsClientService($key, $domain);
        } else {
            $this->bps->setDomain($domain);
        }

        // -------- Tentukan daftar VAR ID --------
        $varIds = [];
        if ((bool) $this->option('allvars')) {
            $this->info("Ambil semua VAR untuk domain {$domain} ...");
            $vars = $this->bps->fetchVars($domain);
            foreach ($vars as $row) {
                $varId = $row['var_id'] ?? $row['id'] ?? $row['kode'] ?? null;
                if ($varId !== null) $varIds[] = (string) $varId;
            }
            $varIds = array_values(array_unique($varIds));
        } else {
            $varsStr = (string) $this->option('vars');
            if ($varsStr === '') {
                $this->error('Isi --vars=81,34 atau pakai --allvars');
                return self::INVALID;
            }
            $varIds = array_values(array_filter(array_map('trim', explode(',', $varsStr))));
        }

        if (empty($varIds)) {
            $this->warn('Tidak ada VAR ID yang diproses.');
            return self::SUCCESS;
        }

        // -------- Sinkron category dari SUBJECT --------
        // Mapping sederhana: category.id = subject_id BPS
        $this->info('Sinkron kategori (subject) ...');
        $subjects = $this->bps->fetchSubjects($domain);
        foreach ($subjects as $s) {
            $subjectId = $s['subjek_id'] ?? $s['subject_id'] ?? $s['id'] ?? null;
            $subjectNm = $s['subjek']    ?? $s['subject']    ?? $s['nama'] ?? $s['name'] ?? null;
            $desc      = $s['keterangan'] ?? $s['deskripsi'] ?? $s['description'] ?? null;

            if ($subjectId === null) continue;

            DB::table('category')->updateOrInsert(
                ['id' => (int) $subjectId],
                [
                    'name'        => mb_substr((string)($subjectNm ?: "Subject {$subjectId}"), 0, 20),
                    'description' => $desc ? mb_substr((string)$desc, 0, 300) : null,
                ]
            );
        }

        // -------- Proses tiap VAR --------
        foreach ($varIds as $varId) {
            $this->info("VAR {$varId} → ambil data th {$start}..{$end}");
            $rows = $this->bps->exploreVar($varId, $start, $end, $domain);
            if (empty($rows)) {
                $this->warn("VAR {$varId}: tidak ada data.");
                continue;
            }

            // Ambil metadata dasar dari sampel baris
            $example   = $rows[0];
            $varLabel  = (string) ($example['var_label'] ?? "Var {$varId}");
            $unit      = (string) ($example['unit'] ?? ($example['satuan'] ?? ''));
            $subjectId = (int)   ($example['subject_id'] ?? $example['subjek_id'] ?? 0);

            // Group per vervar_id → 1 indikator per kombinasi (var_id, vervar_id)
            $byVervar = [];
            foreach ($rows as $r) {
                $keyV = (string) ($r['vervar_id'] ?? 'null');
                $byVervar[$keyV][] = $r;
            }

            foreach ($byVervar as $vervarKey => $groupRows) {
                $vervarId    = $vervarKey === 'null' ? null : (int) $vervarKey;
                $vervarLabel = $groupRows[0]['vervar_label'] ?? null;

                $indicatorName = trim($varLabel . ($vervarLabel ? " - {$vervarLabel}" : ''));

                // Cari/insert indicator by (bps_var_id, bps_vervar_id)
                $indicatorId = DB::table('indicator')->where([
                    'bps_var_id'    => (int) $varId,
                    'bps_vervar_id' => $vervarId,
                ])->value('id');

                if (!$indicatorId) {
                    $indicatorId = DB::table('indicator')->insertGetId([
                        'category_id'   => $subjectId,             // bisa 0 kalau gak ketemu
                        'bps_var_id'    => (int) $varId,
                        'bps_turvar_id' => null,
                        'bps_vervar_id' => $vervarId,
                        'name'          => mb_substr($indicatorName, 0, 50),
                        'unit'          => mb_substr($unit, 0, 15),
                        'description'   => null,
                    ]);
                } else {
                    DB::table('indicator')->where('id', $indicatorId)->update([
                        'category_id' => $subjectId,
                        'name'        => mb_substr($indicatorName, 0, 50),
                        'unit'        => mb_substr($unit, 0, 15),
                    ]);
                }

                // Siapkan batch upsert ke indicator_value
                // NOTE: region_id DISSET KE 0, dan kita TIDAK menyentuh tabel region
                $now   = now();
                $batch = [];

                foreach ($groupRows as $r) {
                    $year = (int) ($r['tahun'] ?? $r['th'] ?? 0);
                    if ($year <= 0) continue;

                    $value = (float) ($r['nilai'] ?? $r['value'] ?? 0);

                    $batch[] = [
                        'indicator_id' => $indicatorId,
                        'region_id'    => 0,        // <<< fix: tanpa region
                        'year'         => $year,
                        'value'        => $value,
                        'last_sync_at' => $now,
                    ];
                }

                if (!empty($batch)) {
                    DB::table('indicator_value')->upsert(
                        $batch,
                        ['indicator_id', 'region_id', 'year'], // unique keys
                        ['value', 'last_sync_at']              // update cols
                    );
                }
            }
        }

        $this->info('✅ Selesai sinkron (tanpa region)!');
        return self::SUCCESS;
    }
}
