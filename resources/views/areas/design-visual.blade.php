@extends('adminlte::page')

@section('title', 'Diseñar Certificado — ' . $area->nombre)

@section('content_header')
    <h1>
        <i class="fas fa-paint-brush text-warning"></i>
        Diseñar Certificado
        <small class="text-muted ml-2">{{ $area->nombre }}</small>
    </h1>
    <div class="mt-2">
        <a href="{{ route('areas.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        @if($area->template_front && $area->template_back)
            <a href="{{ route('areas.preview', $area->id) }}"
               target="_blank"
               class="btn btn-sm btn-outline-info ml-2">
                <i class="fas fa-eye"></i> Ver PDF guardado
            </a>
            <button type="button"
                    class="btn btn-sm btn-outline-danger ml-2"
                    onclick="confirmClearTemplate()">
                <i class="fas fa-trash-alt"></i> Eliminar diseño guardado
            </button>
            <form id="clearTemplateForm"
                  action="{{ route('areas.clear.template', $area->id) }}"
                  method="POST"
                  style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

<div class="row">

    {{-- ── PANEL IZQUIERDO ──────────────────────────────────────────────────── --}}
    <div class="col-md-2 pr-1">

        <div class="card card-outline card-warning mb-2">
            <div class="card-body p-2 text-center">
                <div class="btn-group btn-group-sm w-100">
                    <button id="btnSideFront" class="btn btn-warning active" onclick="switchSide('front')">
                        <i class="fas fa-file"></i> Frente
                    </button>
                    <button id="btnSideBack" class="btn btn-outline-warning" onclick="switchSide('back')">
                        <i class="fas fa-file-alt"></i> Dorso
                    </button>
                </div>
            </div>
        </div>

        <div class="card card-outline card-secondary mb-2">
            <div class="card-header p-2">
                <h6 class="mb-0 small"><i class="fas fa-image"></i> Imagen de fondo</h6>
            </div>
            <div class="card-body p-2">
                <input type="file" id="bgInput" accept="image/jpeg,image/png" class="form-control-file" style="font-size:11px;">
                <small class="text-muted">JPG o PNG, máx. 5MB.</small>
                <div id="bgUploadProgress" class="mt-1" style="display:none;">
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" style="width:100%"></div>
                    </div>
                    <small class="text-muted">Subiendo imagen...</small>
                </div>
                <button class="btn btn-xs btn-outline-danger btn-block mt-1" onclick="removeBg()">
                    <i class="fas fa-times"></i> Quitar fondo
                </button>
            </div>
        </div>

        <div class="card card-outline card-primary mb-2">
            <div class="card-header p-2">
                <h6 class="mb-0 small"><i class="fas fa-th-large"></i> Elementos</h6>
            </div>
            <div class="card-body p-2">
                <p class="text-muted" style="font-size:10px;">Hacé clic para agregar al certificado</p>

                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('nombre')">
                    <i class="fas fa-user"></i> Nombre y apellido
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('curso')">
                    <i class="fas fa-graduation-cap"></i> Nombre del curso
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('area')">
                    <i class="fas fa-sitemap"></i> Área
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('condicion')">
                    <i class="fas fa-certificate"></i> Condición
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('horas')">
                    <i class="fas fa-clock"></i> Horas
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('fecha')">
                    <i class="fas fa-calendar"></i> Fecha
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('anio')">
                    <i class="fas fa-calendar-alt"></i> Año
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('cuv')">
                    <i class="fas fa-key"></i> CUV
                </button>
                <button class="btn btn-xs btn-outline-success btn-block mb-1" onclick="addElement('qr')">
                    <i class="fas fa-qrcode"></i> Código QR
                </button>
                <button class="btn btn-xs btn-outline-success btn-block mb-1" onclick="addElement('firma_responsable', {firma_index:0})">
                    <i class="fas fa-signature"></i> Firma 1
                </button>
                <button class="btn btn-xs btn-outline-success btn-block mb-1" onclick="addElement('firma_responsable', {firma_index:1})">
                    <i class="fas fa-signature"></i> Firma 2
                </button>
                <button class="btn btn-xs btn-outline-success btn-block mb-1" onclick="addElement('firma_responsable', {firma_index:2})">
                    <i class="fas fa-signature"></i> Firma 3
                </button>
                <button class="btn btn-xs btn-outline-success btn-block mb-1" onclick="addElement('firma_responsable', {firma_index:3})">
                    <i class="fas fa-signature"></i> Firma 4
                </button>
                <button class="btn btn-xs btn-outline-success btn-block mb-1" onclick="addElement('firma_responsable', {firma_index:4})">
                    <i class="fas fa-signature"></i> Firma 5
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('objetivo')">
                    <i class="fas fa-bullseye"></i> Objetivo
                </button>
                <button class="btn btn-xs btn-outline-primary btn-block mb-1" onclick="addElement('contenido')">
                    <i class="fas fa-list-ul"></i> Contenido
                </button>
                <button class="btn btn-xs btn-outline-dark btn-block mb-1" onclick="addElement('texto')">
                    <i class="fas fa-font"></i> Texto libre
                </button>
                <button class="btn btn-xs btn-outline-dark btn-block mb-1" onclick="triggerImageUpload()">
                    <i class="fas fa-image"></i> Imagen / Logo
                </button>
                <input type="file" id="imgUpload" accept="image/*" style="display:none;">
            </div>
        </div>

    </div>

    {{-- ── CANVAS CENTRAL ───────────────────────────────────────────────────── --}}
    <div class="col-md-7 px-1">
        <div class="card mb-2">
            <div class="card-header p-2 d-flex justify-content-between align-items-center">
                <span id="sideLabel" class="font-weight-bold text-warning">
                    <i class="fas fa-file"></i> Editando: FRENTE
                </span>
                <div class="d-flex align-items-center">
                    <span class="badge badge-success mr-2" style="font-size:10px;">
                        <i class="fas fa-check-circle"></i> WYSIWYG — lo que ves es lo que sale en el PDF
                    </span>
                    <button class="btn btn-xs btn-outline-secondary" onclick="clearCanvas()">
                        <i class="fas fa-trash"></i> Limpiar
                    </button>
                </div>
            </div>

            {{--
                El canvas interno trabaja en coordenadas reales del PDF: 1122×793px.
                Se escala visualmente al 75% con CSS transform:scale(SCALE).
                El wrapper ocupa el espacio visual resultante: 1122*0.75 × 793*0.75 = 841.5 × 594.75 ≈ 842×595px.
                Las coordenadas x,y,width,height,fontSize guardadas son siempre en espacio PDF (1122×793).
                DomPDF recibe el HTML sin ningún factor de escala adicional → WYSIWYG perfecto.
            --}}
            <div class="card-body p-1 text-center" style="background:#888; overflow:auto;">
                <div id="canvasWrapper" style="
                    width: {{ round(1122 * 0.75) }}px;
                    height: {{ round(793 * 0.75) }}px;
                    margin: 0 auto;
                    position: relative;
                ">
                    <div id="canvas" style="
                        position: absolute;
                        top: 0; left: 0;
                        width: 1122px;
                        height: 793px;
                        background: #fff;
                        transform-origin: top left;
                        transform: scale(0.75);
                        overflow: hidden;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.4);
                    ">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body py-2 d-flex justify-content-between align-items-center flex-wrap">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Arrastrá los elementos para posicionarlos. Hacé clic para seleccionar y editar.
                    <strong>Las coordenadas corresponden exactamente al PDF.</strong>
                </small>
                <div class="d-flex align-items-center flex-wrap">
                    <div class="btn-group mr-3 mb-1">
                        <button class="btn btn-outline-info btn-sm"
                                onclick="switchSide(currentSide === 'front' ? 'back' : 'front')">
                            <i class="fas fa-exchange-alt"></i>
                            Ir al <span id="switchLabel">Dorso</span>
                        </button>
                    </div>
                    <div class="btn-group mb-1">
                        <button class="btn btn-warning btn-sm"
                                onclick="livePreview()"
                                id="btnPreview">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button class="btn btn-success btn-sm px-4 font-weight-bold"
                                onclick="saveDesign()">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── PANEL DERECHO: PROPIEDADES ───────────────────────────────────────── --}}
    <div class="col-md-3 pl-1">
        <div class="card" id="propsPanel">
            <div class="card-header p-2">
                <h6 class="mb-0 small"><i class="fas fa-sliders-h"></i> Propiedades</h6>
            </div>
            <div class="card-body p-2" id="propsBody">
                <p class="text-muted small text-center mt-3">
                    <i class="fas fa-mouse-pointer fa-2x d-block mb-2"></i>
                    Hacé clic en un elemento del certificado para editar sus propiedades
                </p>
            </div>
        </div>

        <div class="card mt-2 card-outline card-secondary">
            <div class="card-header p-2">
                <h6 class="mb-0 small"><i class="fas fa-info-circle"></i> Vista previa con datos de</h6>
            </div>
            <div class="card-body p-2">
                <small class="text-muted">
                    <strong>Nombre:</strong> Juan Pérez<br>
                    <strong>Curso:</strong> Curso de Ejemplo<br>
                    <strong>Área:</strong> {{ $area->nombre }}<br>
                    <strong>Año:</strong> {{ date('Y') }}<br>
                    <strong>Condición:</strong> Aprobado<br>
                    <strong>Objetivo:</strong> Capacitar al personal...<br>
                    <strong>Contenido:</strong> Módulo 1, 2, 3...<br>
                    <strong>Firma 1:</strong> María García (Capacitadora)<br>
                    <strong>Firma 2:</strong> Carlos López (Coordinador)<br>
                    <strong>Firma 3:</strong> Ana Martínez (Directora)<br>
                    <strong>Firma 4:</strong> Pedro Rodríguez (Supervisor)<br>
                    <strong>Firma 5:</strong> Laura Sánchez (Coordinadora)
                </small>
            </div>
        </div>

        <div class="card mt-2 card-outline card-info">
            <div class="card-body p-2">
                <small class="text-info">
                    <i class="fas fa-ruler-combined"></i>
                    <strong>Canvas real:</strong> 1122 × 793 px (A4 landscape)<br>
                    <strong>Vista en pantalla:</strong> escalada al 75%<br>
                    Las coordenadas X/Y en propiedades son las del PDF real.
                </small>
            </div>
        </div>
    </div>

