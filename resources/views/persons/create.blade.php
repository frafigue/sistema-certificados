@extends('adminlte::page')

@section('title', 'Añadir Persona')

@section('content_header')
<h1>Añadir Nueva Persona</h1>
@stop

@section('content')
<div class="card">
    <div class="card-body">

        {{-- ✅ Mensaje de confirmación para mover persona de área --}}
        @if(session('confirmar_mover'))
            @php $persona = session('persona_existente'); @endphp
            <div class="alert alert-warning alert-dismissible">
                <h5><i class="fas fa-exclamation-triangle"></i> Persona encontrada en otra área</h5>
                <p>
                    La persona <strong>{{ $persona['apellido'] }}, {{ $persona['nombre'] }}</strong>
                    (DNI: <strong>{{ $persona['dni'] }}</strong>) ya está registrada en
                    <strong>"{{ $persona['area_actual'] }}"</strong>.
                </p>
                <p class="mb-0">¿Querés moverla al área que seleccionaste?</p>
            </div>
        @endif

        {{-- Errores de validación --}}
        @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('persons.store') }}" method="POST" id="personForm">
            @csrf

            {{-- ✅ Campo oculto para confirmar el movimiento de área --}}
            @if(session('confirmar_mover'))
                <input type="hidden" name="confirmar_mover" value="1">
            @endif

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>DNI</label>
                        <input type="text" name="dni" class="form-control"
                               value="{{ old('dni') }}" required
                               {{ session('confirmar_mover') ? 'readonly' : '' }}>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" name="apellido" class="form-control"
                               value="{{ old('apellido') }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" class="form-control"
                               value="{{ old('nombre') }}" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Título</label>
                        <input type="text" name="titulo" class="form-control"
                               value="{{ old('titulo') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Domicilio</label>
                        <input type="text" name="domicilio" class="form-control"
                               value="{{ old('domicilio') }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" class="form-control"
                               value="{{ old('telefono') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email') }}" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Área Asignada</label>
                @if(auth()->user()->role?->name === 'Administrador')
                    <input type="hidden" name="area_id" value="{{ auth()->user()->area_id }}">
                    <input type="text" class="form-control"
                           value="{{ auth()->user()->area->nombre ?? 'Sin Área' }}" disabled>
                @else
                    <select name="area_id" class="form-control" required>
                        <option value="">Seleccione un Área</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}"
                                {{ old('area_id') == $area->id ? 'selected' : '' }}>
                                {{ $area->nombre }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            @if(session('confirmar_mover'))
                {{-- ✅ Botones cuando hay confirmación pendiente --}}
                <button type="submit" class="btn btn-warning" id="btnConfirmar">
                    <i class="fas fa-exchange-alt"></i> Sí, mover al área
                </button>
                <a href="{{ route('persons.create') }}" class="btn btn-secondary ml-2">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            @else
                {{-- Botones normales --}}
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <a href="{{ route('persons.index') }}" class="btn btn-secondary ml-2">
                    Cancelar
                </a>
            @endif

        </form>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function () {
    // ✅ Confirmación SweetAlert al mover persona de área
    @if(session('confirmar_mover'))
    @php $persona = session('persona_existente'); @endphp
    $('#btnConfirmar').on('click', function (e) {
        e.preventDefault();
        var form = $('#personForm');
        Swal.fire({
            title: '¿Mover persona de área?',
            html: 'La persona <strong>{{ $persona["apellido"] }}, {{ $persona["nombre"] }}</strong> será movida desde <strong>"{{ $persona["area_actual"] }}"</strong> al área seleccionada.',
            icon: 'warning',
            showCancelButton:   true,
            confirmButtonColor: '#e0a800',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  'Sí, mover',
            cancelButtonText:   'Cancelar',
            reverseButtons:     true,
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });
    @endif
});
</script>
@stop