<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BpsClientService;
use Symfony\Component\Console\Input\StringInput;

/**
 * BPSPullCommand
 * 
 * Artisan command agar dapat menarik data via CLI 
 * 
 */

class BpsPullCommand extends Command
{
    /**
     * $signature
     * - Command + opsi/argumen
     * - mode: subjects|vars|data
     * 
     */

    protected $signature = 'bps:pull
    {mode : subjects|vars|data}
    {--domain= : Domain BPS}
    {--key= : API key (override .env)}
    {--vars= : Daftar VAR ID dibisah koma}
    {--start=100 : Start TH ID (default 100)}
    {--end=125 : End TH ID (default 125)}
    {--out=public/hasil_semua_var.json : Path Output}';

    /**
     * $description
     * - Keterangan singkat command (muncul ketika php artisan list)
     * 
     */

    protected $description = 'Tarik data dari Web API BPS (subjects/vars/data var) dan simpan sesuai kebutuhan';


    /**
     * 
     * __construct
     * - Inject Service
     */

    public function __construct(private BpsClientService $client)
    {
        parent::__construct();
    }

    /**
     * hanlde
     * - Entry point di saat command jalan
     * - Baca argumen/opsi, panggil service, dan tampilkan hasil 
     */

    public function handle(): int
    {
        $mode   = strtolower((string) $this->argument('mode'));
        $domain = (string) ($this->option('domain') ?: $this->getDefaultDomain());
        $key    = (string) ($this->option('key') ?: $this->getDefaultKey());

        if (!empty($key)) {
            $this->client = new BpsClientService($key, $domain);
        } else {
            $this->client->setDomain($domain);
        }

        if ($mode === 'subjects') {
            $this->info("Menraik semua subject untuk domain {$domain}...");
            $data = $this->client->fetchSubjects($domain);
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        if ($mode === 'vars') {
            $this->info("Menarik semua var untuk domain {$domain}...");
            $data = $this->client->fetchVars($domain);
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        if ($mode === 'data') {
            $varsStr = (string) $this->option('vars');
            if ($varsStr === '') {
                $this->error('Opsi --vars wajib diisi untuk mode data (contoh: --vars=81,34');
                return self::INVALID;
            }

            $varIds = array_values(array_filter(array_map('trim', explode(',', $varsStr)), fn($v) => $v !== ''));

            $start = (int) $this->option('start');
            $end   = (int) $this->option('end');
            $out   = (string) $this->option('out');

            $this->info("Menarik data untuk VAR ID [" . implode(', ', $varIds) . "] (th {$start}..{$end}) domain {$domain}");
            $path = $this->client->collectAllVarsDataToJson($varIds, $start, $end, $out, $domain);

            $this->info("✓ Berhasil disimpan ke storage: {$path}");
            $this->comment("Kalau out=public/... file dapat diakses di public path (pastikan disk/public disymlink).");
            return self::SUCCESS;
        }

        $this->error("Mode tidak dikenal {$mode} (gunakan: subjects|vars|data)");
        return self::INVALID;
    }

    /**
     * getDefaultDomain
     * - Ambil domain default  = 7310
     */

    private function getDefaultDomain(): String
    {
        return (string) config('services.bps.domain', env('BPS_DOMAIN', '7310'));
    }

    /**
     * getDefaultKey
     */

    private function getDefaultKey(): string
    {
        return (string) config('services.bps.key', env('BPS_API_KEY', ''));
    }
}