</div>

<form id="saveForm" action="{{ route('areas.design.save', $area->id) }}" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="design_front" id="inputDesignFront">
    <input type="hidden" name="design_back"  id="inputDesignBack">
</form>

<form id="previewForm" action="{{ route('areas.preview.live', $area->id) }}" method="POST" target="_blank" style="display:none;">
    @csrf
    <input type="hidden" name="design_front" id="previewInputFront">
    <input type="hidden" name="design_back"  id="previewInputBack">
</form>

<script>
    var INITIAL_FRONT = @json($designFront);
    var INITIAL_BACK  = @json($designBack);
    var UPLOAD_BG_URL = "{{ route('areas.upload.background', $area->id) }}";
    var CSRF_TOKEN    = "{{ csrf_token() }}";

    // ── Dimensiones reales del PDF (coinciden exactamente con DomPDF A4 landscape a 96dpi) ──
    var PDF_W = 1122;
    var PDF_H = 793;

    // ── Factor de escala visual únicamente (no afecta coordenadas guardadas) ──
    var SCALE = 0.75; // 1122 * 0.75 = 841.5 ≈ 842px visible
</script>

@stop

@section('css')
<style>
    .btn-xs { padding: 3px 8px; font-size: 11px; line-height: 1.4; border-radius: 3px; }

    .cert-element {
        position: absolute;
        cursor: move;
        border: 2px dashed transparent;
        box-sizing: border-box;
        user-select: none;
    }
    .cert-element:hover    { border-color: #aaa; }
    .cert-element.selected { border-color: #007bff !important; }

    .cert-element .el-delete {
        display: none;
        position: absolute;
        top: -10px; right: -10px;
        width: 20px; height: 20px;
        background: #dc3545; color: #fff;
        border-radius: 50%; font-size: 12px;
        line-height: 20px; text-align: center;
        cursor: pointer; z-index: 999;
    }
    .cert-element.selected .el-delete { display: block; }

    .cert-element .el-resize {
        display: none;
        position: absolute;
        bottom: -6px; right: -6px;
        width: 12px; height: 12px;
        background: #007bff; border-radius: 2px;
        cursor: se-resize; z-index: 999;
    }
    .cert-element.selected .el-resize { display: block; }

    #canvas { cursor: default; }
    #canvasWrapper { display: inline-block; }
</style>
@stop

@section('js')
<script>
// ════════════════════════════════════════════════════════════════════════════
// Estado global
// ════════════════════════════════════════════════════════════════════════════
var currentSide = 'front';
var designs = {
    front: INITIAL_FRONT || { background: '', elements: [] },
    back:  INITIAL_BACK  || { background: '', elements: [] },
};
var selectedEl = null;
var elCounter  = 0;

var TYPE_LABELS = {
    nombre:            'Nombre y apellido',
    curso:             'Nombre del curso',
    area:              'Área',
    condicion:         'Condición',
    horas:             'Horas',
    fecha:             'Fecha',
    anio:              'Año',
    cuv:               'CUV',
    qr:                'Código QR',
    firma_responsable: 'Firma de Responsable',
    objetivo:          'Objetivo',
    contenido:         'Contenido',
    texto:             'Texto libre',
    imagen:            'Imagen / Logo',
};

var SAMPLE_RESPONSABLES = [
    { nombre: 'María García',    cargo: 'Capacitadora'  },
    { nombre: 'Carlos López',    cargo: 'Coordinador'   },
    { nombre: 'Ana Martínez',    cargo: 'Directora'     },
    { nombre: 'Pedro Rodríguez', cargo: 'Supervisor'    },
    { nombre: 'Laura Sánchez',   cargo: 'Coordinadora'  },
];

var SAMPLE = {
    nombre:    'Juan Pérez',
    curso:     'Curso de Ejemplo',
    area:      '{{ $area->nombre }}',
    condicion: 'Aprobado',
    horas:     '40 horas',
    fecha:     new Date().toLocaleDateString('es-AR'),
    anio:      new Date().getFullYear().toString(),
    cuv:       'CUV: UAARESUB126APRJP678',
    qr:        '(QR)',
    texto:     'Texto libre',
    imagen:    '(Imagen)',
    objetivo:  'Objetivo: Capacitar al personal en el uso de herramientas tecnológicas.',
    contenido: 'Contenido: Módulo 1: Introducción. Módulo 2: Práctica. Módulo 3: Evaluación.',
};

// ════════════════════════════════════════════════════════════════════════════
// Inicialización
// ════════════════════════════════════════════════════════════════════════════
$(document).ready(function () {
    renderCanvas();

    // ── Fondo del certificado ────────────────────────────────────────────────
    $('#bgInput').on('change', function () {
        var file = this.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire('Archivo muy grande', 'La imagen no puede superar los 5MB.', 'warning');
            $(this).val('');
            return;
        }

        // Preview inmediata en el canvas
        var reader = new FileReader();
        reader.onload = function (e) {
            var canvas = document.getElementById('canvas');
            canvas.style.backgroundImage    = "url('" + e.target.result + "')";
            canvas.style.backgroundSize     = 'cover';
            canvas.style.backgroundPosition = 'center';
            canvas.style.backgroundColor    = '';
            designs[currentSide].background = e.target.result; // temporal hasta que suba
        };
        reader.readAsDataURL(file);

        // Subir al servidor y reemplazar base64 por URL permanente
        var formData = new FormData();
        formData.append('image', file);
        formData.append('side',  currentSide);
        formData.append('_token', CSRF_TOKEN);

        $('#bgUploadProgress').show();
        $('#bgInput').prop('disabled', true);

        $.ajax({
            url:         UPLOAD_BG_URL,
            type:        'POST',
            data:        formData,
            processData: false,
            contentType: false,
            success: function (res) {
                designs[currentSide].background = res.url;
            },
            error: function (xhr) {
                var msg = 'No se pudo subir la imagen al servidor.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg += ' ' + xhr.responseJSON.message;
                Swal.fire('Error al subir', msg, 'error');
                designs[currentSide].background = '';
                var canvas = document.getElementById('canvas');
                canvas.style.backgroundImage = 'none';
                canvas.style.backgroundColor = '#ffffff';
            },
            complete: function () {
                $('#bgUploadProgress').hide();
                $('#bgInput').prop('disabled', false).val('');
            },
        });
    });

    // ── Imágenes / logos ─────────────────────────────────────────────────────
    $('#imgUpload').on('change', function () {
        var file = this.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire('Archivo muy grande', 'La imagen no puede superar los 5MB.', 'warning');
            $(this).val('');
            return;
        }
        var formData = new FormData();
        formData.append('image',  file);
        formData.append('side',   currentSide);
        formData.append('_token', CSRF_TOKEN);

        Swal.fire({
            title: 'Subiendo imagen...',
            allowOutsideClick: false,
            didOpen: function () { Swal.showLoading(); },
        });

        $.ajax({
            url:         UPLOAD_BG_URL,
            type:        'POST',
            data:        formData,
            processData: false,
            contentType: false,
            success:  function (res) { Swal.close(); addElement('imagen', { src: res.url }); },
            error:    function ()    { Swal.fire('Error', 'No se pudo subir la imagen.', 'error'); },
            complete: function ()    { $('#imgUpload').val(''); },
        });
    });

    // Clic en zona vacía del canvas → deseleccionar
    $('#canvas').on('click', function (e) {
        if ($(e.target).is('#canvas')) deselect();
    });
});

