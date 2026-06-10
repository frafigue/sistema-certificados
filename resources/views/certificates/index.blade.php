@extends('adminlte::page')

@section('title', 'Gestión de Certificados')

@section('content_header')
    <h1>Listado de Certificados</h1>
@stop

@section('content')

@can('is-admin-or-root')
<div class="card card-primary collapsed-card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-search"></i> Filtros de Búsqueda
        </h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>
    <div class="card-body">

        @if(session('batch_id'))
        <div class="card card-info mb-3" id="batch-progress-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cogs"></i>
                    Procesando certificados masivos
                </h3>
            </div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 30px;">
                    <div
                        id="batch-progress-bar"
                        class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                        role="progressbar"
                        style="width: 0%">
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
            </div>
        </div>
        @endif

        <form method="GET" action="{{ route('certificates.index') }}">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>DNI Persona</label>
                        <input type="text" name="search_dni" class="form-control"
                            placeholder="Buscar por DNI..."
                            value="{{ request('search_dni') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Curso</label>
                        <input type="text" name="search_course" class="form-control"
                            placeholder="Buscar por nombre del curso..."
                            value="{{ request('search_course') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Área</label>
                        <input type="text" name="search_area" class="form-control"
                            placeholder="Buscar por área..."
                            value="{{ request('search_area') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="search_tipo" class="form-control">
                            <option value="">-- Todos --</option>
                            @foreach($tiposDisponibles as $tipo)
                                <option value="{{ $tipo }}" {{ request('search_tipo') == $tipo ? 'selected' : '' }}>
                                    {{ $tipo }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="{{ route('certificates.index') }}" class="btn btn-secondary ml-2">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

<div class="card">

    @can('is-admin-or-root')
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="{{ route('certificates.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Generación Individual
                </a>
                <a href="{{ route('certificate-generator.form') }}" class="btn btn-success ml-2">
                    <i class="fas fa-layer-group"></i> Generación Masiva
                </a>
                <form action="{{ route('certificates.sendPending') }}" method="POST" class="d-inline ml-2">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-paper-plane"></i> Enviar Pendientes
                    </button>
                </form>
            </div>
            <small class="text-muted">
                Total: <strong>{{ $certificates->total() }}</strong> certificados
            </small>
        </div>
    </div>
    @endcan

    <div class="card-body">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        @if(session('import_errors'))
            <div class="alert alert-danger alert-dismissible fade show">
                <h6><i class="fas fa-times-circle"></i> Errores durante la importación:</h6>
                <ul class="mb-0">
                    @foreach(session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>DNI Persona</th>
                        <th>Nombre Persona</th>
                        <th>Curso</th>
                        <th>Area</th>
                        <th>Tipo</th>
                        <th>CUV</th>
                        <th>Origen</th>
                        <th>Email</th>
                        <th>Enviado</th>
                        <th>Error</th>
                        <th style="width: 160px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($certificates as $certificate)
                    <tr>
                        <td>{{ $certificate->id }}</td>
                        <td>{{ $certificate->person->dni ?? 'N/A' }}</td>
                        <td>{{ $certificate->person->apellido ?? '' }}, {{ $certificate->person->nombre ?? '' }}</td>
                        <td>{{ $certificate->course->nombre ?? 'N/A' }}</td>
                        <td>{{ $certificate->area_excel ?? 'N/A' }}</td>

                        <td>
                            @php
                                $badgeColors = [
                                    'Aprobado'    => 'success',
                                    'Capacitador' => 'primary',
                                    'Asistente'   => 'secondary',
                                ];
                                $color = $badgeColors[$certificate->condition] ?? 'dark';
                            @endphp
                            <span class="badge badge-{{ $color }}">
                                {{ $certificate->condition }}
                            </span>
                        </td>

                        <td><code>{{ $certificate->unique_code }}</code></td>

                        {{-- ORIGEN --}}
                        <td>
                            @if($certificate->import_history_id)
                                <a href="{{ route('import-history.show', $certificate->import_history_id) }}"
                                   class="badge badge-info"
                                   style="color:#fff;"
                                   title="Ver Importación #{{ $certificate->import_history_id }}"
                                   data-toggle="tooltip">
                                    <i class="fas fa-layer-group"></i> Masivo
                                </a>
                            @else
                                <span class="badge badge-secondary">
                                    <i class="fas fa-user"></i> Individual
                                </span>
                            @endif
                        </td>

                        <td>
                            @if($certificate->email_status == 'pendiente')
                                <span class="badge badge-warning">Pendiente</span>
                            @elseif($certificate->email_status == 'enviado')
                                <span class="badge badge-success">Enviado</span>
                            @elseif($certificate->email_status == 'error')
                                <span class="badge badge-danger">Error</span>
                            @else
                                <span class="badge badge-secondary">—</span>
                            @endif
                        </td>

                        <td>
                            {{ $certificate->email_sent_at
                                ? \Carbon\Carbon::parse($certificate->email_sent_at)->format('d/m/Y H:i')
                                : '—' }}
                        </td>

                        <td>
                            @if($certificate->email_error)
                                <span title="{{ $certificate->email_error }}"
                                      data-toggle="tooltip">
                                    ⚠
                                </span>
                            @else
                                —
                            @endif
                        </td>

                        <td class="d-flex align-items-center">
                            <a href="{{ route('certificates.download', $certificate->id) }}"
                               target="_blank"
                               class="btn btn-sm btn-info mr-1" title="Ver PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <a href="{{ asset('storage/' . $certificate->qr_path) }}"
                               target="_blank"
                               class="btn btn-sm btn-secondary mr-1" title="Ver QR">
                                <i class="fas fa-qrcode"></i>
                            </a>
                            @can('is-admin-or-root')
                            <a href="{{ route('certificates.edit', $certificate->id) }}"
                               class="btn btn-sm btn-warning mr-1" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('certificates.destroy', $certificate->id) }}"
                                  method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="btn btn-sm btn-danger btn-delete"
                                        title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="text-center text-muted">
                            <i class="fas fa-certificate fa-2x mb-2 d-block"></i>
                            No hay certificados que coincidan con la búsqueda.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    <div class="card-footer clearfix">
        {{ $certificates->appends(request()->query())->links() }}
    </div>

