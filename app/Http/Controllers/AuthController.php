<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        return response()->json([
            'token' => $token,
            'user' => auth()->user()
        ]);
    }

    // Nuevo método para recuperación de contraseña
    public function recoverPassword(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Generar password temporal
        $temporaryPassword = Str::random(8);
        $user->temporaryPassword = $temporaryPassword;
        $user->save();

        // Enviar email usando EmailJS desde el backend
        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post('https://api.emailjs.com/api/v1.0/email/send', [
            'service_id' => env('EMAILJS_SERVICE_ID'),
            'template_id' => env('EMAILJS_TEMPLATE_ID'),
            'user_id' => env('EMAILJS_USER_ID'),
            'template_params' => [
                'to_name' => $user->name,
                'to_email' => $user->email,
                'temporaryPassword' => $temporaryPassword
            ]
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error enviando email'], 500);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'temporaryPassword' => $temporaryPassword
        ]);
    }
}