// ════════════════════════════════════════════════════════════════════════════
// Navegación entre caras
// ════════════════════════════════════════════════════════════════════════════
function switchSide(side) {
    saveCurrentToState();
    currentSide = side;
    $('#btnSideFront').toggleClass('active btn-warning',       side === 'front')
                     .toggleClass('btn-outline-warning',       side !== 'front');
    $('#btnSideBack') .toggleClass('active btn-warning',       side === 'back')
                     .toggleClass('btn-outline-warning',       side !== 'back');
    $('#sideLabel').html(side === 'front'
        ? '<i class="fas fa-file"></i> Editando: FRENTE'
        : '<i class="fas fa-file-alt"></i> Editando: DORSO');
    $('#switchLabel').text(side === 'front' ? 'Dorso' : 'Frente');
    selectedEl = null;
    showProps(null);
    renderCanvas();
}

// ════════════════════════════════════════════════════════════════════════════
// Renderizado del canvas
// ════════════════════════════════════════════════════════════════════════════
function renderCanvas() {
    var canvas = document.getElementById('canvas');
    var design = designs[currentSide];

    if (design.background) {
        canvas.style.backgroundImage    = "url('" + design.background + "')";
        canvas.style.backgroundSize     = 'cover';
        canvas.style.backgroundPosition = 'center';
        canvas.style.backgroundColor    = '';
    } else {
        canvas.style.backgroundImage = 'none';
        canvas.style.backgroundColor = '#ffffff';
    }

    $(canvas).find('.cert-element').remove();
    design.elements.forEach(function (el) { appendElToCanvas(el); });
}

