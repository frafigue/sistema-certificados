@extends('adminlte::page')
@section('title', 'Importar Personas')
@section('content_header')
    <h1>
        <i class="fas fa-upload text-success"></i>
        Importar Personas desde Excel
    </h1>
    <div class="mt-2">
        <a href="{{ route('persons.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Listado
        </a>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-md-8">

            {{-- ALERTAS DE SESIÓN --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i>
                    <strong>Éxito!</strong> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Atención!</strong> {{ session('warning') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-times-circle"></i>
                    <strong>Error!</strong> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            {{-- ✅ AVISOS DE PERSONAS YA EXISTENTES (ACTUALIZADAS) --}}
            @if(session('import_warnings') && count(session('import_warnings')) > 0)
                <div class="alert alert-info alert-dismissible fade show">
                    <h5>
                        <i class="fas fa-sync-alt"></i>
                        Personas ya registradas — fueron actualizadas
                    </h5>
                    @foreach(session('import_warnings') as $warning)
                        <div class="mb-1 p-2 border border-info rounded bg-white">
                            <i class="fas fa-user text-info mr-1"></i>
                            <strong>Fila {{ $warning['fila'] }}:</strong> {{ $warning['mensaje'] }}
                        </div>
                    @endforeach
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            {{-- ERRORES DE IMPORTACIÓN --}}
            @if(session('import_errors'))
                @php
                    $errorsList = session('import_errors');
                    $maxToShow = 30;
                @endphp

                @if(is_array($errorsList) && count($errorsList))
                    <div class="alert alert-warning alert-dismissible fade show">
                        <h5>
                            <i class="fas fa-exclamation-triangle"></i>
                            Errores en la importación
                        </h5>

                        @foreach(array_slice($errorsList, 0, $maxToShow) as $error)
                            <div class="error-item mb-2 p-2 border border-warning rounded bg-white">
                                @if(is_array($error))
                                    <strong class="text-danger">
                                        Fila {{ $error['fila'] ?? 'N/A' }}:
                                    </strong>

                                    {{ implode(', ', $error['errores'] ?? []) }}

                                    @if(isset($error['valores']))
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <strong>Valores detectados:</strong>
                                                {{ implode(', ', array_slice($error['valores'], 0, 5)) }}
                                            </small>
                                        </div>
                                    @endif
                                @else
                                    {{ $error }}
                                @endif
                            </div>
                        @endforeach

                        @if(count($errorsList) > $maxToShow)
                            <div class="mt-2 text-muted">
                                Mostrando los primeros {{ $maxToShow }} errores de {{ count($errorsList) }}.
                                Corrige el archivo y vuelve a intentar.
                            </div>
                        @endif

                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                @endif
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <h5><i class="fas fa-times-circle"></i> Errores de validación:</h5>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Card de Instrucciones -->
            <div class="card card-primary">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i>
                        Instrucciones para la Importación
                    </h5>
                </div>

                <div class="card-body">

                    <div class="alert alert-info bg-light border-info">
                        <p class="mb-2">
                            Descargá la plantilla oficial para asegurar compatibilidad total.
                            El orden de las columnas no es obligatorio si utilizás la plantilla.
                        </p>

                        <div class="text-center">
                            <a href="{{ route('persons.download.template') }}"
                                class="btn btn-primary btn-lg">
                                <i class="fas fa-download"></i>
                                Descargar Plantilla Excel
                            </a>
                        </div>
                    </div>

                    <h6 class="text-primary mt-4">
                        <i class="fas fa-table"></i>
                        Formato del Archivo
                    </h6>

                    <p class="text-muted">
                        La primera fila debe contener cabeceras reconocibles.
                        El sistema detecta automáticamente variaciones en los nombres y el orden de las columnas.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>Columna</th>
                                    <th>Requerido</th>
                                    <th>Descripción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>dni</code></td>
                                    <td><span class="badge badge-danger">Obligatorio</span></td>
                                    <td>Documento único</td>
                                </tr>
                                <tr>
                                    <td><code>apellido</code></td>
                                    <td><span class="badge badge-danger">Obligatorio</span></td>
                                    <td>Apellido</td>
                                </tr>
                                <tr>
                                    <td><code>nombre</code></td>
                                    <td><span class="badge badge-danger">Obligatorio</span></td>
                                    <td>Nombre</td>
                                </tr>
                                <tr>
                                    <td><code>titulo</code></td>
                                    <td><span class="badge badge-secondary">Opcional</span></td>
                                    <td>Título profesional</td>
                                </tr>
                                <tr>
                                    <td><code>domicilio</code></td>
                                    <td><span class="badge badge-secondary">Opcional</span></td>
                                    <td>Dirección</td>
                                </tr>
                                <tr>
                                    <td><code>telefono</code></td>
                                    <td><span class="badge badge-secondary">Opcional</span></td>
                                    <td>Teléfono</td>
                                </tr>
                                <tr>
                                    <td><code>email</code></td>
                                    <td><span class="badge badge-danger">Obligatorio</span></td>
                                    <td>Email único</td>
                                </tr>
                                <tr class="table-info">
                                    <td><code>Area Asignada</code></td>
                                    <td><span class="badge badge-danger">Obligatorio</span></td>
                                    <td>Debe existir en el sistema</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <ul class="mb-0 small">
                            <li>El sistema detecta automáticamente DNIs y emails duplicados</li>
                            <li>Las personas existentes se actualizan automáticamente</li>
                            <li>Las filas vacías se ignoran</li>
                            <li>Optimizado para grandes volúmenes de datos (importación por lotes)</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- SUBIDA --}}
            <div class="card card-success mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-upload"></i>
                        Subir Archivo Excel
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('persons.import') }}"
                        method="POST"
                        enctype="multipart/form-data"
                        id="importForm">
                        @csrf

                        <div class="form-group">
                            <label for="excel_file">
                                <i class="fas fa-file-excel text-success"></i>
                                Seleccionar Archivo
                            </label>

                            <div class="custom-file">
                                <input type="file"
                                    class="custom-file-input"
                                    id="excel_file"
                                    name="excel_file"
                                    accept=".xlsx,.xls,.csv"
                                    required>
                                <label class="custom-file-label" id="fileLabel">
                                    Seleccionar archivo...
                                </label>
                            </div>

                            <small class="form-text text-muted">
                                Formatos permitidos: .xlsx, .xls, .csv | Tamaño máximo: 10MB
                            </small>
                        </div>

                        <div class="text-center">
                            <button type="submit"
                                    class="btn btn-success btn-lg px-5"
                                    id="submitBtn">
                                <i class="fas fa-upload"></i>
                                Iniciar Importación
                            </button>

                            <a href="{{ route('persons.index') }}"
                                class="btn btn-outline-secondary btn-lg px-5 ml-2">
                                Cancelar
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>

        <!-- Sidebar de Ayuda -->
        <div class="col-md-4">
            <div class="card card-info">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-question-circle"></i> Ayuda Rápida
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-info">
                            <i class="fas fa-download"></i> Plantilla Incluye:
                        </h6>
                        <ul class="small pl-3">
                            <li>Formato predefinido</li>
                            <li>Validaciones de datos</li>
                            <li>Lista de áreas disponibles</li>
                            <li>Ejemplos prácticos</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-info">
                            <i class="fas fa-sync-alt"></i> Comportamiento:
                        </h6>
                        <ul class="small pl-3">
                            <li>Personas existentes se actualizan</li>
                            <li>Nuevas personas se crean automáticamente</li>
                            <li>Usuarios se generan con DNI como contraseña</li>
                            <li>Múltiples áreas permitidas por persona</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-info">
                            <i class="fas fa-user-shield"></i> Permisos:
                        </h6>
                        <ul class="small pl-3">
                            <li>Administradores solo pueden asignar su área</li>
                            <li>Root puede asignar cualquier área</li>
                            <li>Validación automática de permisos</li>
                        </ul>
                    </div>

                    <div class="text-center mt-4">
                        <div class="bg-light p-3 rounded">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h6 class="mb-1">Tiempo Estimado</h6>
                            <small class="text-muted">100 registros ≈ 30 segundos</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas Rápidas -->
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
                                <small class="text-muted">Personas Totales</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2 bg-light">
                                <h4 class="text-success mb-0">{{ \App\Models\Area::count() }}</h4>
                                <small class="text-muted">Áreas Activas</small>
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
    .badge {
        font-size: 0.75em;
        font-weight: normal;
    }
    code {
        background: #f8f9fa;
        padding: 3px 8px;
        border-radius: 4px;
        border: 1px solid #e9ecef;
        font-size: 0.9em;
        color: #e83e8c;
        font-weight: 500;
    }
    .table-sm th, .table-sm td {
        padding: 0.75rem;
        vertical-align: middle;
    }
    .error-item {
        background-color: #fff3cd;
        border-left: 4px solid #ffc107;
    }
    .custom-file-label::after {
        content: "Examinar";
    }
    .card-header {
        background: linear-gradient(45deg, #f8f9fa, #ffffff);
        border-bottom: 1px solid #dee2e6;
    }
    .bg-light {
        background-color: #f8f9fa !important;
    }
</style>
@stop

@section('js')
<script>
    $(document).ready(function() {
        $('#excel_file').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            var icon = '<i class="fas fa-file-excel text-success mr-2"></i>';
            $('#fileLabel').html(icon + (fileName || 'Seleccionar archivo...'));
            
            var validExtensions = ['.xlsx', '.xls', '.csv'];
            var fileExtension = fileName.substring(fileName.lastIndexOf('.')).toLowerCase();
            if (fileName && validExtensions.indexOf(fileExtension) === -1) {
                Swal.fire('Error', 'Por favor selecciona un archivo Excel válido (.xlsx, .xls, .csv)', 'error');
                $(this).val('');
                $('#fileLabel').html('<i class="fas fa-search"></i> Seleccionar archivo...');
            }
        });

        $('#importForm').on('submit', function(e) {
            var file = $('#excel_file').val();
            if (!file) {
                e.preventDefault();
                Swal.fire('Error', 'Por favor selecciona un archivo Excel', 'error');
                return false;
            }
            
            e.preventDefault();
            Swal.fire({
                title: '¿Confirmar Importación?',
                html: `
                    <div class="text-left">
                        <p>Esta acción importará todas las personas del archivo Excel.</p>
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Esta acción no se puede deshacer.</strong>
                        </div>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-upload"></i> Sí, Importar',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                reverseButtons: true,
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return new Promise((resolve) => {
                        $('#submitBtn').html('<i class="fas fa-spinner fa-spin"></i> Importando...')
                                      .prop('disabled', true)
                                      .addClass('disabled');
                        resolve();
                    });
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#importForm').off('submit').submit();
                } else {
                    $('#submitBtn').html('<i class="fas fa-upload"></i> Iniciar Importación')
                                  .prop('disabled', false)
                                  .removeClass('disabled');
                }
            });
        });

        @if($errors->any() || session('import_errors'))
            $('#submitBtn').html('<i class="fas fa-upload"></i> Iniciar Importación')
                          .prop('disabled', false)
                          .removeClass('disabled');
        @endif

        @if($errors->any() || session('import_errors') || session('error') || session('import_warnings'))
            $('html, body').animate({
                scrollTop: $(".alert").first().offset().top - 100
            }, 500);
        @endif
    });
</script>

@if(session('success'))
<script>
    $(document).ready(function() {
        setTimeout(function() {
            window.location.href = "{{ route('persons.index') }}";
        }, 3000);
    });
</script>
@endif
@stop