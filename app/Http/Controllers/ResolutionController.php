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

        if ($user->role?->name === 'Administrador' && $user->area_id) {
            $query->where('area_id', $user->area_id);
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
        // Obtener el usuario logueado
        $user = Auth::user();

        // Si el usuario es 'root', mostrar todas las áreas
        if ($user->role?->name === 'Root') {
            $areas = Area::all();
        } 
        // Si es un 'Administrador' con un área asignada, mostrar solo su área
        else if ($user->role?->name === 'Administrador' && $user->area_id) {
            $areas = Area::where('id', $user->area_id)->get();
        } 
        // Para cualquier otro caso (opcional, para prevenir errores), 
        // se puede devolver una colección vacía.
        else {
            $areas = collect(); 
        }

        return view('resolutions.create', compact('areas'));
    }

    public function store(Request $request)
    {
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

        // Si el usuario es 'Root', puede editar todas las áreas
        if ($user->role?->name === 'Root') {
            $areas = Area::all();
        } 
        // Si el usuario es un 'Administrador', solo puede editar su área
        else if ($user->role?->name === 'Administrador' && $user->area_id) {
            $areas = Area::where('id', $user->area_id)->get();
        } else {
            $areas = collect();
        }

        return view('resolutions.edit', compact('resolution', 'areas'));
    }

    public function update(Request $request, Resolution $resolution)
    {
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
        if ($resolution->pdf_path) {
            Storage::disk('public')->delete($resolution->pdf_path);
        }

        $resolution->delete();

        return redirect()->route('resolutions.index')
            ->with('success', 'Resolución eliminada exitosamente.');
    }
}