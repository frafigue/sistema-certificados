@extends('adminlte::page')
@section('title', 'Emitir Nuevo Certificado')
@section('content_header')
<h1>Emitir Nuevo Certificado</h1>
@stop

@section('css')
<style>
    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
        padding: 0.375rem 0.75rem !important;
        font-size: 1rem !important;
        line-height: 1.5 !important;
        background-color: #fff !important;
    }
    .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-left: 0 !important;
        color: #495057 !important;
    }
    .select2-container .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px) !important;
        right: 8px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #6c757d !important;
    }
    .select2-dropdown {
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
        font-size: 1rem !important;
        z-index: 9999 !important;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #007bff !important;
    }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
        padding: 0.375rem 0.75rem !important;
        font-size: 0.9rem !important;
    }
    .select2-container { width: 100% !important; }
    .select2-container--default.select2-container--disabled .select2-selection--single {
        background-color: #e9ecef !important;
        cursor: not-allowed !important;
    }
    .select2-results__option.select2-results__message {
        color: #6c757d;
        font-style: italic;
        font-size: 0.875rem;
    }

    /* Badge de sigla generada */
    #condition_code_preview {
        display: inline-block;
        min-width: 48px;
        font-weight: bold;
        letter-spacing: 1px;
    }
</style>
@stop