// ════════════════════════════════════════════════════════════════════════════
// Agregar elemento
// ════════════════════════════════════════════════════════════════════════════
function addElement(type, extra) {
    var defaults = getDefaults(type);
    var el = Object.assign({
        id:          'el_' + (++elCounter),
        type:        type,
        // Coordenadas en espacio PDF real (1122×793px)
        x:           80,
        y:           80 + (designs[currentSide].elements.length * 50),
        width:       defaults.width,
        height:      defaults.height,
        fontSize:    defaults.fontSize || 18,
        color:       '#000000',
        bold:        defaults.bold  || false,
        italic:      false,
        align:       defaults.align || 'center',
        content:     defaults.content || '',
        firma_index: 0,
    }, extra || {});

    // Evitar que el elemento quede fuera del canvas
    el.x = Math.min(el.x, PDF_W - el.width);
    el.y = Math.min(el.y, PDF_H - el.height);

    designs[currentSide].elements.push(el);
    var $domEl = appendElToCanvas(el);
    selectEl($domEl, el);
}

// ════════════════════════════════════════════════════════════════════════════
// Tamaños por defecto (en espacio PDF real 1122×793px)
// ════════════════════════════════════════════════════════════════════════════
function getDefaults(type) {
    var map = {
        nombre:            { width: 534, height: 60,  fontSize: 28, bold: true               },
        curso:             { width: 534, height: 53,  fontSize: 22                           },
        area:              { width: 400, height: 40,  fontSize: 16                           },
        condicion:         { width: 334, height: 47,  fontSize: 20, bold: true               },
        horas:             { width: 267, height: 40,  fontSize: 16                           },
        fecha:             { width: 267, height: 40,  fontSize: 16                           },
        anio:              { width: 133, height: 40,  fontSize: 16                           },
        cuv:               { width: 400, height: 33,  fontSize: 12                           },
        qr:                { width: 133, height: 133                                         },
        firma_responsable: { width: 200, height: 133, fontSize: 11, align: 'center'          },
        objetivo:          { width: 667, height: 107, fontSize: 13, align: 'left'            },
        contenido:         { width: 667, height: 160, fontSize: 13, align: 'left'            },
        texto:             { width: 334, height: 47,  fontSize: 14, content: 'Escribí aquí' },
        imagen:            { width: 160, height: 107                                         },
    };
    return map[type] || { width: 200, height: 53 };
}

