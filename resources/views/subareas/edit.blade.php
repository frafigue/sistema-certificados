@extends('adminlte::page')

@section('title', 'Editar Subárea')

@section('content_header')
    <h1>Editar Subárea: {{ $subarea->nombre }}</h1>
@stop

@section('content')
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('subareas.update', $subarea->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="area_id">Área</label>
                    <select name="area_id" id="area_id" class="form-control" required>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}" {{ $subarea->area_id == $area->id ? 'selected' : '' }}>
                                {{ $area->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="nombre">Nombre de la Subárea</label>
                    <input type="text" name="nombre" id="nombre" class="form-control" value="{{ $subarea->nombre }}" required>
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea name="descripcion" id="descripcion" class="form-control" rows="3">{{ $subarea->descripcion }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Actualizar
                </button>
                <a href="{{ route('subareas.index') }}" class="btn btn-secondary">
                    Cancelar
                </a>
            </form>
        </div>
    </div>
@stop