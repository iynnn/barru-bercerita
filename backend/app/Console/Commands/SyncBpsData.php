<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Category;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Publication;
use App\Models\Region;
use Throwable;

class SyncBpsData extends Command
{
    /**
     * Nama dan signature dari console command
     * {varIds?*} berarti argumen varIds bersifat opsional (?) dan dapat menerima banyak nilai (*)
     */

    protected $signature = 'bps:sync {varIds?*} {--all : Sinkronisasi semua variabel yang terdaftar}';
    protected $description = 'Mengambil semua master data dan data time series dari API BPS';

    public function handle()
    {
        // Start time 
        $startTime = microtime(true);
        $this->info('🚀 Memulai proses sinkronisasi data dari BPS...');

        $variableIds = $this->argument('varIds'); // Menjadi sebuah array
        $runAll = $this->option('all');

        $variableIdsToExplore = [];

        if ($runAll) {
            $this->info('Opsi --all terdeteksi. Menjalankan sinkronisasi untuk semua variabel terdaftar');
            $variableIdsToExplore = [34, 81, 52];
        } elseif (!empty($variableIds)) {
            $variableIdsToExplore = $variableIds;
            $this->info('Akan memproses variabel spesifik dengan ID: ' . implode(', ', $variableIds));
        } else {
            $this->error('Perintah tidak lengkap. Silakan input minimal satu ID Variabel atau gunakan opsi --all');
            $this->line('Contoh 1 (satu ID): <fg=yellow>php artisan bps:sync 81</>');
            $this->line('Contoh 2 (beberapa ID): <fg=yellow>php artisan bps:sync 81 34 52</>');
            $this->line('Contoh 3 (semua): <fg=yellow>php artisan bps:sync --all</>');
            return 1;
        }

        $this->info('🚀 Memulai proses sinkronisasi data dari BPS...');

        // ---- Konfigurasi ----
        $apiKey = env('BPS_API_KEY', '6f2b04253bc3c59d762755e3f322f550');
        $domainId = '7310';
        $region = Region::firstOrCreate(['code' => $domainId], ['name' => 'Kabupaten Barru']);

        // Looping untuk setiap variabel yang dipilih untuk diproses
        foreach ($variableIdsToExplore as $id) {
            $this->line("\n--- Mengeksplorasi Variabel Utama ID: {$id} ---");
            $this->exploreAndSaveVariable((int)$id, $domainId, $apiKey, $region);
        }
        // Catat Waktu selesai
        $endTime = microtime(true);

        $duration = round($endTime - $startTime, 2);

        $this->info('✅ Sinkronisasi data selesai!');
        $this->info("⏳ Durasi eksekusi: {$duration} detik.");
        return 0;
    }

    /**
     * Mengeksplorasi satu variabel untuk semua rentang tahun dan menyimpannya.
     * 
     */

    protected function exploreAndSaveVariable(int $varId, string $domainId, string $apiKey, Region $region)
    {
        $startThId = 100;
        $endThId = 125;

        for ($thStart = $startThId; $thStart <= $endThId; $thStart += 2) {
            $thEnd = min($thStart + 1, $endThId);
            $rangeStr = ($thStart == $thEnd) ? $thStart : "{$thStart}:{$thEnd}";
            $this->comment(" > Mengecek rentang ID tahun: {$rangeStr}");

            try {
                $response = Http::timeout(30)->get('http://webapi.bps.go.id/v1/api/list/model/data/', [
                    'domain' => $domainId,
                    'var' => $varId,
                    'th' => $rangeStr,
                    'key' => $apiKey
                ]);

                if (!$response->successful()) {
                    $this->error("   ! Gagal mengambil API untuk rentang {$rangeStr}. Status: "  . $response->status());
                    continue;
                }

                $dataJson = $response->json();

                if (($dataJson['data-availability'] ?? 'list-not-available') !== 'available') {
                    $this->warn("   - Data tidak tersedia.");
                    continue;
                }

                $this->processAndSaveResponse($dataJson, $region);
            } catch (Throwable $e) {
                $this->error("   ! Terjadi error:" . $e->getMessage());
            }

            sleep(1);
        }
    }

    /**
     * Parsing one respons JSON and saving to database
     */

    protected function processAndSaveResponse(array $dataJson, Region $region)
    {
        $varData = $dataJson['var'][0] ?? null;
        if (!$varData) return; // Keluar jika tidak ada info variabel

        $subjectData = $dataJson['subject'][0] ?? null;
        $category = Category::firstOrCreate(['name' => $subjectData['label'] ?? 'Tanpa Kategori']);

        foreach ($dataJson['vervar'] ?? [] as $vervarItem) {
            $indicator = Indicator::updateOrCreate(
                ['bps_var_id' => $varData['val'], 'bps_vervar_id' => $vervarItem['val']],
                [
                    'category_id' => $category->id,
                    'name' => $vervarItem['label'],
                    'unit' => $varData['unit'],
                    'description' => $varData['def'] ?? $varData['note'],
                    'bps_turvar_id' => $dataJson['turvar'][0]['val'] ?? null,
                ]
            );

            if (isset($dataJson['related'])) {
                $publicationIds = [];
                foreach ($dataJson['related'] as $relatedItem) {
                    //  Simpan setiap link sebagai publikasi induk 
                    $publication = Publication::firstOrCreate(
                        ['bps_related_id' => $relatedItem['id']],
                        ['title' => $relatedItem['title'], 'link' => $relatedItem['link']]
                    );
                    $publicationIds[] = $publication->id;
                    // $indicator->publications()->syncWithoutDetaching($publication->id);
                    $indicator->publications()->sync($publicationIds);
                }
            }

            foreach ($dataJson['tahun'] ?? [] as $tahunItem) {
                $kunci = "{$vervarItem['val']}{$varData['val']}{$dataJson['turvar'][0]['val']}{$tahunItem['val']}{$dataJson['turtahun'][0]['val']}";
                $nilai = $dataJson['datacontent'][$kunci] ?? null;

                if ($nilai !== null) {
                    IndicatorValue::updateOrCreate(
                        ['indicator_id' => $indicator->id, 'region_id' => $region->id, 'year' => $tahunItem['label']],
                        ['value' => $nilai, 'last_synced_at' => now()]
                    );
                }
            }
        }


        $this->info("   ✓ Data dari API berhasil diproses.");
    }
}
