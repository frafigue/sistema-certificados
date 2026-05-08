<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Resultados de la Consulta</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100vw;
            height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            overflow: hidden;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            width: 100vw;
            height: 100vh;
            max-width: 100%;
            max-height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 0;
            box-shadow: none;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            padding: 30px 20px;
            text-align: center;
            color: white;
            flex: 0 0 auto;
            position: relative;
        }

        .logo {
            font-size: 48px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .main-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1;
        }

        .subtitle {
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .content {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 20px 30px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none;
            transition: background 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 8px 25px rgba(149, 165, 166, 0.3);
            margin-bottom: 24px;
        }

        .btn-back:hover {
            background: linear-gradient(135deg, #7f8c8d, #5f6768);
            box-shadow: 0 12px 35px rgba(149, 165, 166, 0.5);
        }

        .results-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            border-left: 5px solid #3498db;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .results-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .results-header p {
            font-size: 14px;
            color: #34495e;
            line-height: 1.6;
        }

        .no-results {
            background: linear-gradient(135deg, #fee, #fdd);
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #e74c3c;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-top: 20px;
        }

        .no-results p {
            font-size: 14px;
            color: #c0392b;
            font-weight: 600;
            line-height: 1.6;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 2px solid #e0e6ed;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        thead {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        thead th {
            padding: 16px 20px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid #e0e6ed;
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody td {
            padding: 16px 20px;
            font-size: 14px;
            color: #2c3e50;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #27ae60, #229954);
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            transition: background 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
            white-space: nowrap;
        }

        .btn-download:hover {
            background: linear-gradient(135deg, #229954, #1e8449);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.5);
        }

        .content::-webkit-scrollbar {
            width: 10px;
        }

        .content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 10px;
        }

        .content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2980b9, #1c5a8b);
        }

        .table-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: #3498db;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .header {
                padding: 20px 15px;
            }

            .main-title {
                font-size: 22px;
            }

            .subtitle {
                font-size: 14px;
            }

            .content {
                padding: 15px 20px;
            }

            .results-header h2 {
                font-size: 18px;
            }

            table {
                min-width: 600px;
            }

            thead th,
            tbody td {
                padding: 12px 15px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 15px;
            }

            .btn-back {
                width: 100%;
                justify-content: center;
            }

            table {
                min-width: 500px;
            }

            thead th,
            tbody td {
                padding: 10px 12px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🎓</div>
            <h1 class="main-title">Verificación de Certificados</h1>
            <p class="subtitle">Registro Público de Certificados (UNJu)</p>
        </div>

        <div class="content">
            <a href="{{ route('consulta.home') }}" class="btn-back">
                <span>←</span> Nueva Búsqueda
            </a>

            @if ($persona && $certificados->isNotEmpty())
                <div class="results-header">
                    <h2>✓ Resultados Encontrados</h2>
                    <p><strong>Graduado:</strong> {{ $persona->nombre }} {{ $persona->apellido ?? '' }}</p>
                    <p><strong>DNI:</strong> {{ $persona->dni ?? 'N/A' }}</p>
                    <p><strong>Total de certificados:</strong> {{ $certificados->count() }}</p>
                </div>

                <div class="table-container">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>DNI</th>
                                    <th>Nombre Completo</th>
                                    <th>Curso</th>
                                    <th>Área</th>
                                    <th>Código CUV</th>
                                    <th style="text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($certificados as $certificado)
                                    <tr>
                                        <td><span class="badge">#{{ $certificado->id }}</span></td>
                                        <td>{{ $persona->dni ?? 'N/A' }}</td>
                                        <td>{{ $persona->nombre }} {{ $persona->apellido ?? '' }}</td>
                                        <td>{{ $certificado->course->nombre ?? 'N/A' }}</td>
                                        <td>{{ $certificado->course->area->nombre ?? $certificado->area_excel ?? 'N/A' }}</td>
                                        <td>{{ $certificado->unique_code ?? 'N/A' }}</td>
                                        <td style="text-align: center;">
                                            <a href="{{ route('certificados.descargar.publico', $certificado->id) }}" 
                                               target="_blank" 
                                               class="btn-download">
                                                <span>📄</span> Descargar
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            @elseif ($persona)
                <div class="results-header">
                    <h2>⚠️ Persona Encontrada</h2>
                    <p><strong>Graduado:</strong> {{ $persona->nombre }} {{ $persona->apellido ?? '' }}</p>
                    <p><strong>DNI:</strong> {{ $persona->dni ?? 'N/A' }}</p>
                </div>

                <div class="no-results">
                    <p><strong>Atención:</strong> Se encontró a la persona registrada, pero no tiene certificados asociados en el sistema.</p>
                </div>

            @else
                <div class="no-results">
                    <p><strong>❌ Sin Resultados:</strong> No se encontró ninguna persona o certificado con los datos proporcionados. Por favor, verifique los datos ingresados e intente nuevamente.</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>