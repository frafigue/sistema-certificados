@extends('adminlte::page')

@section('title', 'Mis Certificados')

@section('content_header')
    <h1>Mis Certificados</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            @if ($certificates->isEmpty())
                <p class="text-muted mb-0">Todavía no tenés certificados emitidos.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Curso</th>
                                <th>Área</th>
                                <th>Fecha</th>
                                <th class="text-right">Certificado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($certificates as $certificate)
                                <tr>
                                    <td>{{ $certificate->course->nombre ?? 'Sin curso' }}</td>
                                    <td>{{ $certificate->course->area->nombre ?? 'Sin área' }}</td>
                                    <td>{{ $certificate->created_at?->format('d/m/Y') }}</td>
                                    <td class="text-right">
                                        @if ($certificate->pdf_path)
                                            <a href="{{ route('certificates.download', $certificate->id) }}"
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-download"></i> Descargar
                                            </a>
                                        @else
                                            <span class="text-muted">Sin PDF</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $certificates->links() }}
            @endif
        </div>
    </div>
@stop
