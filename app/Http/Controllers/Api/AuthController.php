<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\GoogleIdTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            ...$data,
            'password' => $data['password'],
        ]);

        $user->sendEmailVerificationNotification();

        $this->ensureSessionAvailable($request);

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $this->ensureSessionAvailable($request);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $request->session()->regenerate();

        return new UserResource(Auth::user());
    }

    public function loginWithGoogle(Request $request, GoogleIdTokenVerifier $verifier)
    {
        $data = $request->validate(['id_token' => 'required|string']);

        $payload = $verifier->verify($data['id_token']);
        abort_unless($payload && ($payload['email_verified'] ?? false), 401, 'Token Google tidak valid.');

        $this->ensureSessionAvailable($request);

        $user = User::firstOrCreate(
            ['email' => $payload['email']],
            [
                'name' => $payload['name'] ?? $payload['email'],
                'password' => Str::random(40),
            ],
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    /**
     * Sanctum hanya menyalakan session middleware kalau request punya header
     * Origin/Referer yang cocok dengan SANCTUM_STATEFUL_DOMAINS — tanpa itu
     * $request->session() melempar RuntimeException mentah (500). Tolak lebih
     * awal dengan pesan yang jelas.
     */
    private function ensureSessionAvailable(Request $request): void
    {
        abort_unless($request->hasSession(), 400, 'Request harus berasal dari origin frontend yang diizinkan.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Link verifikasi telah dikirim.']);
    }

    public function verifyEmail(Request $request, string $id, string $hash)
    {
        $user = $request->user();

        abort_unless((string) $user->getKey() === $id, 403);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(rtrim(config('services.frontend_url'), '/').'/verify-email?status=success');
    }
}
