<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class NewPasswordController extends Controller
{
    /**
     * Handle an incoming new password request for API.
     * Accepts JSON body with: email, password, password_confirmation, token.
     * Returns JSON with status and message.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Validate token before attempting reset (extra safety for API)
        $passwordBroker = config('auth.defaults.passwords');
        $expireMinutes = (int) config("auth.passwords.$passwordBroker.expire", 60);
        $table = Schema::hasTable('password_reset_tokens') ? 'password_reset_tokens' : 'password_resets';
        $record = DB::table($table)->where('email', $request->email)->first();

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => __('passwords.user'),
                'reason' => 'not_found',
                'expires_minutes' => $expireMinutes,
            ], 404);
        }

        $createdAt = isset($record->created_at) ? Carbon::parse($record->created_at) : null;
        $isExpired = $createdAt ? $createdAt->addMinutes($expireMinutes)->isPast() : false;
        $matches = isset($record->token) ? Hash::check($request->token, $record->token) : false;

        if (!$matches || $isExpired) {
            return response()->json([
                'status' => 'error',
                'message' => $isExpired ? __('passwords.token') : __('passwords.token'),
                'reason' => $isExpired ? 'expired' : 'mismatch',
                'expires_minutes' => $expireMinutes,
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET || $status === Password::PasswordReset) {
            return response()->json([
                'status' => 'success',
                'message' => 'Senha redefinida com sucesso.',
            ], 200);
        }

        $errorMessage = match ($status) {
            Password::INVALID_TOKEN => 'Token de redefinição inválido.',
            Password::INVALID_USER => 'Usuário não encontrado para o e-mail informado.',
            default => 'Não foi possível redefinir a senha.',
        };

        return response()->json([
            'status' => 'error',
            'message' => $errorMessage,
            'errors' => [
                'email' => [$errorMessage],
            ],
        ], 422);
    }

    /**
     * Validate a reset password token for API.
     * Accepts query/body: email and token, returns JSON with validity.
     */
    public function validateToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $passwordBroker = config('auth.defaults.passwords');
        $expireMinutes = (int) config("auth.passwords.$passwordBroker.expire", 60);

        $table = Schema::hasTable('password_reset_tokens') ? 'password_reset_tokens' : 'password_resets';

        $record = DB::table($table)->where('email', $request->email)->first();

        if (!$record) {
            return response()->json([
                'valid' => false,
                'reason' => 'not_found',
                'message' => 'Nenhum token de redefinição encontrado para este e-mail.',
                'expires_minutes' => $expireMinutes,
            ], 404);
        }

        $createdAt = isset($record->created_at) ? Carbon::parse($record->created_at) : null;
        $isExpired = $createdAt ? $createdAt->addMinutes($expireMinutes)->isPast() : false;

        $matches = isset($record->token) ? Hash::check($request->token, $record->token) : false;

        $valid = $matches && !$isExpired;
        $reason = $matches ? ($isExpired ? 'expired' : null) : 'mismatch';
        $message = $valid ? 'Token válido.' : ($reason === 'expired' ? 'Token expirado.' : 'Token inválido.');

        return response()->json([
            'valid' => $valid,
            'reason' => $reason,
            'message' => $message,
            'expires_minutes' => $expireMinutes,
        ], $valid ? 200 : 422);
    }
}