@section('content')
<div class="card">
    <div class="card-body">

        @if($errors->has('template'))
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> {{ $errors->first('template') }}
            </div>
        @endif

        <form action="{{ route('certificates.store') }}" method="POST">
            @csrf

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Curso</label>
                        <select name="course_id" id="course_id" class="form-control" required>
                            <option value="">-- Seleccione un curso --</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}"
                                    {{ old('course_id') == $course->id ? 'selected' : '' }}>
                                    {{ $course->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('course_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group" id="person_wrapper">
                        <label>
                            Persona
                            <span id="persons-loading" style="display:none;">
                                <i class="fas fa-spinner fa-spin text-info ml-1"></i>
                                <small class="text-info">Cargando...</small>
                            </span>
                        </label>
                        <select name="person_id" id="person_id" class="form-control" required>
                            <option value="">-- Seleccione primero un curso --</option>
                        </select>
                        <small id="persons-hint" class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Seleccioná un curso para ver las personas disponibles
                        </small>
                        @error('person_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </div>

            <hr>
            <h5>Datos para Generación de CUV</h5>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Unidad Académica</label>
                        <input type="text" name="unidad_academica" id="unidad_academica"
                               class="form-control cuv-field"
                               value="{{ old('unidad_academica') }}" required>
                        @error('unidad_academica')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Área</label>
                        <input type="text" id="area" class="form-control" readonly>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Subárea</label>
                        <input type="text" name="subarea" id="subarea"
                               class="form-control cuv-field"
                               value="{{ old('subarea') }}" required>
                        @error('subarea')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Código Incremental (ID Persona)</label>
                        <input type="text" id="codigo_incremental" class="form-control" readonly>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Año</label>
                        <input type="number" name="anio" id="anio"
                               class="form-control cuv-field"
                               value="{{ old('anio', date('Y')) }}"
                               min="2000" max="2099" required>
                        <small class="form-text text-muted">Podés modificar el año si es necesario</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Iniciales</label>
                        <input type="text" name="iniciales" id="iniciales"
                               class="form-control cuv-field"
                               value="{{ old('iniciales') }}" required>
                        @error('iniciales')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>3 Últimos Dígitos del DNI</label>
                        <input type="text" id="tres_ultimos_dni" class="form-control" readonly>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Condición (Tipo Certificado)</label>
                        <input type="text"
                               name="condition"
                               id="condition"
                               class="form-control cuv-field"
                               value="{{ old('condition', 'Aprobado') }}"
                               placeholder="Ej: Aprobado, Asistente, Capacitador..."
                               required
                               autocomplete="off">
                        <small class="form-text text-muted">
                            Escribí cualquier condición. Sigla en el CUV:
                            <span class="badge badge-secondary" id="condition_code_preview">APR</span>
                        </small>
                        @error('condition')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nota (opcional)</label>
                        <input type="number" step="0.01" name="nota"
                               class="form-control"
                               value="{{ old('nota') }}">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>CUV (Código Único de Verificación) Generado</label>
                @error('cuv')
                    <div class="alert alert-danger py-1">{{ $message }}</div>
                @enderror
                <input type="text" id="cuv_display" class="form-control bg-light" readonly>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-certificate"></i> Generar Certificado
            </button>
        </form>

    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function () {

    var todasLasPersonas = [];

    /**
     * Devuelve las primeras 3 letras en mayúscula de la condición ingresada.
     * Si el texto tiene menos de 3 letras, devuelve lo que haya en mayúscula.
     */
    function getConditionCode(conditionText) {
        // Eliminar espacios al inicio y tomar solo letras (ignorar números y símbolos)
        var clean = conditionText.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ]/g, '');
        return clean.substring(0, 3).toUpperCase();
    }

    function initSelect2Disabled() {
        $('#person_id').select2({
            placeholder:    '-- Seleccione primero un curso --',
            allowClear:     true,
            dropdownParent: $('#person_wrapper'),
            language: {
                noResults: function () { return 'No se encontraron personas'; },
            }
        });
        $('#person_id').prop('disabled', true);
    }

    initSelect2Disabled();
    
    function generarSigla(texto) {
    if (!texto) return '';

        texto = texto.toUpperCase().trim();

        let palabras = texto.split(' ');

        if (palabras.length === 1) {
            return texto.substring(0, 3);
        }

        let ignorar = ['DE', 'LA', 'LAS', 'LOS', 'DEL', 'Y'];

        let sigla = '';

        palabras.forEach(p => {
            if (!ignorar.includes(p) && p.length > 0) {
                sigla += p.charAt(0);
            }
        });

        return sigla.substring(0, 3);
    }

    function updateCUV() {
        let unidad  = generarSigla($('#unidad_academica').val());
        let subarea = generarSigla($('#subarea').val());
        let area    = generarSigla($('#area').val());
        let areaText   = $('#area').val();
        let codigo     = $('#codigo_incremental').val();
        let anio       = $('#anio').val();
        let condicion  = getConditionCode($('#condition').val());
        let iniciales  = $('#iniciales').val();
        let ultimosDni = $('#tres_ultimos_dni').val();
        let anioCorto  = String(anio).slice(-2);

        // Actualizar el badge de preview de sigla
        $('#condition_code_preview').text(condicion || '---');

        $('#cuv_display').val(
            (unidad + area + subarea + codigo + anioCorto + condicion + iniciales + ultimosDni).toUpperCase()
        );
    }

    // Inicializar badge y CUV con el valor por defecto del campo condition
    updateCUV();

    $('#course_id').on('change', function () {
        let courseId = $(this).val();

        $('#person_id').val(null).trigger('change');
        $('#person_id').html('<option value="">-- Seleccione una persona --</option>');
        $('#area').val('');
        $('#codigo_incremental').val('');
        $('#tres_ultimos_dni').val('');
        $('#iniciales').val('');
        todasLasPersonas = [];
        updateCUV();

        if (!courseId) {
            initSelect2Disabled();
            $('#persons-hint').html(
                '<i class="fas fa-info-circle"></i> Seleccioná un curso para ver las personas disponibles'
            );
            return;
        }

        $('#persons-loading').show();
        initSelect2Disabled();

        $.ajax({
            url:  "{{ url('get-persons-by-course') }}/" + courseId,
            type: 'GET',
            success: function (data) {
                $('#area').val(data.area_name);

                let options = '<option value="">-- Seleccione una persona --</option>';

                if (data.persons.length === 0) {
                    if (data.total_area === 0) {
                        $('#persons-hint').html(
                            '<span class="text-warning">' +
                            '<i class="fas fa-exclamation-triangle"></i> ' +
                            'No hay personas registradas en el área "' + data.area_name + '"' +
                            '</span>'
                        );
                    } else {
                        $('#persons-hint').html(
                            '<span class="text-info">' +
                            '<i class="fas fa-info-circle"></i> ' +
                            'Las ' + data.total_area + ' persona(s) del área "' + data.area_name +
                            '" ya tienen certificado en este curso.' +
                            '</span>'
                        );
                    }
                    $('#person_id').html(options);
                    initSelect2Disabled();
                } else {
                    todasLasPersonas = data.persons;

                    data.persons.forEach(function (p) {
                        options += `<option value="${p.id}"
                                        data-person-id="${p.id}"
                                        data-dni="${p.dni}"
                                        data-apellido="${p.apellido}"
                                        data-nombre="${p.nombre}">
                                        ${p.apellido}, ${p.nombre} — DNI: ${p.dni}
                                    </option>`;
                    });

                    let msg = '<span class="text-success">' +
                              '<i class="fas fa-check-circle"></i> ' +
                              data.persons.length + ' persona(s) disponibles';

                    if (data.ya_tienen > 0) {
                        msg += ' <span class="text-muted">(' + data.ya_tienen +
                               ' ya tienen certificado y fueron excluidas)</span>';
                    }

                    msg += ' — <strong>Escribí para buscar</strong></span>';
                    $('#persons-hint').html(msg);

                    $('#person_id').html(options);

                    $('#person_id').select2({
                        placeholder:    '-- Escribí nombre o DNI para buscar --',
                        allowClear:     true,
                        dropdownParent: $('#person_wrapper'),
                        language: {
                            noResults: function () { return 'No se encontraron personas'; },
                            searching: function () { return 'Buscando...'; },
                        },
                    });
                    $('#person_id').prop('disabled', false);
                }

                updateCUV();
            },
            error: function () {
                $('#person_id').html('<option value="">Error al cargar personas</option>');
                initSelect2Disabled();
                $('#persons-hint').html(
                    '<span class="text-danger">' +
                    '<i class="fas fa-times-circle"></i> Error al cargar las personas' +
                    '</span>'
                );
            },
            complete: function () {
                $('#persons-loading').hide();
            }
        });
    });

    $('#person_id').on('change', function () {
        let selected  = $(this).find('option:selected');
        let personId  = selected.data('person-id');
        let dni       = selected.data('dni')      ? String(selected.data('dni'))      : '';
        let apellido  = selected.data('apellido') ? String(selected.data('apellido')) : '';
        let nombre    = selected.data('nombre')   ? String(selected.data('nombre'))   : '';

        let iniciales = '';
        if (apellido) iniciales += apellido.trim().charAt(0).toUpperCase();
        if (nombre)   iniciales += nombre.trim().charAt(0).toUpperCase();

        $('#codigo_incremental').val(personId || '');
        $('#tres_ultimos_dni').val(dni.slice(-3));
        $('#iniciales').val(iniciales);
        updateCUV();
    });

    // Escuchar cambios en todos los campos que afectan el CUV
    $('.cuv-field').on('keyup change input', updateCUV);

});
</script>
@endsection