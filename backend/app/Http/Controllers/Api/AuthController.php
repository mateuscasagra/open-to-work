<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendVerificationCodeRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Mail\VerifyEmailCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    private const CODE_TTL_MINUTES = 15;

    private const MAX_VERIFICATION_ATTEMPTS = 5;

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (! config('auth.email_verification_enabled')) {
            $user->forceFill(['email_verified_at' => now()])->save();
            Auth::login($user);
            $request->session()->regenerate();

            return response()->json(['user' => $user->refresh()], 201);
        }

        $this->issueVerificationCode($user);

        return response()->json([
            'status' => 'verification_required',
            'email' => $user->email,
        ], 202);
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('auth.verify.invalid_email'),
            ]);
        }

        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => __('auth.verify.already_verified'),
            ]);
        }

        if ($user->email_verification_code === null
            || $user->email_verification_code_expires_at === null
            || $user->email_verification_code_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => __('auth.verify.code_expired'),
            ]);
        }

        if ($user->email_verification_attempts >= self::MAX_VERIFICATION_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => __('auth.verify.too_many_attempts'),
            ]);
        }

        if (! Hash::check($data['code'], $user->email_verification_code)) {
            $user->forceFill([
                'email_verification_attempts' => $user->email_verification_attempts + 1,
            ])->save();

            throw ValidationException::withMessages([
                'code' => __('auth.verify.invalid_code'),
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_attempts' => 0,
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->refresh()]);
    }

    public function resendVerificationCode(ResendVerificationCodeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->where('email', $data['email'])->first();

        // Always respond 200 to avoid leaking which emails are registered.
        if ($user && $user->email_verified_at === null) {
            $this->issueVerificationCode($user);
        }

        return response()->json(['status' => 'sent']);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $credentials = ['email' => $data['email'], 'password' => $data['password']];

        if (! Auth::attempt($credentials, remember: (bool) ($data['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if (config('auth.email_verification_enabled')
            && $user instanceof User
            && $user->email_verified_at === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->issueVerificationCode($user);

            throw ValidationException::withMessages([
                'email' => __('auth.verify.must_verify'),
            ])->status(403);
        }

        $request->session()->regenerate();

        return response()->json(['user' => Auth::user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    private function issueVerificationCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'email_verification_attempts' => 0,
        ])->save();

        Mail::to($user->email)->send(new VerifyEmailCode(
            name: $user->name,
            code: $code,
            expiresInMinutes: self::CODE_TTL_MINUTES,
        ));
    }
}
