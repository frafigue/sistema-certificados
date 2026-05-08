@extends('adminlte::page')
@section('title', 'Gestión de Áreas')
@section('content_header')
<h1>Gestión de Áreas</h1>
@stop
@section('content')
<div class="card">
    <div class="card-header">
        @can('is-root')
            <a href="{{ route('areas.create') }}" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Crear Nueva Área
            </a>
        @endcan
    </div>

    {{-- ✅ Filtros de búsqueda --}}
    <div class="card-header bg-primary text-white" style="cursor:pointer;"
         data-toggle="collapse" data-target="#filtrosBody">
        <i class="fas fa-search"></i> Filtros de Búsqueda
        <span class="float-right">
            <i class="fas fa-{{ request()->hasAny(['nombre']) ? 'minus' : 'plus' }}"></i>
        </span>
    </div>
    <div id="filtrosBody" class="collapse {{ request()->hasAny(['nombre']) ? 'show' : '' }}">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('areas.index') }}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small mb-1">Nombre del Área</label>
                            <input type="text" name="nombre" class="form-control form-control-sm"
                                   placeholder="Buscar por nombre..."
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
                                <a href="{{ route('areas.index') }}" class="btn btn-secondary btn-sm ml-1">
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

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th class="text-center">Plantillas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($areas as $area)
                <tr>
                    <td>{{ $area->nombre }}</td>
                    <td>{{ $area->descripcion }}</td>
                    <td class="text-center">
                        @if($area->template_front && $area->template_back)
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> Configuradas
                            </span>
                        @else
                            <span class="badge badge-warning">
                                <i class="fas fa-exclamation-triangle"></i> Sin plantillas
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap" style="gap:4px;">
                            <a href="{{ route('areas.edit', $area->id) }}"
                               class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <a href="{{ route('areas.design', $area->id) }}"
                               class="btn btn-sm btn-warning">
                                <i class="fas fa-paint-brush"></i> Diseñar
                            </a>
                            @if($area->template_front && $area->template_back)
                                <a href="{{ route('areas.preview', $area->id) }}"
                                   target="_blank"
                                   class="btn btn-sm btn-secondary">
                                    <i class="fas fa-eye"></i> Preview
                                </a>
                            @endif
                            @can('is-root')
                                <form action="{{ route('areas.destroy', $area->id) }}"
                                      method="POST"
                                      class="d-inline delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{ $areas->links() }}

    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function () {
    $('.delete-form').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: '¿Eliminar área?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@stop