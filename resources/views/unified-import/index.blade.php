@extends('adminlte::page')

@section('title', 'Carga e Emisión Masiva')

@section('content_header')
    <h1>
        <i class="fas fa-layer-group text-primary"></i>
        Carga e Emisión Masiva
    </h1>
    <div class="mt-2">
        <a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Inicio
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

        {{-- RESUMEN --}}
        @if(session('import_summary'))
            @php $summary = session('import_summary'); @endphp
            <div class="card card-primary">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Resumen del proceso</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-outline card-primary mb-0">
                                <div class="card-header">
                                    <h6 class="card-title mb-0"><i class="fas fa-users text-primary"></i> Personas — Hoja 1</h6>
                                </div>
                                <div class="card-body py-2">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr><td><span class="badge badge-success">✔</span> Creadas</td><td><strong>{{ $summary['personas']['creadas'] }}</strong></td></tr>
                                        <tr><td><span class="badge badge-warning">↷</span> Ya existían</td><td><strong>{{ $summary['personas']['existentes'] }}</strong></td></tr>
                                        <tr><td><span class="badge badge-danger">✘</span> Errores</td><td><strong>{{ $summary['personas']['errores'] }}</strong></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-outline card-success mb-0">
                                <div class="card-header">
                                    <h6 class="card-title mb-0"><i class="fas fa-certificate text-success"></i> Certificados — Hoja 2</h6>
                                </div>
                                <div class="card-body py-2">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr><td><span class="badge badge-success">✔</span> Generados</td><td><strong>{{ $summary['certificados']['generados'] }}</strong></td></tr>
                                        <tr><td><span class="badge badge-danger">✘</span> Errores</td><td><strong>{{ $summary['certificados']['errores'] }}</strong></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ERRORES --}}
        @if(session('import_errors') && count(session('import_errors')) > 0)
            @php $errorsList = session('import_errors'); $maxToShow = 30; @endphp
            <div class="alert alert-warning alert-dismissible fade show">
                <h5><i class="fas fa-exclamation-triangle"></i> Errores ({{ count($errorsList) }} total)</h5>
                @foreach(array_slice($errorsList, 0, $maxToShow) as $error)
                    <div class="error-item mb-2 p-2 border border-warning rounded bg-white">
                        @if(is_array($error))
                            <strong class="text-danger">[{{ $error['hoja'] ?? 'General' }}] Fila {{ $error['fila'] ?? 'N/A' }}:</strong>
                            {{ implode(', ', $error['errores'] ?? []) }}
                            @if(!empty($error['valores']))
                                <div class="mt-1">
                                    <small class="text-muted"><strong>Valores:</strong> {{ implode(' | ', array_filter((array) $error['valores'])) }}</small>
                                </div>
                            @endif
                        @else
                            {{ $error }}
                        @endif
                    </div>
                @endforeach
                @if(count($errorsList) > $maxToShow)
                    <p class="mb-0 text-muted small mt-2">Mostrando {{ $maxToShow }} de {{ count($errorsList) }} errores.</p>
                @endif
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        {{-- HOJA 1 --}}
        <div class="card card-primary">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-users"></i> Hoja 1 — Personas</h5>
                <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
            </div>
            <div class="card-body">
                <p class="text-muted">Si una persona ya existe, sus datos <strong>no se modifican</strong>.</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="bg-light"><tr><th>Columna</th><th>Requerido</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>dni</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Solo dígitos</td></tr>
                            <tr><td><code>apellido</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Apellido</td></tr>
                            <tr><td><code>nombre</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Nombre</td></tr>
                            <tr><td><code>titulo</code></td><td><span class="badge badge-secondary">Opcional</span></td><td>Título profesional</td></tr>
                            <tr><td><code>domicilio</code></td><td><span class="badge badge-secondary">Opcional</span></td><td>Dirección</td></tr>
                            <tr><td><code>telefono</code></td><td><span class="badge badge-secondary">Opcional</span></td><td>Teléfono</td></tr>
                            <tr><td><code>email</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Email único y válido</td></tr>
                            <tr class="table-info"><td><code>areaasignada</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Nombre exacto del área</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- HOJA 2 --}}
        <div class="card card-success">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-certificate"></i> Hoja 2 — Certificados</h5>
                <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
            </div>
            <div class="card-body">
                <p class="text-muted">Solo necesitás el <strong>DNI</strong> como referencia. No repetir nombre ni apellido.</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="bg-light"><tr><th>Columna</th><th>Requerido</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>dni</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Debe existir en Hoja 1 o en el sistema</td></tr>
                            <tr><td><code>curso</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Nombre exacto del curso</td></tr>
                            <tr><td><code>nota</code></td><td><span class="badge badge-secondary">Opcional</span></td><td>Numérico</td></tr>
                            <tr><td><code>unidad_academica</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Siglas (ej: SS)</td></tr>
                            <tr><td><code>area</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Siglas (ej: SAL)</td></tr>
                            <tr><td><code>subarea</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Siglas (ej: PAB)</td></tr>
                            <tr><td><code>codigo_incremental</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>ID de la persona</td></tr>
                            <tr><td><code>ano</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Año (ej: {{ date('Y') }})</td></tr>
                            <tr>
                                <td><code>tipo_certificado</code></td>
                                <td><span class="badge badge-danger">Obligatorio</span></td>
                                <td><span class="badge badge-info">APR</span> Aprobado &nbsp; <span class="badge badge-info">ASI</span> Asistente &nbsp; <span class="badge badge-info">CAP</span> Capacitador</td>
                            </tr>
                            <tr><td><code>iniciales</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Iniciales apellido+nombre (ej: GJ)</td></tr>
                            <tr><td><code>3_ultimos_del_dni</code></td><td><span class="badge badge-danger">Obligatorio</span></td><td>Últimos 3 dígitos del DNI</td></tr>
                            <tr class="table-warning">
                                <td><code>cuv</code></td>
                                <td><span class="badge badge-secondary">Opcional</span></td>
                                <td>Dejar <strong>vacío</strong> para generación automática.<br><small class="text-muted">= unidad_academica + area + subarea + codigo_incremental + ano + tipo_certificado + iniciales + 3_ultimos_del_dni</small></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-warning mt-2 mb-0">
                    <i class="fas fa-info-circle"></i> Si el CUV ya existe, esa fila se saltea. El proceso continúa con las demás.
                </div>
            </div>
        </div>

        {{-- FORMULARIO --}}
        <div class="card card-info mt-2">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-file-upload"></i> Subir Archivo Excel Unificado</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info bg-light border-info mb-4">
                    <p class="mb-2">Descargá la plantilla con las dos hojas pre-configuradas.</p>
                    <div class="text-center">
                        <a href="{{ route('unified-import.template') }}" class="btn btn-primary">
                            <i class="fas fa-download"></i> Descargar Plantilla Unificada
                        </a>
                    </div>
                </div>

                <form action="{{ route('unified-import.import') }}" method="POST"
                      enctype="multipart/form-data" id="unifiedImportForm">
                    @csrf
                    <div class="form-group">
                        <label for="excel_file"><i class="fas fa-file-excel text-success"></i> Seleccionar Archivo</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="excel_file"
                                   name="excel_file" accept=".xlsx,.xls" required>
                            <label class="custom-file-label" id="fileLabel">Seleccionar archivo...</label>
                        </div>
                        <small class="form-text text-muted">Formatos: .xlsx, .xls | Máximo: 20MB</small>
                    </div>

                    @error('excel_file')
                        <div class="alert alert-danger py-2">{{ $message }}</div>
                    @enderror

                    <div class="text-center mt-3">
                        <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn">
                            <i class="fas fa-upload"></i> Procesar Archivo
                        </button>
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-lg px-5 ml-2">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

    </div>{{-- /col-md-8 --}}

    {{-- SIDEBAR --}}
    <div class="col-md-4">
        <div class="card card-info">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-question-circle"></i> ¿Cómo funciona?</h5>
            </div>
            <div class="card-body">
                <h6 class="text-info"><i class="fas fa-list-ol"></i> Flujo del proceso</h6>
                <ol class="small pl-3 mb-3">
                    <li class="mb-1">Personas de <strong>Hoja 1</strong> se importan</li>
                    <li class="mb-1">Se crean usuarios (contraseña = DNI)</li>
                    <li class="mb-1">Certificados de <strong>Hoja 2</strong> se generan</li>
                    <li class="mb-1">Se envían emails con PDF adjunto</li>
                </ol>
                <h6 class="text-info"><i class="fas fa-shield-alt"></i> Protecciones</h6>
                <ul class="small pl-3 mb-3">
                    <li>Personas duplicadas → salteadas sin error</li>
                    <li>CUV duplicados → error en esa fila</li>
                    <li>Email fallido → certificado igual se genera</li>
                    <li>Un error no detiene el proceso completo</li>
                </ul>
                <h6 class="text-info"><i class="fas fa-user-shield"></i> Permisos</h6>
                <ul class="small pl-3 mb-3">
                    <li>Administradores: solo su área</li>
                    <li>Root: todas las áreas</li>
                </ul>
                <div class="text-center mt-3">
                    <div class="bg-light p-3 rounded">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h6 class="mb-1">Tiempo Estimado</h6>
                        <small class="text-muted">Puede demorar varios minutos. <strong>No cerrés la pestaña.</strong></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-secondary mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Estadísticas actuales</h5>
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
    </div>

