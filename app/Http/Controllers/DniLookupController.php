<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DniLookupController extends Controller
{
    /**
     * Looks a DNI up in RENIEC through the Decolecta API and returns the
     * person's names to prefill the employee form. The token lives in .env
     * (DECOLECTA_API_TOKEN) and results are cached to save API credits.
     */
    public function show(string $dni)
    {
        abort_unless(preg_match('/^\d{8}$/', $dni), 422, __('The DNI must have exactly 8 digits.'));

        $token = config('services.decolecta.token');
        if (!$token) {
            return response()->json([
                'ok' => false,
                'message' => __('Validation not configured: add DECOLECTA_API_TOKEN to your .env file.'),
            ], 503);
        }

        $result = Cache::remember("dni_lookup_{$dni}", now()->addDay(), function () use ($dni, $token) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(8)
                    ->get(rtrim(config('services.decolecta.url'), '/').'/v1/reniec/dni', ['numero' => $dni]);
            } catch (\Throwable $e) {
                // Surface the real cause: the classic one on Windows (Laragon/XAMPP)
                // is cURL error 60 — PHP has no CA bundle configured in php.ini.
                \Illuminate\Support\Facades\Log::warning('DNI lookup failed: '.$e->getMessage());

                $message = str_contains($e->getMessage(), 'cURL error 60') || stripos($e->getMessage(), 'SSL certificate') !== false
                    ? __('SSL error in PHP: set curl.cainfo and openssl.cafile in php.ini to a cacert.pem file (see docs/CONFIGURACION.md) and restart the server.')
                    : __('Could not reach the validation service. Try again in a moment.').' ('.\Illuminate\Support\Str::limit($e->getMessage(), 90).')';

                return ['ok' => false, 'status' => 503, 'message' => $message];
            }

            if ($response->status() === 404) {
                return ['ok' => false, 'status' => 404, 'message' => __('DNI not found.')];
            }

            if (!$response->successful()) {
                return ['ok' => false, 'status' => 502, 'message' => __('The validation service answered with an error (check your Decolecta token or credits).')];
            }

            $data = $response->json() ?? [];

            // Defensive parsing: Decolecta uses first_name / first_last_name /
            // second_last_name, but tolerate alternative field names too.
            $firstName = $data['first_name'] ?? $data['nombres'] ?? '';
            $lastName = trim(
                ($data['first_last_name'] ?? $data['apellido_paterno'] ?? '').' '.
                ($data['second_last_name'] ?? $data['apellido_materno'] ?? '')
            );

            if ($firstName === '' && $lastName === '' && !empty($data['full_name'])) {
                // Last resort: full_name comes as "SURNAME1 SURNAME2 NAMES..."
                $parts = explode(' ', $data['full_name']);
                $lastName = implode(' ', array_slice($parts, 0, 2));
                $firstName = implode(' ', array_slice($parts, 2));
            }

            if ($firstName === '' && $lastName === '') {
                return ['ok' => false, 'status' => 502, 'message' => __('Unexpected response from the validation service.')];
            }

            return [
                'ok' => true,
                'status' => 200,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
        });

        // Don't cache failures
        if (!($result['ok'] ?? false)) {
            Cache::forget("dni_lookup_{$dni}");
        }

        return response()->json($result, $result['status'] ?? 200);
    }
}
