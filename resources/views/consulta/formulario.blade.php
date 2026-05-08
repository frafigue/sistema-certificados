<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Verificación de Certificados</title>
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
            overflow: hidden; /* quitar scroll */
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
            border-radius: 0; /* sin border radius para que ocupe todo */
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
            overflow-y: auto; /* Scroll solo dentro de content si fuera necesario */
            padding: 20px 30px;
        }

        .description {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            max-height: 30vh;
            overflow-y: auto;
        }

        .description p {
            font-size: 14px;
            line-height: 1.6;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .description ul {
            margin-left: 20px;
            color: #34495e;
            font-size: 14px;
        }

        .description li {
            margin-bottom: 6px;
        }

        .search-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
            position: relative;
        }

        form {
            max-width: 500px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            outline: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.5);
        }

        .form-group input::placeholder {
            color: #95a5a6;
            font-style: italic;
        }

        .btn-container {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.3s ease, box-shadow 0.3s ease;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #1c5a8b);
            box-shadow: 0 12px 35px rgba(52, 152, 219, 0.5);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            box-shadow: 0 8px 25px rgba(149, 165, 166, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #7f8c8d, #5f6768);
            box-shadow: 0 12px 35px rgba(149, 165, 166, 0.5);
        }

        .icon {
            font-size: 16px;
        }

        @media (max-width: 600px) {
            .header {
                padding: 20px 15px;
            }

            .main-title {
                font-size: 22px;
            }

            .content {
                padding: 15px 20px;
            }

            form {
                width: 100%;
                padding: 0 10px;
            }

            .btn-container {
                flex-direction: column;
                gap: 12px;
            }

            .btn {
                width: 100%;
                padding: 15px;
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
            <div class="description">
                <p><strong>Aquí usted encontrará a los graduados universitarios cuyos diplomas y certificados han sido intervenidos a partir del año 2012 y los títulos extranjeros convalidados a partir del año 2010.</strong></p>
                <p>Los casos previos a las fechas mencionadas que deseen incorporarse o modificar datos obrantes en este Registro Público y Único, deberán comunicarse:</p>
                <ul>
                    <li><strong>con su institución universitaria</strong>, los graduados;</li>
                    <li><strong>con la Dirección Nacional de Gestión Universitaria (DNGU)</strong>, los convalidados.</li>
                </ul>
            </div>

            <h2 class="search-title">Ingrese su DNI o su Nombre y Apellido para iniciar la búsqueda</h2>

            <form method="POST" action="{{ route('certificados.buscar') }}">
                @csrf
                <div class="form-group">
                    <label for="dni">DNI (Opcional)</label>
                    <input
                        type="text"
                        id="dni"
                        name="dni"
                        placeholder="Ingrese su número de DNI"
                        value="{{ old('dni') }}"
                    />
                </div>

                <div class="form-group">
                    <label for="nombre_apellido">Apellido y Nombre (Opcional)</label>
                    <input
                        type="text"
                        id="nombre_apellido"
                        name="nombre_apellido"
                        placeholder="Ingrese su nombre y apellido completo"
                        value="{{ old('nombre_apellido') }}"
                    />
                </div>

                <div class="btn-container">
                    <button type="submit" class="btn btn-primary">
                        <span class="icon">🔍</span>Buscar
                    </button>
                    <a href="{{ route('login') }}" class="btn btn-secondary">
                        <span class="icon">👤</span>Acceder Admin
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
