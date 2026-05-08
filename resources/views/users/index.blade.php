@extends('adminlte::page')

@section('title', 'Gestión de Usuarios')

@section('content_header')
<h1>Gestión de Usuarios</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <a href="{{ route('users.create') }}" class="btn btn-primary">Crear Nuevo Usuario</a>
    </div>
    <div class="card-body">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-times-circle"></i> {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="badge badge-info">{{ $user->role->name ?? 'Sin Rol' }}</span>
                    </td>
                    <td>
                        {{-- ✅ Mostrar estado activo/inactivo --}}
                        @if($user->activo)
                            <span class="badge badge-success"><i class="fas fa-check"></i> Activo</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-ban"></i> Inactivo</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap" style="gap:4px;">
                            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i> Editar
                            </a>

                            {{-- ✅ Botón Reactivar (solo para administradores inactivos) --}}
                            @if($user->role?->name === 'Administrador' && !$user->activo)
                                <form action="{{ route('users.reactivar', $user->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-success btn-reactivar">
                                        <i class="fas fa-redo"></i> Reactivar
                                    </button>
                                </form>
                            @endif

                            {{-- ✅ Botón Eliminar con lógica según rol --}}
                            @if($user->role?->name === 'Root')
                                {{-- Root: botón deshabilitado --}}
                                <button class="btn btn-sm btn-secondary" disabled title="No se puede eliminar un Root">
                                    <i class="fas fa-lock"></i> Protegido
                                </button>
                            @elseif($user->role?->name === 'Administrador' && $user->activo)
                                {{-- Administrador activo: desactivar --}}
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline delete-form"
                                      data-nombre="{{ $user->name }}"
                                      data-tipo="administrador">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <i class="fas fa-ban"></i> Desactivar
                                    </button>
                                </form>
                            @elseif($user->role?->name === 'Administrador' && !$user->activo)
                                {{-- Administrador inactivo: ya está desactivado --}}
                                <button class="btn btn-sm btn-secondary" disabled>
                                    <i class="fas fa-ban"></i> Desactivado
                                </button>
                            @else
                                {{-- Persona: eliminar --}}
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline delete-form"
                                      data-nombre="{{ $user->name }}"
                                      data-tipo="persona">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            @endif

                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="card-footer clearfix">
            {{ $users->links() }}
        </div>

    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function () {

    // ✅ Confirmación para Eliminar / Desactivar
    $('.delete-form').on('submit', function (e) {
        e.preventDefault();
        var form    = this;
        var nombre  = $(this).data('nombre');
        var tipo    = $(this).data('tipo');

        var titulo  = tipo === 'administrador'
            ? '¿Desactivar administrador?'
            : '¿Eliminar usuario?';

        var texto   = tipo === 'administrador'
            ? 'El administrador "' + nombre + '" no podrá iniciar sesión pero sus datos quedarán guardados.'
            : 'Se eliminarán permanentemente los datos de "' + nombre + '". Esta acción no se puede deshacer.';

        var boton   = tipo === 'administrador'
            ? 'Sí, desactivar'
            : 'Sí, eliminar';

        var color   = tipo === 'administrador' ? '#e0a800' : '#d33';

        Swal.fire({
            title:              titulo,
            text:               texto,
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonColor: color,
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  boton,
            cancelButtonText:   'Cancelar',
            reverseButtons:     true,
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });

    // ✅ Confirmación para Reactivar
    $('.btn-reactivar').on('click', function (e) {
        e.preventDefault();
        var form = $(this).closest('form');
        Swal.fire({
            title:              '¿Reactivar administrador?',
            text:               'El administrador podrá volver a iniciar sesión en el sistema.',
            icon:               'question',
            showCancelButton:   true,
            confirmButtonColor: '#28a745',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  'Sí, reactivar',
            cancelButtonText:   'Cancelar',
            reverseButtons:     true,
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });

});
</script>
@stop