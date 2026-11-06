<?php

namespace App\Http\Controllers;

use App\Models\ImportHistory;
use Illuminate\Support\Facades\Auth;

class ImportHistoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $alcance = function ($query) use ($user) {
            if ($user->esRoot()) {
                return;
            }

            if ($user->esAdministrador() && $user->area_id) {
                $query->whereHas('user', function ($q) use ($user) {
                    $q->where('area_id', $user->area_id);
                });

                return;
            }

            if ($user->esGestor()) {
                $query->where('user_id', $user->id);

                return;
            }

            $query->whereRaw('1 = 0');
        };

        $query = ImportHistory::with('user')->latest();
        $alcance($query);

        $imports = $query->paginate(15);

        // Totales reales (no paginados)
        $totalsQuery = ImportHistory::query();
        $alcance($totalsQuery);

        $totals = [
            'total' => $totalsQuery->count(),
            'completed' => (clone $totalsQuery)->where('status', 'completed')->count(),
            'processing' => (clone $totalsQuery)->where('status', 'processing')->count(),
            'failed' => (clone $totalsQuery)->where('status', 'failed')->count(),
        ];

        return view('import-history.index', compact('imports', 'totals'));
    }

    public function show(ImportHistory $importHistory)
    {
        $user = Auth::user();

        abort_unless(
            $user->puedeVerImportacion($importHistory),
            403,
            'No tenés permisos para ver esta importación.'
        );

        $importHistory->load([
            'user',
            'certificates.person',
            'certificates.course',
        ]);

        return view('import-history.show', compact('importHistory'));
    }
}
