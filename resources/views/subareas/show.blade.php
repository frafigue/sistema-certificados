@extends('adminlte::page')

@section('title', 'Detalle de Subárea')

@section('content_header')
    <h1>Detalle de Subárea</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">ID</dt>
                <dd class="col-sm-9">{{ $subarea->id }}</dd>

                <dt class="col-sm-3">Nombre</dt>
                <dd class="col-sm-9">{{ $subarea->nombre }}</dd>

                <dt class="col-sm-3">Área</dt>
                <dd class="col-sm-9">{{ $subarea->area->nombre ?? '—' }}</dd>

                <dt class="col-sm-3">Gestor</dt>
                <dd class="col-sm-9">{{ $subarea->gestor->name ?? 'Sin asignar' }}</dd>

                <dt class="col-sm-3">Estado</dt>
                <dd class="col-sm-9">
                    @if($subarea->estado == 'activa')
                        <span class="badge bg-success">Activa</span>
                    @elseif($subarea->estado == 'sin_gestor')
                        <span class="badge bg-warning">Sin gestor</span>
                    @else
                        <span class="badge bg-danger">Inactiva</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Descripción</dt>
                <dd class="col-sm-9">{{ $subarea->descripcion ?? '—' }}</dd>

                <dt class="col-sm-3">Creada por</dt>
                <dd class="col-sm-9">{{ $subarea->creador->name ?? '—' }}</dd>

                <dt class="col-sm-3">Fecha de creación</dt>
                <dd class="col-sm-9">{{ $subarea->created_at->format('d/m/Y H:i') }}</dd>
            </dl>

            <hr>

            <a href="{{ route('subareas.edit', $subarea->id) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="{{ route('subareas.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al listado
            </a>
        </div>
    </div>
@stop