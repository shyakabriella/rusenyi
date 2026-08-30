<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegisterController extends BaseController
{
    /**
     * Login.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation error.',
                $validator->errors(),
                422
            );
        }

        if (!Auth::attempt([
            'email' => $request->email,
            'password' => $request->password,
        ])) {
            return $this->sendError(
                'Invalid email or password.',
                null,
                401
            );
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_active || $user->status !== 'active') {
            Auth::logout();

            return $this->sendError(
                'Your account is not active. Please contact the administrator.',
                null,
                403
            );
        }

        // One active login session per user.
        $user->tokens()->delete();

        $token = $user
            ->createToken('gihombo-auth-token')
            ->plainTextToken;

        $user->update([
            'last_login_at' => now(),
        ]);

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',

            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'must_change_password' => $user->must_change_password,
            ],
        ], 'Login successful.');
    }

    /**
     * Logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->sendResponse(
            null,
            'Logout successful.'
        );
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation error.',
                $validator->errors(),
                422
            );
        }

        /** @var User $user */
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->sendError(
                'Current password is incorrect.',
                null,
                422
            );
        }

        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
        ]);

        // Revoke old sessions.
        $user->tokens()->delete();

        $token = $user
            ->createToken('gihombo-auth-token')
            ->plainTextToken;

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'must_change_password' => false,
        ], 'Password changed successfully.');
    }

    /**
     * Forgot password.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation error.',
                $validator->errors(),
                422
            );
        }

        Password::sendResetLink([
            'email' => $request->email,
        ]);

        // Do not expose whether an email exists.
        return $this->sendResponse(
            null,
            'If the email exists in our system, password reset instructions have been sent.'
        );
    }

    /**
     * Reset forgotten password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
            ],
            'token' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation error.',
                $validator->errors(),
                422
            );
        }

        $status = Password::reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->sendError(
                __($status),
                null,
                422
            );
        }

        return $this->sendResponse(
            null,
            'Password reset successfully.'
        );
    }
}