</div>

@stop

@section('js')
<script>
$(document).ready(function () {

    // Tooltips
    $('[data-toggle="tooltip"]').tooltip();

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR CERTIFICADO
    |--------------------------------------------------------------------------
    */
    $('.card-body').on('click', '.btn-delete', function (e) {
        e.preventDefault();
        var form = $(this).closest('form');

        Swal.fire({
            title: '¿Estás seguro?',
            text: "El certificado PDF y su código QR serán eliminados permanentemente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Confirmación final',
                    html: 'Para confirmar, escribí <strong>ELIMINAR</strong>.',
                    input: 'text',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                    inputValidator: (value) => {
                        if (value !== 'ELIMINAR') {
                            return 'La palabra no coincide. Eliminación cancelada.';
                        }
                    }
                }).then((result2) => {
                    if (result2.isConfirmed) {
                        form.submit();
                    }
                });
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | BATCH EN VIVO
    |--------------------------------------------------------------------------
    */
    @if(session('batch_id'))
        let batchId = "{{ session('batch_id') }}";
        console.log('🔥 Batch ID:', batchId);

        let interval = setInterval(function () {
            $.ajax({
                url: "/batch-status/" + batchId,
                type: "GET",
                success: function (response) {
                    console.log(response);

                    let data      = response.data ?? response;
                    let progress  = data.progress      ?? 0;
                    let total     = data.total_jobs    ?? 0;
                    let processed = data.processed_jobs ?? 0;
                    let pending   = data.pending_jobs   ?? 0;
                    let failed    = data.failed_jobs    ?? 0;
                    let finished  = data.finished       ?? false;

                    $('#batch-progress-bar')
                        .css('width', progress + '%')
                        .text(progress + '%');

                    $('#batch-total').text(total);
                    $('#batch-processed').text(processed);
                    $('#batch-pending').text(pending);
                    $('#batch-failed').text(failed);

                    if (finished) {
                        clearInterval(interval);
                        $('#batch-progress-bar')
                            .removeClass('progress-bar-animated')
                            .removeClass('bg-success')
                            .addClass(failed > 0 ? 'bg-warning' : 'bg-primary');

                        Swal.fire({
                            icon: failed > 0 ? 'warning' : 'success',
                            title: failed > 0
                                ? 'Proceso finalizado con errores'
                                : 'Proceso completado',
                            html:
                                '<b>Total:</b> '      + total     + '<br>' +
                                '<b>Procesados:</b> ' + processed + '<br>' +
                                '<b>Errores:</b> '    + failed,
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            location.reload();
                        });
                    }
                },
                error: function (xhr) {
                    console.error(xhr);
                }
            });
        }, 2000);
    @endif

});
</script>
@stop