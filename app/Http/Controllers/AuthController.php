<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\CreateApiKeyRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\TokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Login a user and return a token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->userAgent()
        );

        return response()->json([
            'user' => new UserResource($result['user']),
            'token' => new TokenResource($result['token']),
        ], Response::HTTP_OK);
    }

    /**
     * Logout the current user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $this->authService->revokeCurrentToken($user);

        return response()->json([
            'message' => 'Successfully logged out',
        ], Response::HTTP_OK);
    }

    /**
     * Create a new API token (long-lived) for the authenticated user
     */
    public function createApiKey(CreateApiKeyRequest $request): TokenResource
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $apiKey = $this->authService->createApiKey(
            $user,
            $request->validated('key_name'),
            $request->validated('abilities') ?? ['*']
        );

        return new TokenResource($apiKey);
    }

    /**
     * Refresh token - creates a new standard token (with expiration)
     */
    public function refreshToken(Request $request): TokenResource
    {
        /**
         * @var ?User
         */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $currentToken = $user->currentAccessToken();

        $token = $this->authService->refreshToken(
            $user,
            $request->userAgent() ?? $currentToken->name,
            $currentToken->abilities ?? []
        );

        return new TokenResource($token);
    }

    /**
     * Get all active tokens for the authenticated user
     */
    public function tokens(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $tokens = $this->authService->getUserTokens($user);

        return response()->json([
            'tokens' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                    'created_at' => $token->created_at,
                ];
            }),
        ], Response::HTTP_OK);
    }

    /**
     * Revoke a specific token by ID
     */
    public function revokeToken(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $this->authService->revokeToken($user, $tokenId);

        return response()->json([
            'message' => 'Token revoked successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Revoke all tokens for the authenticated user
     */
    public function revokeAllTokens(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $this->authService->revokeAllTokens($user);

        return response()->json([
            'message' => 'All tokens revoked successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $message = $this->authService->sendPasswordResetLink(
            $request->validated('email')
        );

        return response()->json([
            'message' => $message,
        ], Response::HTTP_OK);
    }

    /**
     * Reset password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $message = $this->authService->resetPassword(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('password_confirmation'),
            $request->validated('token')
        );

        return response()->json([
            'message' => $message,
        ], Response::HTTP_OK);
    }

    /**
     * Verify email
     */
    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid verification link');
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
            ], Response::HTTP_OK);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verified successfully',
        ], Response::HTTP_OK);
    }

    /**
     * Resend email verification notification
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $this->authService->resendEmailVerification($user);

        return response()->json([
            'message' => 'Verification email sent',
        ], Response::HTTP_OK);
    }
}
