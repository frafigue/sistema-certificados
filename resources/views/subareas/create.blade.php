@extends('adminlte::page')

@section('title', 'Nueva Subárea')

@section('content_header')
    <h1>Nueva Subárea</h1>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('subareas.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="area_id">Área</label>
                    <select name="area_id" id="area_id" class="form-control" required>
                        <option value="">— Seleccionar área —</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="nombre">Nombre de la Subárea</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción (opcional)</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="gestor_id">Gestor (opcional)</label>
                    <select name="gestor_id" id="gestor_id" class="form-control">
                        <option value="">— Sin gestor —</option>
                        @foreach ($gestores ?? [] as $gestor)
                            <option value="{{ $gestor->id }}">{{ $gestor->name }} ({{ $gestor->email }})</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Podés asignar un gestor existente. Para crear uno nuevo, andá a "Gestión de
                        Usuarios".</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <a href="{{ route('subareas.index') }}" class="btn btn-secondary">
                    Cancelar
                </a>
            </form>
        </div>
    </div>
@stop
