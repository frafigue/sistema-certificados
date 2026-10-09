# Arquitectura del Sistema - Certificados UNJu

## 🎯 Descripción general

Sistema de gestión y verificación pública de certificados universitarios.

- Consulta pública de certificados (sin login)
- Verificación por código único
- Gestión interna (con login): áreas, cursos, personas, certificados
- Generación masiva de certificados
- Envío de emails con certificados adjuntos

## 👥 Roles del sistema

| Rol | ID | Descripción |
|---|---|---|
| Root | 1 | Control total del sistema |
| Administrador | 2 | Gestión de un área |
| Persona | 3 | Usuario final |
| Gestor | 4 (futuro) | Gestión de una subárea |

## 🏗️ Tablas principales

| Tabla | Descripción |
|---|---|
| users | Usuarios del sistema (con login) |
| areas | Áreas académicas |
| resolutions | Resoluciones académicas |
| courses | Cursos de cada área |
| people | Personas (alumnos, beneficiarios) |
| certificates | Certificados emitidos |
| certificate_templates | Plantillas de diseño |
| import_histories | Historial de importaciones |
| jobs | Cola de trabajos pendientes |
| job_batches | Batches de trabajos |
| failed_jobs | Trabajos fallidos |

## 🔄 Flujos principales

### Consulta pública

Usuario anónimo ingresa a /certificados/
Ingresa DNI o nombre
POST a /certificados/
Busca en tablas people y certificates
Muestra resultados

### Generación masiva

Admin ingresa a /certificados/certificate-generator
Paso 1: Genera Excel pre-completado
Paso 2: Sube Excel con notas
CertificatesSheetImport crea ImportHistory
Bus::batch(GenerateCertificateJob) dispatch
Workers procesan cada certificado
Actualiza ImportHistory con progreso
Envía emails (cola emails)

## 🔧 Configuración técnica

### Subdirectorio /certificados

.env:
APP_URL=https://sied.unju.edu.ar/certificados
ASSET_URL=https://sied.unju.edu.ar/certificados

app/Providers/AppServiceProvider.php:
URL::forceRootUrl(rtrim(config('app.url'), '/') . '/');

public/.htaccess:
RewriteBase /certificados/

routes/web.php:
Route::post('', [ConsultaPublicaController::class, 'buscarCertificados'])->name('certificados.buscar');

### Colas de trabajo

| Cola | Uso |
|---|---|
| certificates | Generación de certificados |
| emails | Envío de emails |
| default | Otros |

## 📦 Dependencias principales

### Backend

- laravel/framework: ^12.0
- barryvdh/laravel-dompdf: ^3.1
- simplesoftwareio/simple-qrcode: ^4.2
- maatwebsite/excel: ^3.1
- jeroennoten/laravel-adminlte: ^3.15

### Frontend

- vite: ^7.0.4
- tailwindcss: ^3.1.0
- alpinejs: ^3.4.2

## 🗺️ Rutas principales

| Método | Ruta | Descripción |
|---|---|---|
| GET | /certificados/ | Consulta pública |
| POST | /certificados/ | Buscar certificados |
| GET | /certificados/login | Login admin |
| GET | /certificados/home | Dashboard |
| GET | /certificados/certificates | Lista certificados |
| GET | /certificados/certificate-generator | Generación masiva |
| GET | /certificados/persons | Gestión personas |
| GET | /certificados/courses | Gestión cursos |
| GET | /certificados/areas | Gestión áreas |
| GET | /certificados/users | Gestión usuarios (solo Root) |

## 🚀 Roadmap

### En producción
- Consulta pública
- Login
- Gestión de áreas, cursos, personas
- Generación individual y masiva
- Diseños personalizables
- Diferentes tamaños de hoja

### En desarrollo (rama feature/subareas)
- Subáreas dentro de áreas
- Rol Gestor con permisos limitados
- Solicitud de credenciales por email

### Futuro
- Reportes y estadísticas
- API REST