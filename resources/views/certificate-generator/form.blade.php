@extends('adminlte::page')

@section('title', 'Generación Masiva de Certificados')

@section('content_header')
    <h1>
        <i class="fas fa-layer-group text-success"></i>
        Generación Masiva de Certificados
    </h1>
    <div class="mt-2">
        <a href="{{ route('certificates.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Certificados
        </a>
    </div>
@stop

@section('content')
<div class="row">

    <div class="col-md-8">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <strong>¡Éxito!</strong> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        @if(session('batch_id'))
        <div class="card card-info mt-3" id="batchProgressCard">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="fas fa-sync-alt fa-spin"></i>
                    Procesando certificados
                </h5>
            </div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 30px;">
                    <div
                        id="batchProgressBar"
                        class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                        role="progressbar"
                        style="width: 0%"
                    >
                        0%
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-md-3">
                        <h4 id="jobsProcessed">0</h4>
                        <small>Procesados</small>
                    </div>
                    <div class="col-md-3">
                        <h4 id="jobsPending">0</h4>
                        <small>Pendientes</small>
                    </div>
                    <div class="col-md-3">
                        <h4 id="jobsFailed">0</h4>
                        <small>Fallidos</small>
                    </div>
                    <div class="col-md-3">
                        <h4 id="jobsTotal">0</h4>
                        <small>Total</small>
                    </div>
                </div>
                <div class="alert alert-light mt-3 mb-0">
                    <i class="fas fa-info-circle"></i>
                    El progreso se actualiza automáticamente.
                </div>
            </div>
        </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <strong>Atención!</strong> {{ session('warning') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-times-circle"></i> <strong>Error!</strong> {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <h6><i class="fas fa-times-circle"></i> Errores de validación:</h6>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        {{-- RESUMEN si viene del Paso 2 --}}
        @if(session('import_summary'))
            @php $summary = session('import_summary'); @endphp
            <div class="card card-success">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar"></i> Resumen del proceso
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-outline card-success mb-0">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-certificate text-success"></i> Certificados generados
                                    </h6>
                                </div>
                                <div class="card-body py-2">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td><span class="badge badge-success">✔</span> Generados</td>
                                            <td><strong>{{ $summary['certificados']['generados'] }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-danger">✘</span> Errores</td>
                                            <td><strong>{{ $summary['certificados']['errores'] }}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ERRORES del Paso 2 --}}
        @if(session('import_errors') && count(session('import_errors')) > 0)
            @php $errorsList = session('import_errors'); $maxToShow = 30; @endphp
            <div class="alert alert-warning alert-dismissible fade show">
                <h5><i class="fas fa-exclamation-triangle"></i> Errores ({{ count($errorsList) }} total)</h5>
                @foreach(array_slice($errorsList, 0, $maxToShow) as $error)
                    <div class="mb-2 p-2 border border-warning rounded bg-white">
                        @if(is_array($error))
                            <strong class="text-danger">Fila {{ $error['fila'] ?? 'N/A' }}:</strong>
                            {{ implode(', ', $error['errores'] ?? []) }}
                            @if(!empty($error['valores']))
                                <div class="mt-1">
                                    <small class="text-muted">
                                        <strong>Valores:</strong>
                                        {{ implode(' | ', array_filter((array) $error['valores'])) }}
                                    </small>
                                </div>
                            @endif
                        @else
                            {{ $error }}
                        @endif
                    </div>
                @endforeach
                @if(count($errorsList) > $maxToShow)
                    <p class="mb-0 text-muted small mt-2">
                        Mostrando {{ $maxToShow }} de {{ count($errorsList) }} errores.
                    </p>
                @endif
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        {{-- FLUJO --}}
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle"></i> ¿Cómo funciona?</h6>
            <ol class="mb-0 small">
                <li>Seleccioná el <strong>curso</strong> y completá los campos comunes</li>
                <li>Hacé clic en <strong>"Generar Excel"</strong> — el archivo se descarga automáticamente</li>
                <li>Abrí el Excel y completá la columna <strong>nota</strong> (amarilla) si corresponde</li>
                <li>Subí el archivo en el <strong>Paso 2</strong> para emitir los certificados y enviar emails</li>
            </ol>
        </div>

        {{-- ============================================================ --}}
        {{-- PASO 1: GENERAR EXCEL                                         --}}
        {{-- ============================================================ --}}
        <div class="card card-success">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-excel"></i>
                    Paso 1 — Generar Excel pre-completado
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('certificate-generator.generate') }}"
                      method="POST"
                      id="generatorForm">
                    @csrf

                    <div class="row">

                        {{-- Curso --}}
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="course_id">
                                    <i class="fas fa-graduation-cap text-primary"></i>
                                    Curso <span class="text-danger">*</span>
                                </label>
                                <select name="course_id" id="course_id"
                                        class="form-control select2 @error('course_id') is-invalid @enderror"
                                        required>
                                    <option value="">— Seleccioná un curso —</option>
                                    @foreach($courses as $course)
                                        <option value="{{ $course->id }}"
                                                data-area="{{ $course->area->nombre ?? '' }}"
                                                {{ old('course_id') == $course->id ? 'selected' : '' }}>
                                            {{ $course->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('course_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-success font-weight-bold" id="area-info"></small>
                            </div>
                        </div>

                        {{-- Unidad Académica --}}
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="unidad_academica">
                                    <i class="fas fa-university text-primary"></i>
                                    Unidad Académica <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="unidad_academica" id="unidad_academica"
                                       class="form-control text-uppercase @error('unidad_academica') is-invalid @enderror"
                                       placeholder="Ej: Facultad de Ciencias Agrarias" maxlength="200"
                                       value="{{ old('unidad_academica') }}" required>
                                @error('unidad_academica')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">Siglas de la unidad académica</small>
                            </div>
                        </div>

                        {{-- Área siglas --}}
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="area_siglas">
                                    <i class="fas fa-sitemap text-primary"></i>
                                    Área (siglas) <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="area_siglas" id="area_siglas"
                                       class="form-control @error('area_siglas') is-invalid @enderror"
                                       placeholder="Se completa al seleccionar el curso" maxlength="150"
                                       value="{{ old('area_siglas') }}" required>
                                @error('area_siglas')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted" id="area_siglas_hint">
                                    Se completa automáticamente al seleccionar el curso
                                </small>
                            </div>
                        </div>

                        {{-- Subárea --}}
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="subarea">
                                    <i class="fas fa-code-branch text-primary"></i>
                                    Subárea <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="subarea" id="subarea"
                                       class="form-control text-uppercase @error('subarea') is-invalid @enderror"
                                       placeholder="Ej: Secretaría de Asuntos Académicos" maxlength="150"
                                       value="{{ old('subarea') }}" required>
                                @error('subarea')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">Siglas de la subárea</small>
                            </div>
                        </div>

                        {{-- Año --}}
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ano">
                                    <i class="fas fa-calendar-alt text-primary"></i>
                                    Año <span class="text-danger">*</span>
                                </label>
                                <input type="number" name="ano" id="ano"
                                       class="form-control @error('ano') is-invalid @enderror"
                                       placeholder="{{ date('Y') }}" min="2000" max="2099"
                                       value="{{ old('ano', date('Y')) }}" required>
                                @error('ano')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Tipo Certificado --}}
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tipo_certificado">
                                    <i class="fas fa-certificate text-primary"></i>
                                    Tipo de Certificado <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="tipo_certificado" id="tipo_certificado"
                                    class="form-control text-uppercase @error('tipo_certificado') is-invalid @enderror"
                                    placeholder="Ej: Aprobado, Asistente, Experto..."
                                    value="{{ old('tipo_certificado') }}"
                                    required>

                                <small class="form-text text-muted">
                                    Se tomarán las primeras 3 letras en mayúscula (ej: Experto → EXP)
                                </small>
                                
                                @error('tipo_certificado')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Preview CUV --}}
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-key text-warning"></i>
                                    Preview CUV (ejemplo)
                                </label>
                                <div class="input-group">
                                    <input type="text" id="cuv_preview"
                                           class="form-control bg-light text-muted font-italic"
                                           placeholder="Se genera automáticamente" readonly>
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-warning">
                                            <i class="fas fa-lock text-dark"></i>
                                        </span>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Ejemplo con ID=1, DNI terminado en 123</small>
                            </div>
                        </div>

                    </div>{{-- /row --}}

                    <div id="persons-info" class="alert alert-secondary d-none mt-1">
                        <i class="fas fa-users"></i>
                        <span id="persons-info-text"></span>
                    </div>

                    <hr>

                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg px-5" id="generateBtn">
                            <i class="fas fa-file-excel"></i> Generar Excel
                        </button>
                        <a href="{{ route('certificates.index') }}"
                           class="btn btn-outline-secondary btn-lg px-4 ml-2">
                            Cancelar
                        </a>
                    </div>

                </form>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- PASO 2: SUBIR EXCEL Y EMITIR                                  --}}
        {{-- ============================================================ --}}
        <div class="card card-primary mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-upload"></i>
                    Paso 2 — Subir Excel y emitir certificados
                </h5>
            </div>
            <div class="card-body">

                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    Antes de subir, asegurate de haber completado la columna
                    <strong>nota</strong> (amarilla) si el certificado la requiere.
                </div>

                <form action="{{ route('certificate-generator.import') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      id="uploadForm">
                    @csrf

                    <div class="form-group">
                        <label for="excel_file_upload">
                            <i class="fas fa-file-excel text-success"></i>
                            Seleccionar archivo Excel
                        </label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input"
                                   id="excel_file_upload" name="excel_file"
                                   accept=".xlsx,.xls" required>
                            <label class="custom-file-label" id="fileLabelUpload">
                                Seleccionar archivo...
                            </label>
                        </div>
                        <small class="form-text text-muted">Formatos: .xlsx, .xls | Máximo: 20MB</small>
                    </div>

                    <div class="text-center mt-3">
                        <button type="submit" class="btn btn-primary btn-lg px-5" id="uploadBtn">
                            {{-- PROGRESO EN VIVO --}}
                            <div id="batch-progress-container" class="mt-4 d-none">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Procesando certificados...
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="progress mb-3" style="height: 30px;">
                                            <div
                                                id="batch-progress-bar"
                                                class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                                role="progressbar"
                                                style="width: 0%"
                                            >
                                                0%
                                            </div>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <h4 id="batch-total">0</h4>
                                                <small>Total</small>
                                            </div>
                                            <div class="col-md-3">
                                                <h4 id="batch-processed">0</h4>
                                                <small>Procesados</small>
                                            </div>
                                            <div class="col-md-3">
                                                <h4 id="batch-pending">0</h4>
                                                <small>Pendientes</small>
                                            </div>
                                            <div class="col-md-3">
                                                <h4 id="batch-failed">0</h4>
                                                <small>Errores</small>
                                            </div>
                                        </div>
                                        <div class="alert alert-success mt-3 d-none" id="batch-finished-message">
                                            <i class="fas fa-check-circle"></i>
                                            ¡Proceso completado correctamente!
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <i class="fas fa-paper-plane"></i>
                            Subir Excel y Emitir Certificados
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- PREVIEW DE COLUMNAS --}}
        <div class="card card-secondary mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-table"></i>
                    Vista previa de columnas del Excel
                </h5>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0 text-center small">
                        <thead>
                            <tr>
                                <th class="bg-success text-white">dni</th>
                                <th class="bg-success text-white">curso</th>
                                <th class="bg-warning text-dark">nota</th>
                                <th class="bg-success text-white">unidad_academica</th>
                                <th class="bg-success text-white">area</th>
                                <th class="bg-success text-white">subarea</th>
                                <th class="bg-success text-white">codigo_incremental</th>
                                <th class="bg-success text-white">ano</th>
                                <th class="bg-success text-white">tipo_certificado</th>
                                <th class="bg-success text-white">iniciales</th>
                                <th class="bg-success text-white">3_ultimos_del_dni</th>
                                <th class="bg-success text-white">cuv</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="text-muted">
                                <td>Auto</td><td>Auto</td>
                                <td class="bg-warning text-dark font-weight-bold">← Completar</td>
                                <td>Auto</td><td>Auto</td><td>Auto</td>
                                <td>Auto</td><td>Auto</td><td>Auto</td>
                                <td>Auto</td><td>Auto</td><td>Auto</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-2">
                    <small class="text-muted">
                        <span class="badge badge-success">Verde</span> Pre-completado &nbsp;
                        <span class="badge badge-warning text-dark">Amarillo</span> Única columna editable (opcional)
                    </small>
                </div>
            </div>
        </div>

    </div>{{-- /col-md-8 --}}

    {{-- SIDEBAR --}}
    <div class="col-md-4">

        <div class="card card-info">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-question-circle"></i> ¿Qué genera el sistema?
                </h5>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    Una fila por cada persona del área del curso, con estos campos ya calculados:
                </p>
                <ul class="small pl-3">
                    <li><strong>iniciales</strong> — 1ª letra apellido + 1ª letra nombre</li>
                    <li><strong>3_ultimos_del_dni</strong> — últimos 3 dígitos del DNI</li>
                    <li><strong>codigo_incremental</strong> — ID de la persona en BD</li>
                    <li><strong>cuv</strong> — concatenación de todos los campos</li>
                </ul>
                <hr>
                <p class="small text-muted mb-1"><strong>Fórmula del CUV:</strong></p>
                <code class="small d-block p-2 bg-light rounded">
                    UA + AREA + SUBAREA + ID + AÑO + TIPO + INICIALES + 3ULTIMOS
                </code>
                <p class="small text-muted mt-2 mb-0">
                    Ejemplo: <code>SSALPAB12025APRG J123</code>
                </p>
            </div>
        </div>

        <div class="card card-warning mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle"></i> Importante
                </h5>
            </div>
            <div class="card-body">
                <ul class="small pl-3 mb-0">
                    <li class="mb-1">Generá el Excel en el <strong>Paso 1</strong></li>
                    <li class="mb-1">Completá la columna <strong>nota</strong> si corresponde</li>
                    <li class="mb-1">Subí el archivo en el <strong>Paso 2</strong></li>
                    <li class="mb-1">Si una persona ya tiene certificado de ese curso, se saltea automáticamente</li>
                    <li>Podés eliminar filas del Excel antes de subirlo</li>
                </ul>
            </div>
        </div>

        <div class="card card-secondary mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar"></i> Estadísticas
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border rounded p-2 bg-light">
                            <h4 class="text-primary mb-0">{{ \App\Models\Person::count() }}</h4>
                            <small class="text-muted">Personas</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 bg-light">
                            <h4 class="text-success mb-0">{{ \App\Models\Certificate::count() }}</h4>
                            <small class="text-muted">Certificados</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /col-md-4 --}}

