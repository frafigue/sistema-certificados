@extends('adminlte::page')

@section('title', 'Mis Plantillas')

@section('content_header')
    <h1>
        <i class="fas fa-layer-group text-primary"></i>
        Mis Plantillas
        <small class="text-muted">Repositorio personal de diseños</small>
    </h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">
                <i class="fas fa-list"></i> Plantillas guardadas
            </h3>
            <a href="{{ route('templates.create') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Nueva plantilla
            </a>
        </div>
        <div class="card-body p-0">
            @if($templates->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No tenés plantillas guardadas todavía.</p>
                    <a href="{{ route('templates.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Crear la primera
                    </a>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Tamaño</th>
                            <th>Orientación</th>
                            @if(Auth::user()->can('is-root'))
                                <th>Creador</th>
                            @endif
                            <th>Fecha</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $tpl)
                            <tr>
                                <td>{{ $tpl->id }}</td>
                                <td>
                                    <strong>{{ $tpl->nombre }}</strong>
                                    @if($tpl->descripcion)
                                        <br><small class="text-muted">{{ Str::limit($tpl->descripcion, 60) }}</small>
                                    @endif
                                </td>
                                <td><span class="badge badge-info">{{ $tpl->paper_size }}</span></td>
                                <td>{{ $tpl->orientation === 'landscape' ? 'Horizontal' : 'Vertical' }}</td>
                                @if(Auth::user()->can('is-root'))
                                    <td>
                                        {{ $tpl->creator->name ?? '—' }}
                                        <br><small class="text-muted">#{{ $tpl->created_by }}</small>
                                    </td>
                                @endif
                                <td><small>{{ $tpl->created_at->format('d/m/Y H:i') }}</small></td>
                                <td class="text-right">
                                    <a href="{{ route('templates.design', $tpl->id) }}" class="btn btn-xs btn-warning" title="Diseñar">
                                        <i class="fas fa-paint-brush"></i>
                                    </a>
                                    <a href="{{ route('templates.show', $tpl->id) }}" class="btn btn-xs btn-info" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('templates.edit', $tpl->id) }}" class="btn btn-xs btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('templates.destroy', $tpl->id) }}" method="POST" style="display:inline-block;"
                                          onsubmit="return confirm('¿Eliminar la plantilla?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-danger" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@stop