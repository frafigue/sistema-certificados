@extends('adminlte::page')

@section('title', 'Editar Curso')

@section('content_header')
    <h1>Editar Curso</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('courses.update', $course->id) }}" method="POST" enctype="multipart/form-data" id="courseForm">
                @csrf
                @method('PUT')
                
                {{-- DATOS PRINCIPALES --}}
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nombre">Nombre del Curso</label>
                            <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $course->nombre) }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="area_id">Área Emisora</label>
                            <select name="area_id" class="form-control" required>
                                <option value="">Seleccione un área</option>
                                @foreach ($areas as $area)
                                    <option value="{{ $area->id }}" @selected(old('area_id', $course->area_id) == $area->id)>{{ $area->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="nro_curso">Nro. de Curso</label>
                            <input type="text" name="nro_curso" class="form-control" value="{{ old('nro_curso', $course->nro_curso) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="periodo">Período</label>
                            <input type="text" name="periodo" class="form-control" value="{{ old('periodo', $course->periodo) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="horas">Horas</label>
                            <input type="number" name="horas" class="form-control" value="{{ old('horas', $course->horas) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="tipo_horas">Tipo de Horas</label>
                            <select name="tipo_horas" class="form-control" required>
                                <option value="Reloj" @selected(old('tipo_horas', $course->tipo_horas) == 'Reloj')>Reloj</option>
                                <option value="Cátedra" @selected(old('tipo_horas', $course->tipo_horas) == 'Cátedra')>Cátedra</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="resolution_id">Resolución Asociada</label>
                            <select name="resolution_id" class="form-control" required>
                                <option value="">Seleccione una resolución</option>
                                @foreach ($resolutions as $resolution)
                                    <option value="{{ $resolution->id }}" @selected(old('resolution_id', $course->resolution_id) == $resolution->id)>
                                        {{ $resolution->numero }} ({{ $resolution->anio }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {{-- ✅ Máxima Nota es opcional --}}
                            <label for="maxima_nota">Máxima Nota <small class="text-muted">(opcional)</small></label>
                            <input type="number" name="maxima_nota" class="form-control" value="{{ old('maxima_nota', $course->maxima_nota) }}">
                        </div>
                    </div>
                </div>

                {{-- <label for="objetivo">Objetivo</label>
                    <textarea name="objetivo" class="form-control" rows="3">{{ old('objetivo', $course->objetivo) }}</textarea>
                </div>

                <div class="form-group">
                    <label for="contenido">Contenido</label>
                    <textarea name="contenido" class="form-control" rows="3">{{ old('contenido', $course->contenido) }}</textarea>
                </div>

                <hr>--}}
                
                {{-- SECCIÓN DE RESPONSABLES DINÁMICOS --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Responsables y Firmas</h5>
                    <button type="button" class="btn btn-success btn-sm" id="addResponsable">
                        <i class="fas fa-plus"></i> Agregar Responsable
                    </button>
                </div>

                <div id="responsablesContainer">
                    @if($course->responsables->count() > 0)
                        @foreach($course->responsables as $index => $responsable)
                            <div class="responsable-item card mb-3" data-index="{{ $index }}">
                                <div class="card-body">
                                    <input type="hidden" name="responsables[{{ $index }}][id]" value="{{ $responsable->id }}">
                                    
                                    <div class="row">
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Nombre del Responsable</label>
                                                <input type="text" name="responsables[{{ $index }}][nombre]" class="form-control" value="{{ old('responsables.'.$index.'.nombre', $responsable->nombre) }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Cargo/Rol</label>
                                                <input type="text" name="responsables[{{ $index }}][cargo]" class="form-control" value="{{ old('responsables.'.$index.'.cargo', $responsable->cargo) }}" placeholder="Ej: Capacitador, Coordinador">
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-right">
                                            <label class="d-block">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm remove-responsable" data-id="{{ $responsable->id }}">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Subir Firma (PNG)</label>
                                                <input type="file" name="responsables[{{ $index }}][signature]" class="form-control-file" accept="image/png">
                                                <small class="form-text text-muted">Sube un archivo nuevo solo si deseas reemplazar la firma actual.</small>
                                                
                                                @if($responsable->signature_path)
                                                    <div class="mt-2">
                                                        <label>Firma Actual:</label><br>
                                                        <img src="{{ asset('storage/' . $responsable->signature_path) }}" alt="Firma" height="60" class="border p-1">
                                                        <div class="form-check mt-2">
                                                            <input type="checkbox" name="responsables[{{ $index }}][remove_signature]" value="1" class="form-check-input" id="removeSignature{{ $index }}">
                                                            <label class="form-check-label" for="removeSignature{{ $index }}">
                                                                Eliminar firma actual
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="alert alert-info">
                            No hay responsables agregados. Haz clic en "Agregar Responsable" para comenzar.
                        </div>
                    @endif
                </div>

                <input type="hidden" name="deleted_responsables" id="deletedResponsables" value="">

                <button type="submit" class="btn btn-primary mt-4">
                    <i class="fas fa-save"></i> Actualizar Curso
                </button>
                <a href="{{ route('courses.index') }}" class="btn btn-secondary mt-4">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </form>
        </div>
    </div>
@stop

@section('js')
<script>
    let responsableIndex = {{ $course->responsables->count() }};
    let deletedResponsables = [];

    document.getElementById('addResponsable').addEventListener('click', function() {
        const container = document.getElementById('responsablesContainer');
        const alert = container.querySelector('.alert-info');
        if (alert) alert.remove();
        
        const newResponsable = `
            <div class="responsable-item card mb-3" data-index="${responsableIndex}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Nombre del Responsable</label>
                                <input type="text" name="responsables[${responsableIndex}][nombre]" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cargo/Rol</label>
                                <input type="text" name="responsables[${responsableIndex}][cargo]" class="form-control" placeholder="Ej: Capacitador, Coordinador">
                            </div>
                        </div>
                        <div class="col-md-3 text-right">
                            <label class="d-block">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm remove-responsable">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Subir Firma (PNG)</label>
                                <input type="file" name="responsables[${responsableIndex}][signature]" class="form-control-file" accept="image/png">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', newResponsable);
        responsableIndex++;
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-responsable') || e.target.closest('.remove-responsable')) {
            const button = e.target.classList.contains('remove-responsable') ? e.target : e.target.closest('.remove-responsable');
            const responsableItem = button.closest('.responsable-item');
            const responsableId = button.getAttribute('data-id');
            if (confirm('¿Estás seguro de eliminar este responsable?')) {
                if (responsableId) {
                    deletedResponsables.push(responsableId);
                    document.getElementById('deletedResponsables').value = JSON.stringify(deletedResponsables);
                }
                responsableItem.remove();
                const container = document.getElementById('responsablesContainer');
                if (container.children.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">No hay responsables agregados. Haz clic en "Agregar Responsable" para comenzar.</div>';
                }
            }
        }
    });

    document.getElementById('courseForm').addEventListener('submit', function(e) {
        const responsables = document.querySelectorAll('.responsable-item');
        if (responsables.length === 0) {
            if (!confirm('No has agregado ningún responsable. ¿Deseas continuar?')) {
                e.preventDefault();
            }
        }
    });
</script>
@stop

@section('css')
<style>
    .responsable-item { transition: all 0.3s ease; }
    .responsable-item:hover { box-shadow: 0 0 10px rgba(0,0,0,0.1); }
</style>
@stop