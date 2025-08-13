<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Indicator;
use Illuminate\Support\Facades\Http;
use Throwable;

class InsightController extends Controller
{
    public function getInsight(Request $request, $bps_var_id)
    {
        // Mengambil Data dari Database
        $indicators = Indicator::where('bps_var_id', $bps_var_id)
            ->with(['values' => function ($query) {
                $query->orderBy('year', 'desc')->take(5);
            }])
            ->get();

        if ($indicators->isEmpty()) {
            return response()->json(['error' => 'Data indikator tidak ditemukan'], 404);
        }

        // -- Format Data menjadi teks sederhana
        $dataText = "Berikut adalah data statistik untuk Kabupaten Barru:\n\n";
        foreach ($indicators as $indicator) {
            $dataText .= "Indikator: " . $indicator->name  . " (" . $indicator->unit . ")\n";
            foreach ($indicator->values as $value) {
                $dataText .= "- Tahun " . $value->year . ": " . $value->value . "\n";
            }
            $dataText .= "\n";
        }

        // Prompt Detail 
        $prompt = "Anda adalah seorang ahli ekonomi, kemiskinan, dan data indikator makro serta analis data. Berdasarkan data statistik berikut untuk Kabupaten Barru, berikan insight atau cerita singkat (maksimal 3 paragraf) mengenai tren atau fenomena yang terjadi. Fokus pada data yang diberikan. Dapat dijelaskan dengan tren ataupun nilai statistik lainnya dan kemungkinan penyebabnya secara umum. Analisisnya yang tajam dan tidak general. \n\nData: \n" . $dataText;

        // Panggil Gemini API 
        $apiKey = env('GEMINI_API_KEY');
        // $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

        if (!$apiKey) {
            return response()->json(['error' => 'Gemini API Key tidak ditemukan'], 500);
        }

        // URL end point Gemini API 
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

        // Data body yang akan dikirim, sama seperti di cURL -d
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];
        try {
            $response = Http::post($url, $body);

            // Periksa jika panggilan API tidak berhasil
            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Gagal menghubungi Gemini API',
                    'details' => $response->json() ?? $response->body()
                ], $response->status());
            }

            // Ambil teks jawaban dari response JSON
            $insight = $response->json('candidates.0.content.parts.0.text', 'Tidak ada insight yang diberikan');
            return response()->json(['insight' => $insight]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Terjadi kesalahan interna: ' . $e->getMessage()], 500);
        }
    }
}
