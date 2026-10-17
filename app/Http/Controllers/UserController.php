<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Person;
use App\Models\Subarea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Area;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'area', 'subarea'])->latest()->paginate(10);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::all();
        $areas = Area::all();
        $subareas = Subarea::with('area')->get();
        return view('users.create', compact('roles', 'areas', 'subareas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'   => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'role_id'    => ['required', 'exists:roles,id'],
            'area_id'    => ['nullable', 'exists:areas,id'],
            'subarea_id' => ['nullable', 'exists:subareas,id'],
        ]);

        $role = Role::find($request->role_id);

        // Validaciones por rol
        if (in_array($role->name, ['Administrador', 'Persona']) && !$request->area_id) {
            return back()
                ->withErrors(['area_id' => 'Debe seleccionar un área.'])
                ->withInput();
        }

        if ($role->name === 'Gestor' && !$request->subarea_id) {
            return back()
                ->withErrors(['subarea_id' => 'Debe seleccionar una subárea para el gestor.'])
                ->withInput();
        }

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role_id'    => $request->role_id,
            'area_id'    => $request->area_id,
            'subarea_id' => $request->subarea_id,
            'activo'     => true,
        ]);

        if ($role->name === 'Persona') {
            Person::create([
                'user_id'   => $user->id,
                'area_id'   => $user->area_id,
                'subarea_id' => $user->subarea_id,
                'dni'       => 'PENDIENTE-' . $user->id,
                'apellido'  => $user->name,
                'nombre'    => '(completar)',
                'email'     => $user->email,
                'titulo'    => 'N/A',
                'domicilio' => 'N/A',
                'telefono'  => 'N/A',
            ]);
        }

        // Si es Gestor, actualizar la subárea con su gestor_id
        if ($role->name === 'Gestor' && $request->subarea_id) {
            $subarea = Subarea::find($request->subarea_id);
            if ($subarea) {
                $subarea->update([
                    'gestor_id' => $user->id,
                    'estado'    => 'activa',
                ]);
            }
        }

        return redirect()->route('users.index')->with('success', 'Usuario creado exitosamente.');
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $areas = Area::all();
        $subareas = Subarea::with('area')->get();
        return view('users.edit', compact('user', 'roles', 'areas', 'subareas'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role_id'    => ['required', 'exists:roles,id'],
            'area_id'    => ['nullable', 'exists:areas,id'],
            'subarea_id' => ['nullable', 'exists:subareas,id'],
            'password'   => ['nullable', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $data = $request->only('name', 'email', 'role_id', 'area_id', 'subarea_id');
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $role = Role::find($request->role_id);
        if (!in_array($role->name, ['Administrador', 'Persona'])) {
            $data['area_id'] = null;
        }

        $user->update($data);

        // Si es Gestor, actualizar la subárea con su gestor_id
        if ($role->name === 'Gestor' && $request->subarea_id) {
            $subarea = Subarea::find($request->subarea_id);
            if ($subarea) {
                $subarea->update([
                    'gestor_id' => $user->id,
                    'estado'    => 'activa',
                ]);
            }
        }

        return redirect()->route('users.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $user)
    {
        $roleName = $user->role?->name;

        if ($roleName === 'Root') {
            return redirect()->route('users.index')
                ->with('error', 'No se puede eliminar un usuario Root del sistema.');
        }

        if ($roleName === 'Administrador') {
            $user->update(['activo' => false]);
            return redirect()->route('users.index')
                ->with('success', 'El administrador "' . $user->name . '" fue desactivado exitosamente.');
        }

        $person = Person::where('user_id', $user->id)->first();

        if ($person && $person->certificates()->count() > 0) {
            return redirect()->route('users.index')
                ->with('error', 'No se puede eliminar a "' . $user->name . '" porque tiene certificados asociados.');
        }

        if ($person) {
            $person->delete();
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'Usuario eliminado exitosamente.');
    }

    public function reactivar(User $user)
    {
        $user->update(['activo' => true]);
        return redirect()->route('users.index')
            ->with('success', 'El administrador "' . $user->name . '" fue reactivado exitosamente.');
    }
}