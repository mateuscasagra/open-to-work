<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendVerificationCodeRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Mail\ResetPasswordEmail;
use App\Mail\VerifyEmailCode;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    private const CODE_TTL_MINUTES = 15;

    private const MAX_VERIFICATION_ATTEMPTS = 5;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const REGISTER_REISSUE_THRESHOLD_MINUTES = 5;

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! config('auth.email_verification_enabled')) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            Auth::login($user);
            $request->session()->regenerate();

            return response()->json(['user' => $user->refresh()], 201);
        }

        $existing = User::query()
            ->where('email', $data['email'])
            ->whereNull('email_verified_at')
            ->first();

        if ($existing !== null) {
            // Re-cadastro com e-mail pendente: trata como retry do verify.
            // Atualiza nome/senha (usuário pode ter errado) e zera attempts.
            // Só reissue o código se o último envio foi há > threshold,
            // pra não spammar quem é dono do e-mail real.
            $existing->forceFill([
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'email_verification_attempts' => 0,
            ])->save();

            if ($this->shouldReissueOnRegister($existing)) {
                $this->issueVerificationCode($existing);
            }

            return response()->json([
                'status' => 'verification_required',
                'email' => $existing->email,
            ], 202);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

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
            'email_verification_code_sent_at' => null,
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
        // Cooldown is enforced server-side so repeated requests don't spam mail.
        if ($user && $user->email_verified_at === null && $this->canResend($user)) {
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

            // Avoid resending the code on every login attempt: only issue a new
            // one if the existing one is missing/expired AND cooldown allows it.
            if (! $this->hasActiveVerificationCode($user) && $this->canResend($user)) {
                $this->issueVerificationCode($user);
            }

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

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::query()->where('email', $email)->first();

        // Sempre 200 — não vaza se o e-mail está cadastrado.
        if ($user instanceof User) {
            $token = Password::broker()->createToken($user);
            $resetUrl = rtrim((string) config('app.frontend_url'), '/')
                . '/reset-password?token=' . urlencode($token)
                . '&email=' . urlencode($user->email);

            Mail::to($user->email)->send(new ResetPasswordEmail(
                name: $user->name,
                resetUrl: $resetUrl,
                expiresInMinutes: (int) config('auth.passwords.users.expire', 60),
            ));
        }

        return response()->json(['status' => 'sent']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ]);
                if ($user->email_verified_at === null) {
                    // Reset de senha via e-mail prova controle do endereço.
                    $user->forceFill(['email_verified_at' => now()]);
                }
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'token' => __('auth.reset.invalid_token'),
            ]);
        }

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->refresh()]);
    }

    private function issueVerificationCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'email_verification_code_sent_at' => now(),
            'email_verification_attempts' => 0,
        ])->save();

        Mail::to($user->email)->send(new VerifyEmailCode(
            name: $user->name,
            code: $code,
            expiresInMinutes: self::CODE_TTL_MINUTES,
        ));
    }

    private function hasActiveVerificationCode(User $user): bool
    {
        return $user->email_verification_code !== null
            && $user->email_verification_code_expires_at !== null
            && $user->email_verification_code_expires_at->isFuture();
    }

    private function canResend(User $user): bool
    {
        if ($user->email_verification_code_sent_at === null) {
            return true;
        }

        return $user->email_verification_code_sent_at->diffInSeconds(now()) >= self::RESEND_COOLDOWN_SECONDS;
    }

    private function shouldReissueOnRegister(User $user): bool
    {
        if ($user->email_verification_code_sent_at === null) {
            return true;
        }

        return $user->email_verification_code_sent_at->diffInMinutes(now()) >= self::REGISTER_REISSUE_THRESHOLD_MINUTES;
    }
}
