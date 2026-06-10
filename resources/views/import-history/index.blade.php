@extends('adminlte::page')

@section('title', 'Historial de Importaciones')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">
                <i class="fas fa-history text-primary"></i>
                Historial de Importaciones
            </h1>
            <small class="text-muted">Monitor completo de procesos masivos</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('certificate-generator.form') }}" class="btn btn-success">
                <i class="fas fa-upload"></i> Nueva Importación
            </a>
        </div>
    </div>
@stop

@section('content')

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if($imports->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <div style="font-size: 70px;">📭</div>
                <h3 class="mt-3">No hay importaciones registradas</h3>
                <p class="text-muted mb-4">Cuando subas un Excel aparecerá aquí el historial completo.</p>
                <a href="{{ route('certificate-generator.form') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-file-excel"></i> Ir a Generación Masiva
                </a>
            </div>
        </div>
    @else

        {{-- RESUMEN SUPERIOR --}}
        <div class="row">
            <div class="col-6 col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $totals['total'] }}</h3>
                        <p>Total Importaciones</p>
                    </div>
                    <div class="icon"><i class="fas fa-file-import"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>{{ $totals['completed'] }}</h3>
                        <p>Completadas</p>
                    </div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>{{ $totals['processing'] }}</h3>
                        <p>Procesando</p>
                    </div>
                    <div class="icon"><i class="fas fa-spinner"></i></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>{{ $totals['failed'] }}</h3>
                        <p>Fallidas</p>
                    </div>
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                </div>
            </div>
        </div>

        {{-- TABLA --}}
        <div class="card shadow">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title">
                        <i class="fas fa-server text-primary"></i>
                        Monitor de procesos masivos
                    </h3>
                    <small class="text-muted">Última actualización: {{ now()->format('d/m/Y H:i:s') }}</small>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="60">#</th>
                                <th width="240">Batch ID</th>
                                <th width="130">Estado</th>
                                <th width="220">Progreso</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Procesados</th>
                                <th class="text-center text-success">Correctos</th>
                                <th class="text-center text-danger">Errores</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th width="100">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($imports as $import)
                                @php $progress = $import->progress(); @endphp
                                <tr class="clickable-row" data-href="{{ route('import-history.show', $import) }}">

                                    <td><strong>#{{ $import->id }}</strong></td>

                                    <td>
                                        <code style="font-size:11px;">
                                            {{ Str::limit($import->batch_id, 30) }}
                                        </code>
                                    </td>

                                    <td>
                                        @if($import->status === 'completed')
                                            <span class="badge badge-success px-2 py-1">
                                                <i class="fas fa-check-circle"></i> COMPLETADO
                                            </span>
                                        @elseif($import->status === 'failed')
                                            <span class="badge badge-danger px-2 py-1">
                                                <i class="fas fa-times-circle"></i> FALLÓ
                                            </span>
                                        @else
                                            <span class="badge badge-warning px-2 py-1">
                                                <i class="fas fa-spinner fa-spin"></i> PROCESANDO
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="progress" style="height:18px;">
                                            <div class="progress-bar
                                                @if($import->status === 'completed') bg-success
                                                @elseif($import->status === 'failed') bg-danger
                                                @else bg-primary progress-bar-striped progress-bar-animated
                                                @endif"
                                                style="width:{{ $progress }}%">
                                                {{ $progress }}%
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-center"><strong>{{ $import->total_rows }}</strong></td>
                                    <td class="text-center">{{ $import->processed_rows }}</td>
                                    <td class="text-center text-success font-weight-bold">{{ $import->success_rows }}</td>
                                    <td class="text-center text-danger font-weight-bold">{{ $import->failed_rows }}</td>

                                    <td>
                                        @if($import->started_at)
                                            <small>
                                                <i class="far fa-clock text-primary"></i>
                                                {{ $import->started_at->format('d/m/Y H:i') }}
                                            </small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if($import->finished_at)
                                            <small>
                                                <i class="fas fa-flag-checkered text-success"></i>
                                                {{ $import->finished_at->format('d/m/Y H:i') }}
                                            </small>
                                        @else
                                            <span class="text-warning font-weight-bold">
                                                <i class="fas fa-spinner fa-spin"></i> En proceso...
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <a href="{{ route('import-history.show', $import) }}"
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer clearfix">
                <div class="float-right">
                    {{ $imports->links() }}
                </div>
            </div>
        </div>

    @endif

@stop

@section('css')
<style>
    code {
        background: #f4f6f9;
        padding: 3px 7px;
        border-radius: 4px;
        border: 1px solid #ddd;
        color: #d63384;
    }
    .progress { border-radius: 20px; overflow: hidden; }
    .progress-bar { font-size: 11px; font-weight: bold; }
    .table td, .table th { vertical-align: middle !important; }
    .clickable-row { cursor: pointer; transition: all .15s ease; }
    .clickable-row:hover { background: #f8fbff !important; }
</style>
@stop

@section('js')
<script>
$(document).ready(function () {
    $('.clickable-row').on('click', function (e) {
        if ($(e.target).closest('a, button').length) return;
        window.location = $(this).data('href');
    });
});
</script>
@stop