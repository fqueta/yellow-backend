<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request,$api=false): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    // public function store(Request $request,$api=false): RedirectResponse
    public function store(Request $request,$api=false)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        try {
            $response = Password::sendResetLink(
                $request->only('email')
            );
            if($api) {
                return response()->json([
                    'status' => 200,
                    'message' => __('Um link de redefinição será enviado se a conta existir.'),
                    'response' => $response,
                ], 200);
            }else{
                return back()->with('status', __('Um link de redefinição será enviado se a conta existir.'));
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => $e->getMessage(),
                'message' => __('Erro ao enviar o link de redefinição de senha.'),
            ], 500);
        }
        // Password::sendResetLink(
        //     $request->only('email')
        // );
    }
}
