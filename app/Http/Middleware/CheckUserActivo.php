<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserActivo
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ Si hay un usuario logueado y está inactivo → expulsarlo
        if (Auth::check() && !Auth::user()->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors([
                    'email' => 'Tu cuenta ha sido desactivada. Contactá al administrador del sistema.'
                ]);
        }

        return $next($request);
    }
}