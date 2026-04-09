<?php

namespace App\Http\Controllers;

use App\Support\PresensiApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->has('user') && session('user.username')) {
            return redirect()->route('dashboard.presensi-sholat');
        }

        return view('login');
    }

    public function logout(Request $request)
    {
        $request->session()->flush();

        return redirect()->route('login.form');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($this->isTurnstileEnabled() && ! $this->verifyTurnstile($request)) {
            return back()
                ->withInput($request->except(['password', 'cf-turnstile-response']))
                ->with('login_error', 'Validasi Cloudflare gagal. Silakan coba lagi.');
        }

        $apiBaseUrl = config('presensi.api_url');

        Log::info('Login attempt (Alizzah presensi)', [
            'username' => $validated['username'],
        ]);

        $payload = [
            'METHOD' => 'LoginRequest',
            'USERNAME' => $validated['username'],
            'PASSWORD' => $validated['password'],
        ];

        $token = $this->generateJwt($payload);

        PresensiApiLog::request('LoginRequest', [
            'username' => $validated['username'],
            'token_length' => strlen($token),
        ]);

        try {
            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->withOptions(['verify' => (bool) config('presensi.http.verify_ssl', true)])
                ->get($apiBaseUrl . '?token=' . urlencode($token));
        } catch (\Throwable $e) {
            PresensiApiLog::connectionFailed('LoginRequest', $e);

            return back()
                ->withInput($request->except('password'))
                ->with('login_error', 'Tidak dapat terhubung ke server. Silakan coba lagi.');
        }

        if (! $response->ok()) {
            PresensiApiLog::badHttp('LoginRequest', $response);

            return back()
                ->withInput($request->except('password'))
                ->with('login_error', 'Terjadi kesalahan pada server. Silakan coba lagi.');
        }

        $data = $response->json();

        if (isset($data['KodeRespon']) && (int) $data['KodeRespon'] === 1) {
            $request->session()->put('user', [
                'username' => $validated['username'],
                'app' => 'presensi-sholat',
            ]);

            return redirect()
                ->route('dashboard.presensi-sholat')
                ->with('login_success', 'Login berhasil.');
        }

        $message = $data['PesanRespon'] ?? 'Login gagal. Akses ditolak.';

        return back()
            ->withInput($request->except('password'))
            ->with('login_error', $message);
    }

    public function showGantiPasswordPresensi()
    {
        if (session('user.app') !== 'presensi-sholat') {
            return redirect()->route('login.form');
        }

        return view('ganti_password_presensi');
    }

    public function gantiPassword(Request $request)
    {
        $validated = $request->validate([
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:3'],
            'confirm_password' => ['required', 'string', 'same:new_password'],
        ]);

        $username = session('user.username');
        if (! $username) {
            return back()->with('password_error', 'Session tidak valid. Silakan login kembali.');
        }

        $apiBaseUrl = config('presensi.api_url');

        $payload = [
            'METHOD' => 'RequestNewPassword',
            'USERNAME' => $username,
            'PASSWORD' => $validated['old_password'],
            'NEWPASSWORD' => $validated['new_password'],
            'NEWPASSWORD2' => $validated['confirm_password'],
        ];

        $token = $this->generateJwt($payload);

        PresensiApiLog::request('RequestNewPassword', ['username' => $username, 'token_length' => strlen($token)]);

        try {
            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->withOptions(['verify' => (bool) config('presensi.http.verify_ssl', true)])
                ->get($apiBaseUrl . '?token=' . urlencode($token));

            if (! $response->ok()) {
                PresensiApiLog::badHttp('RequestNewPassword', $response);

                return back()
                    ->withInput($request->except(['old_password', 'new_password', 'confirm_password']))
                    ->with('password_error', 'Terjadi kesalahan pada server (HTTP ' . $response->status() . ').');
            }

            $data = $response->json();

            if (isset($data['KodeRespon']) && (int) $data['KodeRespon'] === 1) {
                return back()->with('password_success', 'Password berhasil diubah.');
            }

            $message = $data['PesanRespon'] ?? 'Gagal mengubah password.';

            return back()
                ->withInput($request->except(['old_password', 'new_password', 'confirm_password']))
                ->with('password_error', $message);
        } catch (\Throwable $e) {
            PresensiApiLog::connectionFailed('RequestNewPassword', $e);

            return back()
                ->withInput($request->except(['old_password', 'new_password', 'confirm_password']))
                ->with('password_error', 'Tidak dapat terhubung ke server. Silakan coba lagi.');
        }
    }

    private function generateJwt(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signingInput = $headerEncoded . '.' . $payloadEncoded;
        $signature = hash_hmac('sha256', $signingInput, config('presensi.jwt_secret'), true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $signingInput . '.' . $signatureEncoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function isTurnstileEnabled(): bool
    {
        return (bool) config('services.turnstile.site_key')
            && (bool) config('services.turnstile.secret_key');
    }

    private function verifyTurnstile(Request $request): bool
    {
        $token = (string) $request->input('cf-turnstile-response', '');
        if ($token === '') {
            Log::warning('Turnstile missing token', [
                'ip' => $request->ip(),
                'username' => $request->input('username'),
            ]);

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            if (! $response->ok()) {
                Log::warning('Turnstile HTTP error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 1000),
                ]);

                return false;
            }

            $data = $response->json();
            $success = (bool) ($data['success'] ?? false);

            if (! $success) {
                Log::warning('Turnstile verification failed', [
                    'error_codes' => $data['error-codes'] ?? [],
                    'hostname' => $data['hostname'] ?? null,
                    'action' => $data['action'] ?? null,
                ]);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::error('Turnstile request exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
