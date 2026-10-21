@extends('adminlte::page')

@section('title', 'Ver Plantilla')

@section('content_header')
    <h1>
        <i class="fas fa-eye text-info"></i>
        {{ $template->nombre }}
    </h1>
    <a href="{{ route('templates.index') }}" class="btn btn-sm btn-outline-secondary mt-2">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">ID</dt>
                <dd class="col-sm-9">{{ $template->id }}</dd>

                <dt class="col-sm-3">Nombre</dt>
                <dd class="col-sm-9">{{ $template->nombre }}</dd>

                <dt class="col-sm-3">Descripción</dt>
                <dd class="col-sm-9">{{ $template->descripcion ?: '—' }}</dd>

                <dt class="col-sm-3">Tamaño</dt>
                <dd class="col-sm-9">{{ $template->paper_size }}</dd>

                <dt class="col-sm-3">Orientación</dt>
                <dd class="col-sm-9">{{ $template->orientation === 'landscape' ? 'Horizontal' : 'Vertical' }}</dd>

                <dt class="col-sm-3">Creador</dt>
                <dd class="col-sm-9">{{ $template->creator->name ?? '—' }} (ID {{ $template->created_by }})</dd>

                <dt class="col-sm-3">Creada</dt>
                <dd class="col-sm-9">{{ $template->created_at->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>
        <div class="card-footer">
            <a href="{{ route('templates.design', $template->id) }}" class="btn btn-warning">
                <i class="fas fa-paint-brush"></i> Diseñar
            </a>
            <a href="{{ route('templates.edit', $template->id) }}" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="{{ route('templates.preview', $template->id) }}" target="_blank" class="btn btn-info">
                <i class="fas fa-file-pdf"></i> Preview PDF
            </a>
        </div>
    </div>
@stop