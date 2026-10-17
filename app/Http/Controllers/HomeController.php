<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = Auth::user();

        // Root, Administrador y Gestor ven el dashboard
        if (in_array($user->role->name, ['Root', 'Administrador', 'Gestor'])) {
            return view('home');
        }

        // Persona ve su dashboard
        if ($user->role->name === 'Persona') {
            return redirect()->route('persona.dashboard');
        }

        // Otros (por defecto)
        return view('welcome');
    }
}