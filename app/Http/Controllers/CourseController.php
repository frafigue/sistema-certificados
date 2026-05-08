<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseResponsable;
use App\Models\Resolution;
use Illuminate\Support\Facades\Storage;
use App\Models\Area;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = Course::query();

        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            $query->where('area_id', $user->area_id);
        }

        // ✅ Filtros de búsqueda
        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }
        if ($request->filled('nro_curso')) {
            $query->where('nro_curso', 'like', '%' . $request->nro_curso . '%');
        }

        $courses = $query->with(['resolution', 'area', 'responsables'])
                     ->latest()
                     ->paginate(10)
                     ->withQueryString();
        return view('courses.index', compact('courses'));
    }

    public function create()
    {
        $user = Auth::user();
        
        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            $resolutions = Resolution::where('area_id', $user->area_id)->get();
            $areas = Area::where('id', $user->area_id)->get();
        } else {
            $resolutions = Resolution::all();
            $areas = Area::all();
        }

        // ✅ Calcular el próximo número de curso (global)
        $nextNroCurso = (int)(Course::max('nro_curso') ?? 0) + 1;

        return view('courses.create', compact('resolutions', 'areas', 'nextNroCurso'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'area_id'        => 'required|exists:areas,id',
            'nro_curso'      => 'required|string|max:255',
            'nombre'         => 'required|string|max:255',
            'periodo'        => 'required|string|max:255',
            'horas'          => 'required|integer|min:1',
            'tipo_horas'     => 'required|in:Reloj,Cátedra',
            'resolution_id'  => 'required|exists:resolutions,id',
            'objetivo'       => 'nullable|string',
            'contenido'      => 'nullable|string',
            'maxima_nota'    => 'nullable|integer|min:1',

            'responsables'             => 'nullable|array',
            'responsables.*.nombre'    => 'required|string|max:255',
            'responsables.*.cargo'     => 'nullable|string|max:255',
            'responsables.*.signature' => 'nullable|image|mimes:png|max:2048',
        ]);

        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            if ($data['area_id'] != $user->area_id) {
                return redirect()->back()
                    ->withErrors(['area_id' => 'No tienes permisos para crear cursos en esta área.'])
                    ->withInput();
            }
            $resolution = Resolution::findOrFail($data['resolution_id']);
            if ($resolution->area_id != $user->area_id) {
                return redirect()->back()
                    ->withErrors(['resolution_id' => 'No tienes permisos para usar esta resolución.'])
                    ->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $course = Course::create([
                'area_id'       => $data['area_id'],
                'nro_curso'     => $data['nro_curso'],
                'nombre'        => $data['nombre'],
                'periodo'       => $data['periodo'],
                'horas'         => $data['horas'],
                'tipo_horas'    => $data['tipo_horas'],
                'resolution_id' => $data['resolution_id'],
                'objetivo'      => $data['objetivo'],
                'contenido'     => $data['contenido'],
                'maxima_nota'   => $data['maxima_nota'] ?? null,
            ]);

            if ($request->has('responsables')) {
                foreach ($request->responsables as $index => $responsableData) {
                    $signaturePath = null;
                    if (isset($responsableData['signature']) && $responsableData['signature']) {
                        $signaturePath = $responsableData['signature']->store('signatures', 'public');
                    }
                    CourseResponsable::create([
                        'course_id'      => $course->id,
                        'nombre'         => $responsableData['nombre'],
                        'cargo'          => $responsableData['cargo'] ?? null,
                        'signature_path' => $signaturePath,
                        'orden'          => $index + 1,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('courses.index')->with('success', 'Curso creado exitosamente.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors(['error' => 'Error al crear el curso: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function show(Course $course)
    {
        $user = Auth::user();
        
        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            if ($course->area_id != $user->area_id) {
                abort(403, 'No tienes permisos para ver este curso.');
            }
        }

        $course->load('responsables');
        return view('courses.show', compact('course'));
    }

    public function edit(Course $course)
    {
        $user = Auth::user();
        
        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            if ($course->area_id != $user->area_id) {
                abort(403, 'No tienes permisos para editar este curso.');
            }
        }

        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            $resolutions = Resolution::where('area_id', $user->area_id)->get();
            $areas = Area::where('id', $user->area_id)->get();
        } else {
            $resolutions = Resolution::all();
            $areas = Area::all();
        }

        $course->load('responsables');
        return view('courses.edit', compact('course', 'resolutions', 'areas'));
    }

    public function update(Request $request, Course $course)
    {
        $user = Auth::user();
        
        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            if ($course->area_id != $user->area_id) {
                abort(403, 'No tienes permisos para actualizar este curso.');
            }
        }

        $data = $request->validate([
            'area_id'        => 'required|exists:areas,id',
            'nro_curso'      => 'required|string|max:255',
            'nombre'         => 'required|string|max:255',
            'periodo'        => 'required|string|max:255',
            'horas'          => 'required|integer|min:1',
            'tipo_horas'     => 'required|in:Reloj,Cátedra',
            'resolution_id'  => 'required|exists:resolutions,id',
            'objetivo'       => 'nullable|string',
            'contenido'      => 'nullable|string',
            'maxima_nota'    => 'nullable|integer|min:1',

            'responsables'                    => 'nullable|array',
            'responsables.*.id'               => 'nullable|exists:course_responsables,id',
            'responsables.*.nombre'           => 'required|string|max:255',
            'responsables.*.cargo'            => 'nullable|string|max:255',
            'responsables.*.signature'        => 'nullable|image|mimes:png|max:2048',
            'responsables.*.remove_signature' => 'nullable|boolean',

            'deleted_responsables'   => 'nullable|array',
            'deleted_responsables.*' => 'exists:course_responsables,id',
        ]);

        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            if ($data['area_id'] != $user->area_id) {
                return redirect()->back()
                    ->withErrors(['area_id' => 'No puedes mover cursos fuera de tu área asignada.'])
                    ->withInput();
            }
            $resolution = Resolution::findOrFail($data['resolution_id']);
            if ($resolution->area_id != $user->area_id) {
                return redirect()->back()
                    ->withErrors(['resolution_id' => 'No puedes usar esta resolución.'])
                    ->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $course->update([
                'area_id'       => $data['area_id'],
                'nro_curso'     => $data['nro_curso'],
                'nombre'        => $data['nombre'],
                'periodo'       => $data['periodo'],
                'horas'         => $data['horas'],
                'tipo_horas'    => $data['tipo_horas'],
                'resolution_id' => $data['resolution_id'],
                'objetivo'      => $data['objetivo'],
                'contenido'     => $data['contenido'],
                'maxima_nota'   => $data['maxima_nota'] ?? null,
            ]);

            if ($request->has('deleted_responsables')) {
                foreach ($request->deleted_responsables as $deletedId) {
                    $responsable = CourseResponsable::find($deletedId);
                    if ($responsable && $responsable->course_id == $course->id) {
                        if ($responsable->signature_path) {
                            Storage::disk('public')->delete($responsable->signature_path);
                        }
                        $responsable->delete();
                    }
                }
            }

            if ($request->has('responsables')) {
                foreach ($request->responsables as $index => $responsableData) {
                    if (isset($responsableData['id']) && $responsableData['id']) {
                        $responsable = CourseResponsable::find($responsableData['id']);
                        if ($responsable && $responsable->course_id == $course->id) {
                            $updateData = [
                                'nombre' => $responsableData['nombre'],
                                'cargo'  => $responsableData['cargo'] ?? null,
                                'orden'  => $index + 1,
                            ];
                            if (isset($responsableData['signature']) && $responsableData['signature']) {
                                if ($responsable->signature_path) {
                                    Storage::disk('public')->delete($responsable->signature_path);
                                }
                                $updateData['signature_path'] = $responsableData['signature']->store('signatures', 'public');
                            }
                            if (isset($responsableData['remove_signature']) && $responsableData['remove_signature']) {
                                if ($responsable->signature_path) {
                                    Storage::disk('public')->delete($responsable->signature_path);
                                }
                                $updateData['signature_path'] = null;
                            }
                            $responsable->update($updateData);
                        }
                    } else {
                        $signaturePath = null;
                        if (isset($responsableData['signature']) && $responsableData['signature']) {
                            $signaturePath = $responsableData['signature']->store('signatures', 'public');
                        }
                        CourseResponsable::create([
                            'course_id'      => $course->id,
                            'nombre'         => $responsableData['nombre'],
                            'cargo'          => $responsableData['cargo'] ?? null,
                            'signature_path' => $signaturePath,
                            'orden'          => $index + 1,
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('courses.index')->with('success', 'Curso actualizado exitosamente.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors(['error' => 'Error al actualizar el curso: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Course $course)
    {
        $user = Auth::user();
        
        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            if ($course->area_id != $user->area_id) {
                abort(403, 'No tienes permisos para eliminar este curso.');
            }
        }

        DB::beginTransaction();
        try {
            foreach ($course->responsables as $responsable) {
                if ($responsable->signature_path) {
                    Storage::disk('public')->delete($responsable->signature_path);
                }
            }
            if ($course->signature1_path) {
                Storage::disk('public')->delete($course->signature1_path);
            }
            if ($course->signature2_path) {
                Storage::disk('public')->delete($course->signature2_path);
            }
            $course->delete();

            DB::commit();
            return redirect()->route('courses.index')->with('success', 'Curso eliminado exitosamente.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors(['error' => 'Error al eliminar el curso: ' . $e->getMessage()]);
        }
    }
}