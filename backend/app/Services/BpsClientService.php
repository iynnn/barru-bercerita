<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * BPS Client Service
 * 
 * Service ini reqrite dari API BPS:
 * - Menarik data dari Web API BPS (subject, var, data var per tahun)
 * - Automisasi Pagination 
 * - Explore data var dalam rentang ID tahun (th) dengan batching 2 tahun per request 
 * - Simpan hasil jadi JSON 
 * 
 * Catatan:
 * BASE_URL: https://webapi.bps.go.id/v1/api/list/
 * List:     ?model={subject|var|th}&domain={DOMAIN}&page={N}&ket={API_KEY}
 * Data Var: /model/data/domain/{domain}/var/{var}/th/{th}/key/{key}/
 * 
 * Respons dai BPS memiliki struktur:
 * {"data-availability" : "available" | "unavailable",
 * "data:"" [ < meta >, [ < rows...> ] ] }
 * 
 * Kode ini dibuat defensif
 * 
 */

class BpsClientService
{
    /** @var string API key BPS */
    private string $apiKey;

    /** @var string Domain BPS, contoh "7310 */
    private string $domain;

    /** @var string Base URL API BPS */
    private string $baseUrl = 'https://webapi.bps.go.id/v1/api/list/';

    /** 
     * __construct 
     * -  Inject kreditensial dan domain default (dapat diubah per panggilan lewat argumen method)
     * 
     */
    public function __construct(?string $apiKey = null, string $domain = '7310')
    {
        $this->apiKey = $apiKey ?: (string) config('services.bps.key', env('BPS_API_KEY', ''));
        $this->domain = $domain;
    }
    /**
     * setDomain
     * -
     */

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * fetchPaged
     * - Helper generik untuk request model yang dipaginasi (subject/var/th)
     * - Looping page = 1..N sampai data availability != available
     * 
     * 
     * @param string $model 'subject'|'var'|'th'
     * @param string|null $domain Domain BPS 
     * @return array<int, array<string,mixed>> Kumpulan Baris
     */

    public function fetchPaged(string $model, ?string $domain = null): array
    {
        $domain = $domain ?: $this->domain;
        $page = 1;
        $all = [];

        while (true) {
            $resp = Http::timeout(15)->get($this->baseUrl, [
                'model' => $model,
                'domain' => $domain,
                'page' => $page,
                'key' => $this->apiKey,
            ]);

            if (!$resp->ok()) {
                // Berhenti ketika status bukan 200
                break;
            }

            $json = $resp->json();
            $availability = $json['data-availability'] ?? 'unavailable';
            if ($availability != 'available') {
                // Jika API mengatakan data tidak available
                break;
            }

            // Data ada di array ke 2, array ke 1 berisikan informasi tentang halaman dan jumlah data (informasi umum)
            $rows = $json['data'][1] ?? [];
            if (empty($rows)) {
                // Halaman kosong -> selesai
                break;
            }

            // Gabung data
            foreach ($rows as $row) {
                $all[] = $row;
            }

            $page++;
            usleep(200 * 1000); // 200ms
        }

        return $all;
    }

    /** 
     * fetchSubjects
     * - Ambil semua subject untuk domain 
     * 
     * 
     * @param string|null $domain Domain BPS
     * @return array
     * 
     */
    public function fetchSubjects(?string $domain = null): array
    {
        return $this->fetchPaged('subject', $domain);
    }

    /**
     * fecthVars
     * - Ambil semua variable untuk domain 
     * 
     * @param string|null $domain BPS
     * @return array
     */
    public function fetchVars(?string $domain = null): array
    {
        return $this->fetchPaged('var', $domain);
    }

    /**
     * fetchYears
     * - Ambil daftar tahun (th) beserta id_tahun
     * 
     * 
     * @param string|null $domain Domain BPS
     * @return array
     */
    public function fecthYears(?string $domain = null): array
    {
        return $this->fetchPaged('th', $domain);
    }


    /**
     * build Data VarUrl
     * - Bentuk URL untuk endpoint data var per rentang tahun
     * - Pola: /model/data/domain/{domain}/var/{var}/th/{th}/key/{key}
     * 
     * @param string $domain
     * @param string|int varId
     * @param string $thParam, contoh: "100:101" atau "100"
     * @return string 
     */
    private function buildDataVarUrl(string $domain, string|int $varId, string $thParam): string
    {
        $domain = urlencode($domain);
        $varId  = urlencode((string)$varId);
        $th     = urlencode($thParam);
        $key    = urlencode($this->apiKey);

        return "https://webapi.bps.go.id/v1/api/list/model/data/domain/{$domain}/var/{$varId}/th/{$th}/key/{$key}/";
    }


    /**
     * exploreVar
     * - Ambil data untuk satu VAR ID dalam rentang ID tahun [startThId..endThId].
     * - Batching: 2 tahun per request 
     * - Merge semua hasil jadi array terurut (berdasarkan id)tahun dan vervar_id, jika ada)
     * 
     * @param int|string $varId
     * @param int $startThId (default 100)
     * @param int $endThId (default 125)
     * @param string|null $domain (default domain service)
     * @return array<int, array<string, mixed>>
     */

    public function exploreVar(int|string $varId, int $startThId = 100, int $endThId = 125, ?string $domain = null): array
    {
        $domain = $domain ?: $this->domain;
        $results = [];

        for ($thStart = $startThId; $thStart <= $endThId; $thStart += 2) {
            $thEnd = min($endThId, $thStart + 1);

            // Jika strart == end, kirim single ID; kalau beda menggunakan "start:end"
            $thParam = ($thStart === $thEnd) ? (string)$thStart : "{$thStart}:{$thEnd}";

            $url = $this->buildDataVarUrl($domain, $varId, $thParam);
            $resp = Http::timeout(20)->get($url);

            if (!$resp->ok()) {
                continue; // Lanjut batch selanjutnya
            }


            // Parse JSON yang aman 
            try {
                $json = $resp->json();
            } catch (\Throwable $e) {
                continue;
            }

            // $json = $resp->json();
            $availability = $json['data-availability'] ?? 'unavailable';
            if ($availability !== 'available') {
                continue;
            }

            $rows = [];
            $data = $json['data'][1] ?? null;

            // Pastikan data 
            // Struktur data hasil 'data'[1]
            $rows = $json['data'][1] ?? [];
            if (empty($rows)) {
                continue;
            }

            //Normalisasi setiap baris

            foreach ($rows as $r) {
                $results[] = [
                    'id_tahun'     => $r['id_tahun']     ?? $r['th_id']      ?? null,
                    'tahun'        => $r['tahun']        ?? $r['th']         ?? null,
                    'vervar_label' => $r['vervar_label'] ?? null,
                    'vervar_id'    => $r['vervar_id']    ?? null,
                    'nilai'        => $r['nilai']        ?? $r['value']      ?? null,
                    'status'       => $r['status']       ?? null,
                    'last_update'  => $r['last_update']  ?? null,
                    'var_label'    => $r['var_label']    ?? null,

                    // Simpan sisa payload mentah jika terdapat struktur lain
                    'raw'          => $r,
                ];
            }

            // Jeda Ringan antar batch
            usleep(150 * 1000);
        }


        // Sort hasil: id_tahun_ASC, lalu vervar_id ASC

        usort($results, function ($a, $b) {
            $at = $a['id_tahun']  ?? 0;
            $bt = $b['id_tahun']  ?? 0;
            if ($at === $bt) {
                return ($a['vervar_id'] ?? 0) <=> ($b['vervar_id'] ?? 0);
            }
            return $at <=> $bt;
        });

        return $results;
    }


    /**
     * collect allVarsDataToJson
     * - Loop banyak VAR ID -> panggil explore Var -> gabung -> simpan jadi file JSON
     * -Format JSON: { "<varId>": [ {record...}, ....],...}
     * 
     * 
     * @param array<int|string> $varIds
     * @param int $startThId
     * @param int $endThId
     * @param string $storagePath path relatif di storage 
     * @param string|null $domain
     * @return string Path file yang dihasilkan 
     */

    public function collectAllVarsDataToJson(array $varIds, int $startThId = 100, int $endThId = 125, string $storagePath = 'public/hasil_semua_var.json', ?string $domain = null): string
    {
        $domain = $domain ?: $this->domain;

        $payload = [];
        foreach ($varIds as $varId) {
            $data = $this->exploreVar($varId, $startThId, $endThId, $domain);
            $payload[(string)$varId] = $data;
        }

        // Simpan ke storage laravel:
        Storage::put($storagePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $storagePath;
    }
}
