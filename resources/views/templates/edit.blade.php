@extends('adminlte::page')

@section('title', 'Editar Plantilla')

@section('content_header')
    <h1>
        <i class="fas fa-edit text-primary"></i>
        Editar Plantilla
    </h1>
    <a href="{{ route('templates.index') }}" class="btn btn-sm btn-outline-secondary mt-2">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
@stop

@section('content')
    <div class="card">
        <form action="{{ route('templates.update', $template->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-group">
                    <label>Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $template->nombre) }}" required maxlength="255">
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2">{{ old('descripcion', $template->descripcion) }}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Tamaño <span class="text-danger">*</span></label>
                            <select name="paper_size" id="paperSizeSelect" class="form-control" required>
                                @foreach(['A4','A5','Oficio','Carta','custom'] as $s)
                                    <option value="{{ $s }}" @selected(old('paper_size', $template->paper_size) === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Orientación <span class="text-danger">*</span></label>
                            <select name="orientation" class="form-control" required>
                                <option value="landscape" @selected(old('orientation', $template->orientation) === 'landscape')>Horizontal</option>
                                <option value="portrait" @selected(old('orientation', $template->orientation) === 'portrait')>Vertical</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group" id="customFields" style="display:{{ $template->paper_size === 'custom' ? 'block' : 'none' }};">
                            <label>Ancho (cm)</label>
                            <input type="number" name="width_cm" step="0.1" min="1" max="200" class="form-control" value="{{ old('width_cm', $template->width_cm) }}">
                            <label class="mt-2">Alto (cm)</label>
                            <input type="number" name="height_cm" step="0.1" min="1" max="200" class="form-control" value="{{ old('height_cm', $template->height_cm) }}">
                        </div>
                    </div>
                </div>

            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar cambios
                </button>
                <a href="{{ route('templates.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
@stop

@section('js')
<script>
document.getElementById('paperSizeSelect').addEventListener('change', function() {
    document.getElementById('customFields').style.display = this.value === 'custom' ? 'block' : 'none';
});
</script>
@stop