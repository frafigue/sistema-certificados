@extends('adminlte::page')

@section('title', 'Crear Usuario')

@section('content_header')
    <h1>Crear Nuevo Usuario</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="name">Nombre</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>

                <div class="form-group">
                    <label for="role_id">Rol</label>
                    <select name="role_id" id="role_id" class="form-control" required>
                        <option value="">Seleccione un rol</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" data-role-name="{{ $role->name }}">
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Campo Área (oculto por defecto) --}}
                <div class="form-group" id="area-select-container" style="display: none;">
                    <label for="area_id">Área (Solo para Administradores y Personas)</label>
                    <select name="area_id" class="form-control">
                        <option value="">Seleccione un área</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Campo Subárea (oculto por defecto) --}}
                <div class="form-group" id="subarea-select-container" style="display: none;">
                    <label for="subarea_id">Subárea (Solo para Gestores)</label>
                    <select name="subarea_id" class="form-control">
                        <option value="">— Sin subárea —</option>
                        @foreach ($subareas as $subarea)
                            <option value="{{ $subarea->id }}">
                                {{ $subarea->nombre }} ({{ $subarea->area->nombre ?? '—' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirmar Contraseña</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function() {
            function toggleRoleFields() {
                var selectedRoleName = $('#role_id').find('option:selected').data('role-name');

                // Mostrar/ocultar Área
                if (selectedRoleName === 'Administrador' || selectedRoleName === 'Persona') {
                    $('#area-select-container').show();
                } else {
                    $('#area-select-container').hide();
                    $('#area-select-container select').val('');
                }

                // Mostrar/ocultar Subárea
                if (selectedRoleName === 'Gestor') {
                    $('#subarea-select-container').show();
                } else {
                    $('#subarea-select-container').hide();
                    $('#subarea-select-container select').val('');
                }
            }
            toggleRoleFields();
            $('#role_id').on('change', toggleRoleFields);
        });
    </script>
@endsection