// ════════════════════════════════════════════════════════════════════════════
// Insertar elemento en el DOM del canvas
// ════════════════════════════════════════════════════════════════════════════
function appendElToCanvas(el) {
    var $canvas = $('#canvas');

    // Las coordenadas y dimensiones están en px del espacio PDF (1122×793).
    // El canvas DOM tiene ese mismo tamaño; CSS transform:scale(0.75) hace
    // la reducción visual sin afectar los valores guardados.
    var $el = $('<div>')
        .addClass('cert-element')
        .attr('data-id', el.id)
        .css({
            left:   el.x      + 'px',
            top:    el.y      + 'px',
            width:  el.width  + 'px',
            height: el.height + 'px',
        });

    var $inner = $('<div>').addClass('el-inner').css({
        width:          '100%',
        height:         '100%',
        overflow:       'hidden',
        fontSize:       (el.fontSize || 14) + 'px',
        color:          el.color || '#000',
        fontWeight:     el.bold   ? 'bold'   : 'normal',
        fontStyle:      el.italic ? 'italic' : 'normal',
        textAlign:      el.align  || 'center',
        lineHeight:     '1.3',
        display:        'flex',
        flexDirection:  'column',
        alignItems:     el.align === 'left' ? 'flex-start' : (el.align === 'right' ? 'flex-end' : 'center'),
        justifyContent: 'center',
        wordWrap:       'break-word',
        whiteSpace:     'normal',
    });

    // ── Contenido visual según tipo ──────────────────────────────────────────
    if (el.type === 'firma_responsable') {
        var idx  = el.firma_index !== undefined ? el.firma_index : 0;
        var resp = SAMPLE_RESPONSABLES[idx] || { nombre: 'Responsable ' + (idx + 1), cargo: 'Cargo' };
        var fs   = el.fontSize || 11;
        $inner.css({ justifyContent: 'flex-end' });
        $inner.html(
            '<div style="flex:1;width:100%;border-bottom:1px dashed #999;"></div>'
            + '<div style="font-size:' + fs + 'px;font-weight:bold;text-align:center;width:100%;margin-top:3px;">' + resp.nombre + '</div>'
            + '<div style="font-size:' + Math.max(9, fs - 1) + 'px;color:#555;text-align:center;width:100%;">' + resp.cargo + '</div>'
        );
    } else if (el.type === 'qr') {
        $inner.css({ justifyContent: 'center', fontSize: '11px', color: '#666' }).text('(QR)');
    } else if (el.type === 'imagen' && el.src) {
        $inner.html('<img src="' + el.src + '" style="max-width:100%;max-height:100%;object-fit:contain;">');
    } else {
        $inner.text(getElLabel(el));
    }

    // ── Controles de borrar y redimensionar ─────────────────────────────────
    var $del = $('<div>').addClass('el-delete').html('&times;').on('click', function (e) {
        e.stopPropagation();
        deleteEl(el.id);
    });
    var $resize = $('<div>').addClass('el-resize').on('mousedown', function (e) {
        e.stopPropagation();
        e.preventDefault();
        startResize(e, el, $el);
    });

    $el.append($inner).append($del).append($resize);
    $canvas.append($el);

    makeDraggable($el, el);
    $el.on('click', function (e) { e.stopPropagation(); selectEl($el, el); });

    return $el;
}

