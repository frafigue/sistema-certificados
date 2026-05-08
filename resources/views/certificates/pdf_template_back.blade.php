<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reverso del Certificado</title>

    <style>
        @page { margin: 0; }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .page {
            width: 1080px;
            height: 750px;
            position: relative;
        }

        /* ✅ FONDO OPTIMIZADO */
        .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("{{ public_path('images/background.jpg') }}");
            background-size: cover;
            background-repeat: no-repeat;
        }

        .content-container {
            position: relative;
            padding: 60px 80px;
        }

        h1 {
            text-align: center;
            font-size: 26px;
            text-transform: uppercase;
            border-bottom: 2px solid #555;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        h2 {
            font-size: 20px;
            color: #0d47a1;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-top: 25px;
        }

        p {
            font-size: 15px;
            line-height: 1.6;
            text-align: justify;
        }

        .tipo-certificado {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }

    </style>
</head>

<body>
<div class="page">

    <div class="background"></div>

    <div class="content-container">

        <h1>{{ $course->nombre ?? 'Información del Curso' }}</h1>

        <!-- ✅ MOSTRAR TIPO -->
        <div class="tipo-certificado">
            Tipo de Certificado: {{ $condition }}
        </div>

        @if($course->area)
            <h2>ÁREA: {{ $course->area->nombre }}</h2>
            <p>{{ $course->area->descripcion ?? 'Sin descripción.' }}</p>
        @endif

        <h2>OBJETIVO</h2>
        <p>{!! nl2br(e($course->objetivo ?? 'Sin objetivo definido.')) !!}</p>

        <h2>CONTENIDO</h2>
        <p>{!! nl2br(e($course->contenido ?? 'Sin contenido definido.')) !!}</p>

        <h2>FECHA DE EMISIÓN</h2>
        <p>{{ date('d/m/Y') }}</p>

    </div>
</div>
</body>
</html>