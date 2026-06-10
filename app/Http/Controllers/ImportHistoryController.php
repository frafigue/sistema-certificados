<?php

namespace App\Http\Controllers;

use App\Models\ImportHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportHistoryController extends Controller
{
    public function index()
    {
        $user  = Auth::user();
        $query = ImportHistory::with('user')->latest();

        // Filtrar por área si es Administrador
        if ($user->role?->name === 'Administrador' && $user->area_id) {
            $query->where('user_id', $user->id);
        }

        $imports = $query->paginate(15);

        // Totales reales (no paginados)
        $totalsQuery = ImportHistory::query();
        if ($user->role?->name === 'Administrador' && $user->area_id) {
            $totalsQuery->where('user_id', $user->id);
        }

        $totals = [
            'total'      => $totalsQuery->count(),
            'completed'  => (clone $totalsQuery)->where('status', 'completed')->count(),
            'processing' => (clone $totalsQuery)->where('status', 'processing')->count(),
            'failed'     => (clone $totalsQuery)->where('status', 'failed')->count(),
        ];

        return view('import-history.index', compact('imports', 'totals'));
    }

    public function show(ImportHistory $importHistory)
    {
        $user = Auth::user();

        // Verificar permisos
        if (
            $user->role?->name === 'Administrador' &&
            $importHistory->user_id !== $user->id
        ) {
            abort(403, 'No tenés permisos para ver esta importación.');
        }

        $importHistory->load([
            'user',
            'certificates.person',
            'certificates.course',
        ]);

        return view('import-history.show', compact('importHistory'));
    }
}