function getElLabel(el) {
    if (el.type === 'texto')  return el.content || 'Texto libre';
    if (el.type === 'imagen') return '(Imagen)';
    if (el.type === 'firma_responsable') {
        var idx  = el.firma_index !== undefined ? el.firma_index : 0;
        var resp = SAMPLE_RESPONSABLES[idx];
        return resp ? resp.nombre : 'Firma ' + (idx + 1);
    }
    return SAMPLE[el.type] || TYPE_LABELS[el.type] || el.type;
}

// ════════════════════════════════════════════════════════════════════════════
// Drag & drop
// ════════════════════════════════════════════════════════════════════════════
function makeDraggable($el, elData) {
    var isDragging  = false;
    var startMouseX, startMouseY, startLeft, startTop;

    $el.on('mousedown', function (e) {
        if ($(e.target).hasClass('el-delete') || $(e.target).hasClass('el-resize')) return;
        e.preventDefault();
        e.stopPropagation();

        isDragging  = true;
        startMouseX = e.clientX;
        startMouseY = e.clientY;
        startLeft   = parseInt($el.css('left'))  || 0;
        startTop    = parseInt($el.css('top'))   || 0;

        $(document).on('mousemove.drag', function (e) {
            if (!isDragging) return;

            // Dividir el desplazamiento en pantalla por SCALE para obtener
            // el desplazamiento en el espacio real del PDF.
            var dx = (e.clientX - startMouseX) / SCALE;
            var dy = (e.clientY - startMouseY) / SCALE;

            var newLeft = Math.round(Math.max(0, Math.min(startLeft + dx, PDF_W - elData.width)));
            var newTop  = Math.round(Math.max(0, Math.min(startTop  + dy, PDF_H - elData.height)));

            $el.css({ left: newLeft + 'px', top: newTop + 'px' });
            elData.x = newLeft;
            elData.y = newTop;
        });

        $(document).on('mouseup.drag', function () {
            if (!isDragging) return;
            isDragging = false;
            $(document).off('mousemove.drag mouseup.drag');

            if (selectedEl && selectedEl.data.id === elData.id) {
                $('#propX').val(elData.x);
                $('#propY').val(elData.y);
            }
        });
    });
}

// ════════════════════════════════════════════════════════════════════════════
// Redimensionado
// ════════════════════════════════════════════════════════════════════════════
function startResize(e, elData, $el) {
    var startX = e.clientX, startY = e.clientY;
    var startW = elData.width, startH = elData.height;

    $(document).on('mousemove.resize', function (e) {
        var newW = Math.round(Math.max(50, startW + (e.clientX - startX) / SCALE));
        var newH = Math.round(Math.max(20, startH + (e.clientY - startY) / SCALE));

        newW = Math.min(newW, PDF_W - elData.x);
        newH = Math.min(newH, PDF_H - elData.y);

        elData.width  = newW;
        elData.height = newH;
        $el.css({ width: newW + 'px', height: newH + 'px' });
    });

    $(document).on('mouseup.resize', function () {
        $(document).off('mousemove.resize mouseup.resize');
        if (selectedEl && selectedEl.data.id === elData.id) {
            $('#propW').val(elData.width);
            $('#propH').val(elData.height);
        }
    });
}

// ════════════════════════════════════════════════════════════════════════════
// Selección y propiedades
// ════════════════════════════════════════════════════════════════════════════
function selectEl($domEl, elData) {
    $('.cert-element').removeClass('selected');
    $domEl.addClass('selected');
    selectedEl = { $dom: $domEl, data: elData };
    showProps(elData);
}

function deselect() {
    $('.cert-element').removeClass('selected');
    selectedEl = null;
    showProps(null);
}

