@extends('adminlte::page')
@section('title', 'Confirmar Importación')
@section('content_header')
    <h1>
        <i class="fas fa-clipboard-check text-primary"></i>
        Confirmar Importación de Personas
    </h1>
    <div class="mt-2">
        <a href="{{ route('persons.import.form') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver y subir otro archivo
        </a>
    </div>
@stop

@section('content')
@php
    $newCount       = $analysis['new_count'] ?? 0;
    $withChanges    = $analysis['with_changes'] ?? [];
    $withoutChanges = $analysis['without_changes'] ?? [];
    $errors         = $analysis['errors'] ?? [];
    $totalRows      = $analysis['total_rows'] ?? 0;
@endphp

<div class="row">
    <div class="col-md-9">

        {{-- RESUMEN GENERAL --}}
        <div class="card card-primary">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar"></i> Resumen del análisis
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border rounded p-3 bg-light">
                            <h3 class="text-success mb-0">{{ $newCount }}</h3>
                            <small class="text-muted">Personas nuevas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 bg-light">
                            <h3 class="text-warning mb-0">{{ count($withChanges) }}</h3>
                            <small class="text-muted">Con cambios</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 bg-light">
                            <h3 class="text-info mb-0">{{ count($withoutChanges) }}</h3>
                            <small class="text-muted">Sin cambios</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 bg-light">
                            <h3 class="text-danger mb-0">{{ count($errors) }}</h3>
                            <small class="text-muted">Errores</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('persons.import.confirm') }}" method="POST" id="confirmForm">
            @csrf

            {{-- PERSONAS NUEVAS --}}
            @if($newCount > 0)
                <div class="card card-success mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-plus text-white"></i>
                            {{ $newCount }} persona(s) nuevas — se importarán automáticamente
                        </h5>
                    </div>
                    <div class="card-body py-2">
                        <p class="mb-0 text-muted small">
                            <i class="fas fa-check-circle text-success"></i>
                            Estas personas no existen en el sistema y serán creadas al confirmar.
                        </p>
                    </div>
                </div>
            @endif

            {{-- PERSONAS CON CAMBIOS --}}
            @if(count($withChanges) > 0)
                <div class="card card-warning mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-edit"></i>
                            {{ count($withChanges) }} persona(s) ya existen con datos diferentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <i class="fas fa-info-circle"></i>
                            Marcá las personas que querés actualizar con los nuevos datos del Excel.
                            Las que no marques serán ignoradas.
                        </p>

                        <div class="mb-2">
                            <button type="button" class="btn btn-sm btn-outline-warning" id="selectAll">
                                <i class="fas fa-check-square"></i> Seleccionar todas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-1" id="deselectAll">
                                <i class="fas fa-square"></i> Deseleccionar todas
                            </button>
                        </div>

                        @foreach($withChanges as $item)
                            <div class="card mb-2 border-warning">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex align-items-start">
                                        <div class="mr-3 mt-1">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox"
                                                    class="custom-control-input update-checkbox"
                                                    id="update_{{ $item['dni'] }}"
                                                    name="update_dns[]"
                                                    value="{{ $item['dni'] }}">
                                                <label class="custom-control-label" for="update_{{ $item['dni'] }}">
                                                    <strong>Actualizar</strong>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <strong>
                                                <i class="fas fa-user text-warning mr-1"></i>
                                                Fila {{ $item['fila'] }}: DNI {{ $item['dni'] }} — {{ $item['apellido'] }}, {{ $item['nombre'] }}
                                            </strong>
                                            <div class="mt-2">
                                                <table class="table table-sm table-bordered mb-0" style="font-size:0.85em;">
                                                    <thead class="bg-light">
                                                        <tr>
                                                            <th>Campo</th>
                                                            <th class="text-danger">Valor actual en sistema</th>
                                                            <th class="text-success">Valor nuevo en Excel</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($item['cambios'] as $cambio)
                                                            <tr>
                                                                <td><strong>{{ $cambio['campo'] }}</strong></td>
                                                                <td class="text-danger">{{ $cambio['actual'] }}</td>
                                                                <td class="text-success">{{ $cambio['nuevo'] }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- PERSONAS SIN CAMBIOS --}}
            @if(count($withoutChanges) > 0)
                <div class="card card-info mt-3">
                    <div class="card-header collapsed-card">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle"></i>
                            {{ count($withoutChanges) }} persona(s) ya existen sin cambios — serán ignoradas
                        </h5>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body" style="display:none;">
                        @foreach($withoutChanges as $item)
                            <div class="mb-1 p-2 border border-info rounded bg-white">
                                <i class="fas fa-user text-info mr-1"></i>
                                <strong>Fila {{ $item['fila'] }}:</strong> {{ $item['mensaje'] }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ERRORES --}}
            @if(count($errors) > 0)
                <div class="card card-danger mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-times-circle"></i>
                            {{ count($errors) }} error(es) encontrados — estas filas serán ignoradas
                        </h5>
                    </div>
                    <div class="card-body">
                        @foreach($errors as $error)
                            <div class="mb-1 p-2 border border-danger rounded bg-white">
                                <strong class="text-danger">Fila {{ $error['fila'] ?? 'N/A' }}:</strong>
                                {{ implode(', ', $error['errores'] ?? []) }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- BOTONES DE ACCIÓN --}}
            <div class="card mt-3">
                <div class="card-body text-center">
                    @if($newCount > 0 || count($withChanges) > 0)
                        <button type="submit" class="btn btn-success btn-lg px-5" id="confirmBtn">
                            <i class="fas fa-check-circle"></i> Confirmar Importación
                        </button>
                    @else
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle"></i>
                            No hay personas nuevas ni cambios para importar.
                        </div>
                    @endif
                    <a href="{{ route('persons.import.form') }}" class="btn btn-outline-secondary btn-lg px-4 ml-2">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </div>

        </form>

    </div>

    {{-- SIDEBAR --}}
    <div class="col-md-3">
        <div class="card card-info">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-question-circle"></i> ¿Cómo funciona?
                </h5>
            </div>
            <div class="card-body small">
                <p><span class="badge badge-success">Verde</span> Personas nuevas que se crearán.</p>
                <p><span class="badge badge-warning text-dark">Amarillo</span> Ya existen pero tienen datos distintos. Podés elegir cuáles actualizar.</p>
                <p><span class="badge badge-info">Azul</span> Ya existen con los mismos datos. Se ignorarán.</p>
                <p><span class="badge badge-danger">Rojo</span> Errores que impiden procesar esa fila.</p>
            </div>
        </div>
    </div>

</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    $('#selectAll').on('click', function() {
        $('.update-checkbox').prop('checked', true);
    });
    $('#deselectAll').on('click', function() {
        $('.update-checkbox').prop('checked', false);
    });

    $('#confirmForm').on('submit', function(e) {
        e.preventDefault();
        var checked = $('.update-checkbox:checked').length;
        var newCount = {{ $newCount }};
        var msg = newCount + ' persona(s) nuevas serán creadas.';
        if (checked > 0) msg += '<br>' + checked + ' persona(s) serán actualizadas.';

        Swal.fire({
            title: '¿Confirmar importación?',
            html: msg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Sí, confirmar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                $('#confirmBtn').html('<i class="fas fa-spinner fa-spin"></i> Procesando...').prop('disabled', true);
                $('#confirmForm').off('submit').submit();
            }
        });
    });
});
</script>
@stop