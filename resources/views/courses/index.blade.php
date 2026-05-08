@extends('adminlte::page')

@section('title', 'Gestión de Cursos')

@section('content_header')
<h1>Listado de Cursos</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <a href="{{ route('courses.create') }}" class="btn btn-primary">Añadir Curso</a>
    </div>

    {{-- ✅ Filtros de búsqueda --}}
    <div class="card-header bg-primary text-white" style="cursor:pointer;"
         data-toggle="collapse" data-target="#filtrosBody">
        <i class="fas fa-search"></i> Filtros de Búsqueda
        <span class="float-right">
            <i class="fas fa-{{ request()->hasAny(['nombre','nro_curso']) ? 'minus' : 'plus' }}"></i>
        </span>
    </div>
    <div id="filtrosBody" class="collapse {{ request()->hasAny(['nombre','nro_curso']) ? 'show' : '' }}">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('courses.index') }}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small mb-1">Nombre del Curso</label>
                            <input type="text" name="nombre" class="form-control form-control-sm"
                                   placeholder="Buscar por nombre..."
                                   value="{{ request('nombre') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-2">
                            <label class="small mb-1">Nro. de Curso</label>
                            <input type="text" name="nro_curso" class="form-control form-control-sm"
                                   placeholder="Buscar por número..."
                                   value="{{ request('nro_curso') }}">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-group mb-2 w-100">
                            <label class="small mb-1">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <a href="{{ route('courses.index') }}" class="btn btn-secondary btn-sm ml-1">
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
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        @endif
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Área</th>
                    <th>Nro. Curso</th>
                    <th>Nombre</th>
                    <th>Período</th>
                    <th>Horas</th>
                    <th>Resolución</th>
                    <th style="width: 150px">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courses as $course)
                <tr>
                    <td>{{ $course->area->nombre ?? 'Sin Área' }}</td>
                    <td>{{ $course->nro_curso }}</td>
                    <td>{{ $course->nombre }}</td>
                    <td>{{ $course->periodo }}</td>
                    <td>{{ $course->horas }} ({{ $course->tipo_horas }})</td>
                    <td>{{ $course->resolution->numero ?? 'N/A' }}</td>
                    <td class="d-flex">
                        <a href="{{ route('courses.edit', $course->id) }}" class="btn btn-sm btn-info mr-2">Editar</a>
                        <form action="{{ route('courses.destroy', $course->id) }}" method="POST" onsubmit="return confirm('¿Estás seguro?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">No hay cursos registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">
        {{ $courses->links() }}
    </div>
</div>
@stop