</div>
@stop

@section('css')
<style>
    code {
        background: #f8f9fa;
        padding: 3px 8px;
        border-radius: 4px;
        border: 1px solid #e9ecef;
        font-size: 0.85em;
        color: #e83e8c;
    }
    .text-uppercase { text-transform: uppercase; }
    .table th, .table td { vertical-align: middle; }
    .custom-file-label::after { content: "Examinar"; }

    #area_siglas.autocompletado {
        background-color: #f0fff0;
        border-color: #28a745;
        font-weight: bold;
    }
</style>
@stop

@section('js')
<script>
$(document).ready(function () {
    // =====================================================
    // BATCH ID DESDE SESSION
    // =====================================================
    let batchId = @json(session('batch_id'));
    let pollingInterval = null;

    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    // ── SELECCIONAR CURSO → autocompletar área ────────────────────────────
    $('#course_id').on('change', function () {
        var selected   = $(this).find('option:selected');
        var areaNombre = selected.data('area');
        var courseId   = $(this).val();

        if (courseId && areaNombre) {

            // Autocompletar el campo área siglas con el nombre exacto del área
            $('#area_siglas').val(areaNombre).addClass('autocompletado');
            $('#area_siglas_hint').html(
                '<span class="text-success">' +
                '<i class="fas fa-check-circle"></i> ' +
                'Completado automáticamente con el área del curso' +
                '</span>'
            );

            // Mostrar info del área debajo del select de curso
            $('#area-info').html(
                '<i class="fas fa-sitemap"></i> Área del curso: <strong>' + areaNombre + '</strong>'
            );

            // Obtener cantidad de personas del área via AJAX
            $.get('/certificate-generator/course-info/' + courseId, function (data) {
                $('#persons-info').removeClass('d-none');
                $('#persons-info-text').html(
                    'Se incluirán todas las personas del área <strong>' +
                    data.area_nombre + '</strong> en el Excel generado.'
                );
            });

        } else {
            // Limpiar si no hay curso seleccionado
            $('#area_siglas').val('').removeClass('autocompletado');
            $('#area_siglas_hint').text('Se completa automáticamente al seleccionar el curso');
            $('#area-info').text('');
            $('#persons-info').addClass('d-none');
        }

        updateCuvPreview();
    });

    // Permitir edición manual del área y quitar estilo autocompletado
    $('#area_siglas').on('input', function () {
        $(this).removeClass('autocompletado');
        $('#area_siglas_hint').text('Editado manualmente');
        updateCuvPreview();
    });

    // ── ACTUALIZAR PREVIEW CUV ────────────────────────────────────────────
    function updateCuvPreview() {
        var ua   = $('#unidad_academica').val().toUpperCase();
        var area = $('#area_siglas').val().toUpperCase();
        var sub  = $('#subarea').val().toUpperCase();
        var ano  = $('#ano').val();
        var tipo = $('#tipo_certificado').val();

        if (ua && area && sub && ano && tipo) {
            $('#cuv_preview').val(ua + area + sub + '1' + ano + tipo + 'EJ' + '123');
        } else {
            $('#cuv_preview').val('');
        }
    }

    $('#unidad_academica, #subarea').on('input', function () {
        $(this).val($(this).val().toUpperCase());
        updateCuvPreview();
    });
    
    $('#tipo_certificado').on('input', function () {
        $(this).val($(this).val().toUpperCase());
        updateCuvPreview();
    });

    $('#ano, #tipo_certificado').on('change input', function () {
        updateCuvPreview();
    });

    // ── PASO 1: Generar Excel ─────────────────────────────────────────────
    $('#generatorForm').on('submit', function (e) {
        e.preventDefault();

        if (!$('#course_id').val()) {
            Swal.fire('Error', 'Por favor seleccioná un curso.', 'error');
            return false;
        }

        var courseName = $('#course_id option:selected').text().trim();
        var tipo = $('#tipo_certificado').val().trim();
        var ano        = $('#ano').val();
        var area       = $('#area_siglas').val();

        Swal.fire({
            title: '¿Generar Excel?',
            html: `<div class="text-left">
                <p>Se generará el Excel con los siguientes datos:</p>
                <ul>
                    <li><strong>Curso:</strong> ${courseName}</li>
                    <li><strong>Área:</strong> ${area}</li>
                    <li><strong>Tipo:</strong> ${tipo}</li>
                    <li><strong>Año:</strong> ${ano}</li>
                </ul>
                <div class="alert alert-info small mb-0">
                    <i class="fas fa-info-circle"></i>
                    El archivo se descarga automáticamente. Completá la columna
                    <strong>nota</strong> si corresponde y luego subilo en el <strong>Paso 2</strong>.
                </div></div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-file-excel"></i> Sí, generar',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                $('#generateBtn')
                    .html('<i class="fas fa-spinner fa-spin"></i> Generando...')
                    .prop('disabled', true);
                $('#generatorForm').off('submit').submit();

                setTimeout(function () {
                    $('#generateBtn')
                        .html('<i class="fas fa-file-excel"></i> Generar Excel')
                        .prop('disabled', false);
                }, 5000);
            }
        });
    });

    // ── PASO 2: Label del archivo ─────────────────────────────────────────
    $('#excel_file_upload').on('change', function () {
        var fileName = $(this).val().split('\\').pop();
        var ext      = fileName.substring(fileName.lastIndexOf('.')).toLowerCase();

        if (fileName && ['.xlsx', '.xls'].indexOf(ext) === -1) {
            Swal.fire('Archivo inválido', 'Por favor seleccioná un archivo Excel (.xlsx o .xls)', 'error');
            $(this).val('');
            $('#fileLabelUpload').text('Seleccionar archivo...');
            return;
        }

        $('#fileLabelUpload').html(
            '<i class="fas fa-file-excel text-success mr-1"></i>' + fileName
        );
    });

    // ── PASO 2: Subir y emitir ────────────────────────────────────────────
    $('#uploadForm').on('submit', function (e) {
        if (!$('#excel_file_upload').val()) {
            e.preventDefault();
            Swal.fire('Error', 'Por favor seleccioná un archivo Excel.', 'error');
            return false;
        }

        e.preventDefault();

        Swal.fire({
            title: '¿Emitir certificados?',
            html: `<div class="text-left">
                <p>El sistema va a:</p>
                <ul>
                    <li>Generar un <strong>PDF</strong> por cada persona del Excel</li>
                    <li>Registrar el <strong>CUV</strong> único de cada certificado</li>
                    <li>Enviar el certificado por <strong>email</strong> a cada persona</li>
                </ul>
                <div class="alert alert-warning small mt-2 mb-0">
                    <i class="fas fa-clock"></i>
                    Este proceso puede demorar varios minutos.
                    <strong>No cerrés la pestaña.</strong>
                </div></div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-paper-plane"></i> Sí, emitir',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                $('#uploadBtn')
                    .html('<i class="fas fa-spinner fa-spin"></i> Procesando... (no cerrés esta pestaña)')
                    .prop('disabled', true);
                $('#uploadForm').off('submit').submit();
            }
        });
    });

    @if($errors->any() || session('error'))
        $('#generateBtn')
            .html('<i class="fas fa-file-excel"></i> Generar Excel')
            .prop('disabled', false);
        $('#uploadBtn')
            .html('<i class="fas fa-paper-plane"></i> Subir Excel y Emitir Certificados')
            .prop('disabled', false);
    @endif
    // =====================================================
    // POLLING AJAX DEL BATCH
    // =====================================================

    function startBatchPolling(batchId)
    {
        if (!batchId) {
            return;
        }
        console.log('Iniciando polling batch:', batchId);
        $('#batch-progress-container').removeClass('d-none');
        pollingInterval = setInterval(function () {
            $.ajax({
                url: '/batch-status/' + batchId,
                method: 'GET',
                success: function (response) {
                    if (!response.success) {
                        return;
                    }
                    const data = response.data;
                    let progress  = data.progress ?? 0;
                    let total     = data.total_jobs ?? 0;
                    let processed = data.processed_jobs ?? 0;
                    let pending   = data.pending_jobs ?? 0;
                    let failed    = data.failed_jobs ?? 0;
                    // =====================================
                    // ACTUALIZAR BARRA
                    // =====================================
                    $('#batch-progress-bar')
                        .css('width', progress + '%')
                        .text(progress + '%');
                    // =====================================
                    // ACTUALIZAR STATS
                    // =====================================
                    $('#batch-total').text(total);
                    $('#batch-processed').text(processed);
                    $('#batch-pending').text(pending);
                    $('#batch-failed').text(failed);
                    // =====================================
                    // FINALIZADO
                    // =====================================
                    if (data.finished === true) {
                        clearInterval(pollingInterval);
                        $('#batch-progress-bar')
                            .removeClass('progress-bar-animated')
                            .removeClass('progress-bar-striped');
                        $('#batch-finished-message')
                            .removeClass('d-none');
                        Swal.fire({
                            icon: 'success',
                            title: 'Proceso completado',
                            text: 'Todos los certificados fueron procesados.',
                            timer: 4000,
                            showConfirmButton: false
                        });
                    }
                },
                error: function (xhr) {
                    console.error(xhr);
                }
            });
        }, 2000);
    }
    // =====================================================
    // INICIAR POLLING SI EXISTE BATCH
    // =====================================================

    if (batchId) {
        startBatchPolling(batchId);
    }
});
</script>
@stop