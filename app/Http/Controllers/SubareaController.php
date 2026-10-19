<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Subarea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubareaController extends Controller
{
    /**
     * Listar subáreas (filtradas por rol)
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->role_id == 1) {
            // Root: ve todas
            $subareas = Subarea::with(['area', 'gestor'])->get();
        } elseif ($user->role_id == 2) {
            // Admin: ve las subáreas de su área
            if ($user->area_id) {
                $subareas = Subarea::where('area_id', $user->area_id)
                    ->with(['area', 'gestor'])
                    ->get();
            } else {
                $subareas = collect(); // Sin área asignada
            }
        } else {
            // Otros: solo su subárea
            $subareas = Subarea::where('id', $user->subarea_id)->with(['area', 'gestor'])->get();
        }

        return view('subareas.index', compact('subareas'));
    }

    /**
     * Formulario de creación
     */
    public function create()
    {
        $user = Auth::user();

        if ($user->role_id == 1) {
            $areas = Area::all();
        } else {
            $areas = Area::where('user_id', $user->id)->get();
        }

        return view('subareas.create', compact('areas'));
    }

    /**
     * Guardar subárea
     */
    public function store(Request $request)
    {
        $request->validate([
            'area_id' => 'required|exists:areas,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        Subarea::create([
            'area_id' => $request->area_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'gestor_id' => null,
            'estado' => 'sin_gestor',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('subareas.index')
            ->with('success', 'Subárea creada. Contactá al Root para asignar un gestor.');
    }

    /**
     * Ver detalle
     */
    public function show(string $id)
    {
        $subarea = Subarea::with(['area', 'gestor'])->findOrFail($id);
        return view('subareas.show', compact('subarea'));
    }

    /**
     * Formulario de edición
     */
    public function edit(string $id)
    {
        $subarea = Subarea::findOrFail($id);
        $areas = Area::all();

        return view('subareas.edit', compact('subarea', 'areas'));
    }

    /**
     * Actualizar subárea
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'area_id' => 'required|exists:areas,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $subarea = Subarea::findOrFail($id);
        $subarea->update([
            'area_id' => $request->area_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
        ]);

        return redirect()->route('subareas.index')
            ->with('success', 'Subárea actualizada.');
    }

    /**
     * Eliminar subárea
     */
    public function destroy(string $id)
    {
        $subarea = Subarea::findOrFail($id);
        $subarea->delete();

        return redirect()->route('subareas.index')
            ->with('success', 'Subárea eliminada.');
    }
}