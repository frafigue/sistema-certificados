<?php

namespace App\Http\Controllers;

use App\Models\Resolution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Area;


class ResolutionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = Resolution::query();

        // Root ve todas
        // Admin ve su área
        // Gestor ve su subárea (a través de la relación con el área)
        if ($user->role?->name === 'Administrador' && $user->area_id) {
            $query->where('area_id', $user->area_id);
        } elseif ($user->role?->name === 'Gestor' && $user->subarea_id) {
            $subarea = $user->subarea;
            if ($subarea) {
                $query->where('area_id', $subarea->area_id);
            }
        }

        // ✅ Filtros de búsqueda
        if ($request->filled('numero')) {
            $query->where('numero', 'like', '%' . $request->numero . '%');
        }
        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }

        $resolutions = $query->latest()->paginate(10)->withQueryString();
        return view('resolutions.index', compact('resolutions'));
    }

    public function create()
    {
        $user = Auth::user();

        if ($user->role?->name === 'Root') {
            $areas = Area::all();
        } elseif ($user->role?->name === 'Administrador' && $user->area_id) {
            $areas = Area::where('id', $user->area_id)->get();
        } elseif ($user->role?->name === 'Gestor' && $user->subarea_id) {
            $subarea = $user->subarea;
            $areas   = Area::where('id', $subarea->area_id)->get();
        } else {
            $areas = collect();
        }

        return view('resolutions.create', compact('areas'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();  // ← AGREGAR ESTA LÍNEA
        // Validar los datos recibidos
        $data = $request->validate([
            'numero' => [
                'required',
                'string',
                'max:255',
                Rule::unique('resolutions')->where(function ($query) use ($request) {
                    return $query->where('anio', $request->anio)
                        ->whereNull('deleted_at');
                }),
            ],
            'anio' => 'required|integer|digits:4|min:1901|max:2155',
            'area_id' => 'required|exists:areas,id',
            'pdf_file' => 'required|file|mimes:pdf',
        ]);

        // Validación para Gestor
        if ($user->role?->name === 'Gestor' && $user->subarea_id) {
            $subarea = $user->subarea;
            if ($data['area_id'] != $subarea->area_id) {
                return redirect()->back()
                    ->withErrors(['area_id' => 'No tienes permisos para crear resoluciones en esta área.'])
                    ->withInput();
            }
        }

        $filePath = $request->file('pdf_file')->store('resolutions', 'public');
        $area = Area::findOrFail($request->area_id);
        Resolution::create([
            'numero' => $request->numero,
            'anio' => $request->anio,
            'area_id' => $area->id,
            'area' => $area->nombre,
            'pdf_path' => $filePath,
        ]);
        return redirect()->route('resolutions.index')
            ->with('success', 'Resolución creada exitosamente.');
    }


    public function edit(Resolution $resolution)
    {
        $user = Auth::user();

        // Validación para Gestor (solo puede editar resoluciones de su área)
        if ($user->role?->name === 'Gestor' && $user->subarea_id) {
            $subarea = $user->subarea;
            if ($resolution->area_id != $subarea->area_id) {
                abort(403, 'No tienes permisos para editar esta resolución.');
            }
        }

        if ($user->role?->name === 'Root') {
            $areas = Area::all();
        } elseif ($user->role?->name === 'Administrador' && $user->area_id) {
            $areas = Area::where('id', $user->area_id)->get();
        } elseif ($user->role?->name === 'Gestor' && $user->subarea_id) {
            $subarea = $user->subarea;
            $areas   = Area::where('id', $subarea->area_id)->get();
        } else {
            $areas = collect();
        }

        return view('resolutions.edit', compact('resolution', 'areas'));
    }

    public function update(Request $request, Resolution $resolution)
    {
        $user = Auth::user();  // ← CAPTURAR

        // Validación para Gestor
        if ($user->role?->name === 'Gestor' && $user->subarea_id) {
            $subarea = $user->subarea;
            if ($resolution->area_id != $subarea->area_id) {
                abort(403, 'No tienes permisos para actualizar esta resolución.');
            }
        }

        $data = $request->validate([
            'numero' => [
                'required',
                'string',
                'max:255',
                Rule::unique('resolutions')->where(function ($query) use ($request) {
                    return $query->where('anio', $request->anio)
                        ->whereNull('deleted_at');
                })->ignore($resolution->id),
            ],
            'anio' => 'required|integer|digits:4|min:1901|max:2155',
            'area_id' => 'required|exists:areas,id',
            'pdf_file' => 'nullable|file|mimes:pdf',
        ]);

        $data = $request->only(['numero', 'anio', 'area']);
        if ($request->hasFile('pdf_file')) {
            Storage::disk('public')->delete($resolution->pdf_path);
            $data['pdf_path'] = $request->file('pdf_file')->store('resolutions', 'public');
        }
        $resolution->update($data);
        return redirect()->route('resolutions.index')
            ->with('success', 'Resolución actualizada exitosamente.');
    }

    public function destroy(Resolution $resolution)
    {
        $user = Auth::user();

        // Validación para Gestor
        if ($user->role?->name === 'Gestor' && $user->subarea_id) {
            $subarea = $user->subarea;
            if ($resolution->area_id != $subarea->area_id) {
                abort(403, 'No tienes permisos para eliminar esta resolución.');
            }
        }

        if ($resolution->pdf_path) {
            Storage::disk('public')->delete($resolution->pdf_path);
        }

        $resolution->delete();

        return redirect()->route('resolutions.index')
            ->with('success', 'Resolución eliminada exitosamente.');
    }
}