function showProps(elData) {
    var $body = $('#propsBody');
    if (!elData) {
        $body.html('<p class="text-muted small text-center mt-3"><i class="fas fa-mouse-pointer fa-2x d-block mb-2"></i>Hacé clic en un elemento para editarlo</p>');
        return;
    }

    var typeLabel = TYPE_LABELS[elData.type] || elData.type;
    var noStyle   = ['qr', 'imagen'].includes(elData.type);

    var html = '<h6 class="small font-weight-bold border-bottom pb-1">' + typeLabel + '</h6>';

    if (elData.type === 'texto') {
        html += '<div class="form-group mb-1"><label class="small mb-0">Texto</label>'
            + '<textarea id="propContent" class="form-control form-control-sm" rows="2">' + (elData.content || '') + '</textarea></div>';
    }

    if (elData.type === 'firma_responsable') {
        var idx = elData.firma_index !== undefined ? elData.firma_index : 0;
        html += '<div class="alert alert-info p-1 mb-2" style="font-size:10px;">'
            + '<i class="fas fa-signature"></i> Mostrará firma, nombre y cargo del <strong>Responsable ' + (idx + 1) + '</strong> del curso.'
            + '</div>';
    }

    if (elData.type === 'objetivo' || elData.type === 'contenido') {
        html += '<div class="alert alert-info p-1 mb-2" style="font-size:10px;">'
            + '<i class="fas fa-info-circle"></i> El texto se toma automáticamente del curso.'
            + '</div>';
    }

    if (!noStyle) {
        html += '<div class="form-group mb-1"><label class="small mb-0">Tamaño de texto (px PDF)</label>'
            + '<input type="number" id="propFontSize" class="form-control form-control-sm" value="' + (elData.fontSize || 14) + '" min="8" max="120"></div>'
            + '<div class="form-group mb-1"><label class="small mb-0">Color de texto</label>'
            + '<input type="color" id="propColor" class="form-control form-control-sm" value="' + (elData.color || '#000000') + '"></div>'
            + '<div class="form-group mb-1"><label class="small mb-0">Alineación</label>'
            + '<select id="propAlign" class="form-control form-control-sm">'
            + '<option value="left"'   + (elData.align === 'left'   ? ' selected' : '') + '>Izquierda</option>'
            + '<option value="center"' + (elData.align === 'center' ? ' selected' : '') + '>Centro</option>'
            + '<option value="right"'  + (elData.align === 'right'  ? ' selected' : '') + '>Derecha</option>'
            + '</select></div>'
            + '<div class="form-check form-check-inline mb-1">'
            + '<input class="form-check-input" type="checkbox" id="propBold" ' + (elData.bold ? 'checked' : '') + '>'
            + '<label class="form-check-label small" for="propBold">Negrita</label></div>'
            + '<div class="form-check form-check-inline mb-2">'
            + '<input class="form-check-input" type="checkbox" id="propItalic" ' + (elData.italic ? 'checked' : '') + '>'
            + '<label class="form-check-label small" for="propItalic">Cursiva</label></div>';
    }

    html += '<hr class="my-1">'
        + '<div class="row mb-1">'
        + '<div class="col-6"><label class="small mb-0">Ancho (px)</label>'
        + '<input type="number" id="propW" class="form-control form-control-sm" value="' + elData.width  + '" min="20"></div>'
        + '<div class="col-6"><label class="small mb-0">Alto (px)</label>'
        + '<input type="number" id="propH" class="form-control form-control-sm" value="' + elData.height + '" min="10"></div>'
        + '</div>'
        + '<div class="row mb-2">'
        + '<div class="col-6"><label class="small mb-0">X (px PDF)</label>'
        + '<input type="number" id="propX" class="form-control form-control-sm" value="' + elData.x + '" min="0" max="' + PDF_W + '"></div>'
        + '<div class="col-6"><label class="small mb-0">Y (px PDF)</label>'
        + '<input type="number" id="propY" class="form-control form-control-sm" value="' + elData.y + '" min="0" max="' + PDF_H + '"></div>'
        + '</div>'
        + '<button class="btn btn-primary btn-sm btn-block" onclick="applyProps()"><i class="fas fa-check"></i> Aplicar</button>'
        + '<button class="btn btn-danger btn-sm btn-block mt-1" onclick="deleteEl(\'' + elData.id + '\')"><i class="fas fa-trash"></i> Eliminar</button>';

    $body.html(html);
}

function applyProps() {
    if (!selectedEl) return;
    var el = selectedEl.data, $dom = selectedEl.$dom;

    if (el.type === 'texto') el.content = $('#propContent').val();

    if (!['qr', 'imagen'].includes(el.type)) {
        el.fontSize = parseInt($('#propFontSize').val()) || 14;
        el.color    = $('#propColor').val();
        el.align    = $('#propAlign').val();
        el.bold     = $('#propBold').is(':checked');
        el.italic   = $('#propItalic').is(':checked');
    }

    el.width  = Math.max(20,  parseInt($('#propW').val()) || el.width);
    el.height = Math.max(10,  parseInt($('#propH').val()) || el.height);
    el.x      = Math.max(0,   parseInt($('#propX').val()));
    el.y      = Math.max(0,   parseInt($('#propY').val()));

    // Clamp para no salir del canvas
    el.x = Math.min(el.x, PDF_W - el.width);
    el.y = Math.min(el.y, PDF_H - el.height);

    $dom.remove();
    designs[currentSide].elements = designs[currentSide].elements.map(function (e) {
        return e.id === el.id ? el : e;
    });
    var $new = appendElToCanvas(el);
    selectEl($new, el);
}

