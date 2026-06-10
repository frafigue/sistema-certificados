@extends('adminlte::page')

@section('title', 'Detalle de Importación #' . $importHistory->id)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="mb-0">
            <i class="fas fa-file-import text-primary"></i>
            Detalle de Importación #{{ $importHistory->id }}
        </h1>
        <a href="{{ route('import-history.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
@stop

@section('content')

<div class="row">

    {{-- INFORMACIÓN GENERAL --}}
    <div class="col-md-4">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle"></i> Información General
                </h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr>
                        <th class="pl-3" width="40%">ID</th>
                        <td>#{{ $importHistory->id }}</td>
                    </tr>
                    <tr>
                        <th class="pl-3">Batch ID</th>
                        <td><small><code>{{ $importHistory->batch_id ?? '—' }}</code></small></td>
                    </tr>
                    <tr>
                        <th class="pl-3">Estado</th>
                        <td>
                            @if($importHistory->status === 'completed')
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> COMPLETADO
                                </span>
                            @elseif($importHistory->status === 'failed')
                                <span class="badge badge-danger">
                                    <i class="fas fa-times-circle"></i> FALLÓ
                                </span>
                            @else
                                <span class="badge badge-warning">
                                    <i class="fas fa-spinner fa-spin"></i> PROCESANDO
                                </span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="pl-3">Archivo</th>
                        <td>{{ $importHistory->file_name ?? 'No registrado' }}</td>
                    </tr>
                    <tr>
                        <th class="pl-3">Usuario</th>
                        <td>{{ $importHistory->user->name ?? 'Sistema' }}</td>
                    </tr>
                    <tr>
                        <th class="pl-3">Inicio</th>
                        <td>{{ optional($importHistory->started_at)->format('d/m/Y H:i:s') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th class="pl-3">Fin</th>
                        <td>
                            @if($importHistory->finished_at)
                                {{ $importHistory->finished_at->format('d/m/Y H:i:s') }}
                            @else
                                <span class="text-warning"><i class="fas fa-spinner fa-spin"></i> En proceso...</span>
                            @endif
                        </td>
                    </tr>
                    @if($importHistory->finished_at && $importHistory->started_at)
                    <tr>
                        <th class="pl-3">Duración</th>
                        <td>
                            {{ $importHistory->started_at->diffForHumans($importHistory->finished_at, true) }}
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        @if($importHistory->general_error)
            <div class="card card-danger card-outline">
                <div class="card-header">
                    <h3 class="card-title text-danger">
                        <i class="fas fa-bug"></i> Error General
                    </h3>
                </div>
                <div class="card-body">
                    <pre class="mb-0 text-danger" style="font-size:12px;white-space:pre-wrap;">{{ $importHistory->general_error }}</pre>
                </div>
            </div>
        @endif
    </div>

    {{-- MÉTRICAS + CERTIFICADOS --}}
    <div class="col-md-8">

        {{-- MÉTRICAS --}}
        <div class="row">
            <div class="col-6 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $importHistory->total_rows }}</h3>
                        <p>Total</p>
                    </div>
                    <div class="icon"><i class="fas fa-list"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>{{ $importHistory->processed_rows }}</h3>
                        <p>Procesados</p>
                    </div>
                    <div class="icon"><i class="fas fa-cogs"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $importHistory->success_rows }}</h3>
                        <p>Correctos</p>
                    </div>
                    <div class="icon"><i class="fas fa-check"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $importHistory->failed_rows }}</h3>
                        <p>Errores</p>
                    </div>
                    <div class="icon"><i class="fas fa-times"></i></div>
                </div>
            </div>
        </div>

        {{-- PROGRESO --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i> Progreso de Importación
                </h3>
            </div>
            <div class="card-body">
                @php $progress = $importHistory->progress(); @endphp
                <div class="progress" style="height:30px;">
                    <div class="progress-bar
                        @if($importHistory->status === 'completed') bg-success
                        @elseif($importHistory->status === 'failed') bg-danger
                        @else bg-warning progress-bar-striped progress-bar-animated
                        @endif"
                        style="width:{{ $progress }}%">
                        {{ $progress }}%
                    </div>
                </div>
                <div class="mt-2 text-muted">
                    <strong>{{ $importHistory->processed_rows }}</strong>
                    de
                    <strong>{{ $importHistory->total_rows }}</strong>
                    filas procesadas.
                </div>
            </div>
        </div>

        {{-- CERTIFICADOS GENERADOS --}}
        @php $certificates = $importHistory->certificates; @endphp

        @if($certificates->count())
            <div class="card card-success card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-certificate"></i>
                        Certificados Generados
                        <span class="badge badge-success ml-2">{{ $certificates->count() }}</span>
                    </h3>
                    <div class="card-tools">
                        <span class="text-muted small">
                            ✅ Enviados:
                            <strong>{{ $certificates->where('email_status', 'enviado')->count() }}</strong>
                            &nbsp;|&nbsp;
                            ❌ Error email:
                            <strong>{{ $certificates->where('email_status', 'error')->count() }}</strong>
                            &nbsp;|&nbsp;
                            ⏳ Pendientes:
                            <strong>{{ $certificates->where('email_status', 'pendiente')->count() }}</strong>
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Persona</th>
                                    <th>DNI</th>
                                    <th>Curso</th>
                                    <th>CUV</th>
                                    <th>Email</th>
                                    <th>Estado Email</th>
                                    <th>PDF</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($certificates as $certificate)
                                    <tr>
                                        <td><strong>#{{ $certificate->id }}</strong></td>
                                        <td>
                                            {{ $certificate->person->apellido ?? '' }},
                                            {{ $certificate->person->nombre ?? '' }}
                                        </td>
                                        <td>{{ $certificate->person->dni ?? '—' }}</td>
                                        <td>{{ $certificate->course->nombre ?? '—' }}</td>
                                        <td>
                                            <code style="font-size:11px;">
                                                {{ $certificate->unique_code }}
                                            </code>
                                        </td>
                                        <td>
                                            @if($certificate->person?->email && !str_ends_with($certificate->person->email, '@tmp.com') && !str_ends_with($certificate->person->email, '@email-temporal.com'))
                                                <small>{{ $certificate->person->email }}</small>
                                            @else
                                                <span class="text-muted">Sin email</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($certificate->email_status === 'enviado')
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> ENVIADO
                                                </span>
                                            @elseif($certificate->email_status === 'error')
                                                <span class="badge badge-danger"
                                                      title="{{ $certificate->email_error }}"
                                                      data-toggle="tooltip">
                                                    <i class="fas fa-times"></i> ERROR
                                                </span>
                                            @else
                                                <span class="badge badge-warning">
                                                    <i class="fas fa-clock"></i> PENDIENTE
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($certificate->pdf_path)
                                                <a href="{{ route('certificates.download', $certificate->id) }}"
                                                   class="btn btn-xs btn-primary"
                                                   target="_blank">
                                                    <i class="fas fa-download"></i> PDF
                                                </a>
                                            @else
                                                <span class="text-muted small">No generado</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="fas fa-hourglass-half fa-2x text-warning mb-2"></i>
                    <p class="text-muted mb-0">
                        Los certificados se están generando en segundo plano.
                        <br>Recargá la página en unos momentos.
                    </p>
                    <a href="{{ route('import-history.show', $importHistory) }}"
                       class="btn btn-sm btn-outline-primary mt-3">
                        <i class="fas fa-sync"></i> Recargar
                    </a>
                </div>
            </div>
        @endif

    </div>

</div>

@stop

@section('css')
<style>
    code {
        background: #f4f6f9;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #ddd;
        color: #d63384;
    }
    .small-box h3 { font-size: 2rem; font-weight: bold; }
    .progress { border-radius: 6px; }
    .progress-bar { font-weight: bold; font-size: 14px; line-height: 30px; }
    .table td, .table th { vertical-align: middle !important; }
    .btn-xs { padding: 2px 8px; font-size: 12px; }
</style>
@stop

@section('js')
<script>
$(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip();

    // Auto-recargar si está procesando
    @if($importHistory->status === 'processing')
        setTimeout(function () {
            window.location.reload();
        }, 5000);
    @endif
});
</script>
@stop