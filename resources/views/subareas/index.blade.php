@extends('adminlte::page')

@section('title', 'Subáreas')

@section('content_header')
    <h1>Subáreas</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <a href="{{ route('subareas.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nueva Subárea
            </a>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Área</th>
                        <th>Gestor</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subareas as $subarea)
                        <tr>
                            <td>{{ $subarea->id }}</td>
                            <td>{{ $subarea->nombre }}</td>
                            <td>{{ $subarea->area->nombre ?? '—' }}</td>
                            <td>{{ $subarea->gestor->name ?? 'Sin asignar' }}</td>
                            <td>
                                @if ($subarea->estado == 'activa')
                                    <span class="badge bg-success">Activa</span>
                                @elseif($subarea->estado == 'sin_gestor')
                                    <span class="badge bg-warning">Sin gestor</span>
                                @else
                                    <span class="badge bg-danger">Inactiva</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('subareas.show', $subarea->id) }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <a href="{{ route('subareas.edit', $subarea->id) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <form action="{{ route('subareas.destroy', $subarea->id) }}" method="POST"
                                    style="display: inline-block;"
                                    onsubmit="return confirm('¿Estás seguro de eliminar esta subárea?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No hay subáreas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