// ════════════════════════════════════════════════════════════════════════════
// Eliminar elemento
// ════════════════════════════════════════════════════════════════════════════
function deleteEl(id) {
    designs[currentSide].elements = designs[currentSide].elements.filter(function (e) {
        return e.id !== id;
    });
    $('[data-id="' + id + '"]').remove();
    if (selectedEl && selectedEl.data.id === id) {
        selectedEl = null;
        showProps(null);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// Limpiar canvas
// ════════════════════════════════════════════════════════════════════════════
function clearCanvas() {
    Swal.fire({
        title: '¿Limpiar el ' + (currentSide === 'front' ? 'frente' : 'dorso') + '?',
        text:  'Se eliminarán todos los elementos de esta cara.',
        icon:  'warning',
        showCancelButton:    true,
        confirmButtonColor:  '#d33',
        cancelButtonColor:   '#6c757d',
        confirmButtonText:   'Sí, limpiar',
        cancelButtonText:    'Cancelar',
        reverseButtons:      true,
    }).then(function (r) {
        if (r.isConfirmed) {
            designs[currentSide].elements   = [];
            designs[currentSide].background = '';
            selectedEl = null;
            showProps(null);
            renderCanvas();
        }
    });
}

function removeBg() {
    designs[currentSide].background = '';
    $('#bgInput').val('');
    renderCanvas();
}

// ════════════════════════════════════════════════════════════════════════════
// Persistencia del estado
// ════════════════════════════════════════════════════════════════════════════
function saveCurrentToState() {
    $('#canvas').find('.cert-element').each(function () {
        var id = $(this).data('id');
        var el = designs[currentSide].elements.find(function (e) { return e.id === id; });
        if (el) {
            el.x = parseInt($(this).css('left')) || 0;
            el.y = parseInt($(this).css('top'))  || 0;
        }
    });
}

// ════════════════════════════════════════════════════════════════════════════
// Guardar diseño
// ════════════════════════════════════════════════════════════════════════════
function saveDesign() {
    saveCurrentToState();

    // Solo el frente es obligatorio
    var frontOk = designs.front.elements.length > 0 || designs.front.background;
    if (!frontOk) {
        Swal.fire({
            title: 'Atención',
            html:  'El <strong>frente</strong> debe tener al menos un elemento o imagen de fondo.',
            icon:  'warning',
        });
        return;
    }

    $('#inputDesignFront').val(JSON.stringify(designs.front));
    $('#inputDesignBack').val(JSON.stringify(designs.back));

    Swal.fire({
        title: '¿Guardar plantillas?',
        html:  'Se guardarán el frente y el dorso del certificado para el área <strong>{{ $area->nombre }}</strong>.',
        icon:  'question',
        showCancelButton:   true,
        confirmButtonColor: '#28a745',
        cancelButtonColor:  '#6c757d',
        confirmButtonText:  '<i class="fas fa-save"></i> Sí, guardar',
        cancelButtonText:   'Cancelar',
        reverseButtons:     true,
    }).then(function (r) {
        if (r.isConfirmed) {
            designModified = false;
            $('#saveForm').submit();
        }
    });
}

// ════════════════════════════════════════════════════════════════════════════
// Preview en vivo
// ════════════════════════════════════════════════════════════════════════════
function livePreview() {
    saveCurrentToState();
    var frontOk = designs.front.elements.length > 0 || designs.front.background;
    var backOk  = designs.back.elements.length  > 0 || designs.back.background;
    if (!frontOk && !backOk) {
        Swal.fire({
            title: 'Sin contenido',
            html:  'Agregá al menos un elemento al <strong>frente</strong> y al <strong>dorso</strong> antes de previsualizar.',
            icon:  'info',
        });
        return;
    }
    $('#previewInputFront').val(JSON.stringify(designs.front));
    $('#previewInputBack').val(JSON.stringify(designs.back));

    var $btn = $('#btnPreview');
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generando...');
    $('#previewForm').submit();
    setTimeout(function () {
        $btn.prop('disabled', false).html('<i class="fas fa-eye"></i> Preview');
    }, 4000);
}

// ════════════════════════════════════════════════════════════════════════════
// Confirmar eliminación del diseño guardado
// ════════════════════════════════════════════════════════════════════════════
function triggerImageUpload() { $('#imgUpload').trigger('click'); }

function confirmClearTemplate() {
    Swal.fire({
        title: '¿Eliminar el diseño guardado?',
        html:  'Se borrarán el <strong>frente</strong> y el <strong>dorso</strong> guardados en la base de datos.<br><br>Esta acción no se puede deshacer.',
        icon:  'warning',
        showCancelButton:   true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor:  '#6c757d',
        confirmButtonText:  '<i class="fas fa-trash-alt"></i> Sí, eliminar',
        cancelButtonText:   'Cancelar',
        reverseButtons:     true,
    }).then(function (r) {
        if (r.isConfirmed) $('#clearTemplateForm').submit();
    });
}

// ════════════════════════════════════════════════════════════════════════════
// Advertencia antes de salir con cambios sin guardar
// ════════════════════════════════════════════════════════════════════════════
var designModified = false;

(function () {
    var _addElement  = addElement;
    var _deleteEl    = deleteEl;
    var _applyProps  = applyProps;

    addElement = function (type, extra) { designModified = true; return _addElement(type, extra); };
    deleteEl   = function (id)          { designModified = true; return _deleteEl(id); };
    applyProps = function ()            { designModified = true; return _applyProps(); };
})();

window.addEventListener('beforeunload', function (e) {
    if (designModified) {
        e.preventDefault();
        e.returnValue = 'Tenés cambios sin guardar. ¿Seguro que querés salir?';
        return e.returnValue;
    }
});
</script>
@stop