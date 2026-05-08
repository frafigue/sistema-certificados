<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Certificado (Frente)</title>

    <style>
        @page { margin: 0; }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
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

        .content-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            text-align: center;
        }

        .element {
            position: absolute;
            width: 90%;
            left: 5%;
            text-align: center;
        }

        .course-title {
            top: 100px;
            font-size: 32px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .person-name-container {
            top: 240px;
            background-color: #3498db;
            color: white;
            padding: 15px 0;
            width: 80%;
            left: 10%;
        }

        .person-name {
            font-size: 36px;
            font-weight: bold;
            margin: 0;
        }

        .main-text {
            top: 360px;
            font-size: 18px;
            line-height: 1.5;
            color: #333;
        }

        .certificate-type {
            top: 480px;
            font-size: 24px;
            font-weight: bold;
            color: #222;
        }

        .footer-element {
            position: absolute;
            bottom: 70px;
            width: 33.33%;
            text-align: center;
        }

        .signature-image {
            max-height: 50px;
            margin-bottom: 5px;
        }

        .signature-text {
            font-size: 12px;
            margin: 0;
        }

        .qr-image {
            width: 110px;
            height: 110px;
        }

    </style>
</head>

<body>
<div class="page">

    <div class="background"></div>

    <div class="content-overlay">

        <div class="element course-title">
            {{ strtoupper($course->nombre ?? '') }}
        </div>

        <div class="element person-name-container">
            <h1 class="person-name">
                {{ $person->nombre ?? '' }} {{ $person->apellido ?? '' }}
            </h1>
        </div>

        <div class="element main-text">
            <p>
                Por haber participado en el curso
                <strong>"{{ $course->nombre ?? '' }}"</strong>,
                correspondiente al área {{ $course->area->nombre ?? '' }},
                con una duración de {{ $course->horas ?? 'N/A' }} horas,
                se le otorga el presente certificado.
            </p>
        </div>

        <!-- ✅ USAMOS CONDITION -->
        <div class="element certificate-type">
            <h2>Certificado de {{ $condition }}</h2>
        </div>

        <!-- FIRMA IZQUIERDA -->
        <div class="footer-element" style="left: 0;">
            @if($course->signature1_path)
                <img src="{{ storage_path('app/public/' . $course->signature1_path) }}" class="signature-image">
                <p class="signature-text">{{ $course->capacitador_nombre ?? '' }}</p>
            @endif
        </div>

        <!-- QR -->
        <div class="footer-element" style="left: 33.33%;">
            <img src="{{ $qr_path }}" class="qr-image">
        </div>

        <!-- FIRMA DERECHA -->
        <div class="footer-element" style="right: 0;">
            @if($course->signature2_path)
                <img src="{{ storage_path('app/public/' . $course->signature2_path) }}" class="signature-image">
                <p class="signature-text">{{ $course->coordinador_nombre ?? '' }}</p>
            @endif
        </div>

    </div>
</div>
</body>
</html>