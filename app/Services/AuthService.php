<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\NewAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthService
{
    /**
     * Authenticate a user and create a token
     *
     * @return array{user: User, token: NewAccessToken}
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid credentials');
        }

        $token = $this->createToken(
            $user,
            $deviceName ?? 'default-device',
            ['*'],
            config('sanctum.expiration')
            ? Carbon::now()->addMinutes(config('sanctum.expiration'))
            : null
        );

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Create an API key (long-lived token) for a user
     *
     * @param  array<string>  $abilities
     */
    public function createApiKey(User $user, string $keyName, array $abilities = ['*']): NewAccessToken
    {
        // The null expiration means the token does not expire
        return $this->createToken($user, $keyName, $abilities, null);
    }

    /**
     * Revoke a specific token by ID
     */
    public function revokeToken(User $user, int|string $tokenId): bool
    {
        $token = $user->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'Token not found');
        }

        $result = $token->delete();

        return $result !== false && $result !== null;
    }

    /**
     * Revoke all tokens for a user
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Revoke the current token
     */
    public function revokeCurrentToken(User $user): bool
    {
        $token = $user->currentAccessToken();

        $result = $token->delete();

        return $result !== false && $result !== null;
    }

    /**
     * Send password reset link to user
     */
    public function sendPasswordResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw new HttpException(
                Response::HTTP_BAD_REQUEST,
                __($status)
            );
        }

        return __($status);
    }

    /**
     * Reset user password
     */
    public function resetPassword(
        string $email,
        string $password,
        string $passwordConfirmation,
        string $token
    ): string {
        $status = Password::reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
                'token' => $token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ]);

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new HttpException(
                Response::HTTP_BAD_REQUEST,
                __($status)
            );
        }

        return __($status);
    }

    /**
     * Resend email verification notification
     */
    public function resendEmailVerification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new HttpException(
                Response::HTTP_BAD_REQUEST,
                'Email already verified'
            );
        }

        $user->sendEmailVerificationNotification();
    }

    /**
     * Get all active tokens for a user
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken>
     */
    public function getUserTokens(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->tokens;
    }

    /**
     * Refresh token - creates a new token with standard expiration
     *
     * @param  array<string>  $abilities
     */
    public function refreshToken(User $user, string $deviceName, array $abilities = ['*']): NewAccessToken
    {
        $expiresAt = config('sanctum.expiration')
            ? Carbon::now()->addMinutes(config('sanctum.expiration'))
            : null;

        return $this->createToken($user, $deviceName, $abilities, $expiresAt);
    }

    /**
     * Create a standard token for a user
     *
     * @param  array<string>  $abilities
     */
    protected function createToken(User $user, string $deviceName, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): NewAccessToken
    {
        return $user->createToken($deviceName, $abilities, $expiresAt);
    }
}