</div>
@stop

@section('css')
<style>
    code { background:#f8f9fa; padding:3px 8px; border-radius:4px; border:1px solid #e9ecef; font-size:.9em; color:#e83e8c; font-weight:500; }
    .table-sm th, .table-sm td { padding:.6rem; vertical-align:middle; }
    .error-item { background-color:#fff3cd; border-left:4px solid #ffc107; }
    .custom-file-label::after { content:"Examinar"; }
</style>
@stop

@section('js')
<script>
$(document).ready(function () {
    $('#excel_file').on('change', function () {
        var fileName = $(this).val().split('\\').pop();
        var ext = fileName.substring(fileName.lastIndexOf('.')).toLowerCase();
        if (fileName && ['.xlsx','.xls'].indexOf(ext) === -1) {
            Swal.fire('Archivo inválido','Por favor seleccioná un archivo Excel (.xlsx o .xls)','error');
            $(this).val(''); $('#fileLabel').text('Seleccionar archivo...'); return;
        }
        $('#fileLabel').html('<i class="fas fa-file-excel text-success mr-1"></i>' + fileName);
    });

    $('#unifiedImportForm').on('submit', function (e) {
        if (!$('#excel_file').val()) {
            e.preventDefault();
            Swal.fire('Error','Por favor seleccioná un archivo Excel.','error'); return false;
        }
        e.preventDefault();
        Swal.fire({
            title: '¿Confirmar procesamiento?',
            html: `<div class="text-left"><p>El sistema realizará:</p><ul>
                <li>Importar personas de la <strong>Hoja 1</strong></li>
                <li>Crear usuarios (contraseña = DNI)</li>
                <li>Generar certificados de la <strong>Hoja 2</strong></li>
                <li>Enviar emails con PDF adjunto</li></ul>
                <div class="alert alert-warning small mt-2 mb-0">
                <i class="fas fa-clock"></i> Puede demorar varios minutos. <strong>No cerrés la pestaña.</strong>
                </div></div>`,
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-upload"></i> Sí, procesar',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar', reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                $('#submitBtn').html('<i class="fas fa-spinner fa-spin"></i> Procesando... (no cerrés esta pestaña)').prop('disabled', true);
                $('#unifiedImportForm').off('submit').submit();
            }
        });
    });

    @if($errors->any() || session('import_errors') || session('error'))
        $('#submitBtn').html('<i class="fas fa-upload"></i> Procesar Archivo').prop('disabled', false);
        $('html, body').animate({ scrollTop: $(".alert").first().offset().top - 100 }, 500);
    @endif
});
</script>
@stop