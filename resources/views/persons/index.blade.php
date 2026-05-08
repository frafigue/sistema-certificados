@extends('adminlte::page')

@section('title', 'Gestión de Personas')

@section('content_header')
    <h1>Listado de Personas</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="{{ route('persons.create') }}" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Carga Individual
                </a>
                <a href="{{ route('persons.import.form') }}" class="btn btn-success ml-2">
                    <i class="fas fa-file-upload"></i> Carga Masiva
                </a>
            </div>
            <small class="text-muted">
                Total: <strong>{{ $people->total() }}</strong> personas
            </small>
        </div>
    </div>

    {{-- ✅ Filtros de búsqueda --}}
    <div class="card-header bg-primary text-white" id="filtrosHeader" style="cursor:pointer;"
         data-toggle="collapse" data-target="#filtrosBody">
        <i class="fas fa-search"></i> Filtros de Búsqueda
        <span class="float-right">
            <i class="fas fa-{{ request()->hasAny(['dni','nombre']) ? 'minus' : 'plus' }}"></i>
        </span>
    </div>

    <div id="filtrosBody" class="collapse {{ request()->hasAny(['dni','nombre']) ? 'show' : '' }}">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('persons.index') }}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small mb-1">DNI</label>
                            <input type="text" name="dni" class="form-control form-control-sm"
                                   placeholder="Buscar por DNI..."
                                   value="{{ request('dni') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small mb-1">Nombre o Apellido</label>
                            <input type="text" name="nombre" class="form-control form-control-sm"
                                   placeholder="Buscar por nombre o apellido..."
                                   value="{{ request('nombre') }}">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-group mb-2 w-100">
                            <label class="small mb-1">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <a href="{{ route('persons.index') }}" class="btn btn-secondary btn-sm ml-1">
                                    <i class="fas fa-times"></i> Limpiar Filtros
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body">

        {{-- ✅ BOTÓN ELIMINACIÓN MASIVA --}}
        <form id="bulkDeleteForm" method="POST" action="{{ route('persons.bulkDelete') }}">
            @csrf
            @method('DELETE')

            <button type="submit" class="btn btn-danger mb-3">
                <i class="fas fa-trash"></i> Eliminar seleccionados
            </button>

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

            @if(session('import_errors'))
                <div class="alert alert-warning alert-dismissible fade show">
                    <h6><i class="fas fa-exclamation-triangle"></i> Errores en la importación:</h6>
                    <ul class="mb-0">
                        @foreach(session('import_errors') as $error)
                            <li>Fila {{ $error['fila'] }}: {{ implode(', ', $error['errores']) }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>DNI</th>
                        <th>Apellido y Nombre</th>
                        <th>Título</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th style="width: 150px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($people as $person)
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" class="checkbox-item" value="{{ $person->id }}">
                        </td>
                        <td>{{ $person->dni }}</td>
                        <td>{{ $person->apellido }}, {{ $person->nombre }}</td>
                        <td>{{ $person->titulo }}</td>
                        <td>{{ $person->email }}</td>
                        <td>{{ $person->telefono }}</td>
                        <td class="d-flex">
                            <a href="{{ route('persons.edit', $person->id) }}"
                               class="btn btn-sm btn-info mr-2" title="Editar">
                                <i class="fas fa-edit"></i> Editar
                            </a>

                            <form action="{{ route('persons.destroy', $person->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger btn-delete" title="Eliminar">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            <i class="fas fa-users fa-2x mb-2 d-block"></i>
                            No hay personas registradas.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

        </form>

    </div>

    <div class="card-footer clearfix">
        {{ $people->links() }}
    </div>
</div>
@stop

@section('css')
<style>
    .btn { margin-right: 5px; }
</style>
@stop

@section('js')
<script>
$(document).ready(function () {

    // ✅ Eliminar individual
    $('.btn-delete').on('click', function (e) {
        e.preventDefault();
        var form = $(this).closest('form');
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // ✅ Seleccionar todos
    $('#selectAll').on('change', function () {
        $('.checkbox-item').prop('checked', this.checked);
    });

    // ✅ Eliminación masiva
    $('#bulkDeleteForm').on('submit', function (e) {

        const selected = $('.checkbox-item:checked');

        if (selected.length === 0) {
            Swal.fire('Atención', 'Seleccioná al menos una persona', 'warning');
            e.preventDefault();
            return;
        }

        e.preventDefault();

        Swal.fire({
            title: '¿Seguro?',
            text: '¿Querés eliminar las personas seleccionadas?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                e.target.submit();
            }
        });
    });

});
</script>
@stop