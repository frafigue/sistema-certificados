# Bugs conocidos y soluciones

Este documento registra los bugs detectados y resueltos durante el desarrollo y puesta en producción del sistema.

---

## Bug 1: Loop de redirecciones en `/certificados/login`

**Fecha:** 2026-10-15
**Severidad:** Alta (sitio inaccesible)
**Estado:** Resuelto

### Síntoma
Al acceder a `https://sied.unju.edu.ar/certificados/login` se mostraba:
Internal Server Error
The server encountered an internal error or misconfiguration

text
En el log de Apache aparecía:
AH00124: Request exceeded the limit of 10 internal redirects

text

### Causa
El archivo `.htaccess` había sido revertido a la versión original, que carecía de la directiva `RewriteBase /certificados/`. Además, la regla de "Redirect Trailing Slashes" estaba causando el loop. Sin `RewriteBase`, Apache no sabía que la app vive en una subcarpeta.

### Solución
Modificar `/var/www/certificados/public/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On
    RewriteBase /certificados/

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
Después: systemctl reload apache2

Verificación
bash
curl -I https://sied.unju.edu.ar/certificados/login
# Debe devolver: HTTP/1.1 200 OK
Bug 2: Botón BUSCAR no hacía nada
Fecha: 2026-10-15
Severidad: Alta (funcionalidad pública inutilizable)
Estado: Resuelto

Síntoma
En la página pública, al ingresar un DNI y hacer clic en "BUSCAR", no pasaba nada. La página se recargaba pero no mostraba resultados.

En Network se veía:

text
certificados    301    document
certificados/   200    document
Causa
El formulario apuntaba a /certificados (sin slash final). Apache redirigía con un 301 a /certificados/, y al seguir el redirect el navegador convertía el POST en GET. Laravel recibía un GET y solo mostraba el formulario otra vez.

Solución
Modificar /var/www/certificados/resources/views/consulta/formulario.blade.php, línea 259:

blade
<!-- ANTES -->
<form method="POST" action="{{ route('certificados.buscar') }}">

<!-- DESPUÉS -->
<form method="POST" action="{{ route('certificados.buscar') }}/">
Después:

bash
su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan view:clear"
su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan view:cache"
Verificación
Ir a https://sied.unju.edu.ar/certificados/.

Ingresar un DNI real.

Hacer clic en BUSCAR.

Debe mostrar resultados.

En Network, ahora debe verse una sola petición POST a /certificados/.

Bug 3: Error al generar Excel masivo (batch_id no encontrado)
Fecha: 2026-10-05
Severidad: Alta (generación masiva inutilizable)
Estado: Resuelto

Síntoma
Al hacer clic en "Generar Excel", aparecía:

text
SQLSTATE[HY000]: General error: 1364 Field 'batch_id' doesn't have a default value
Causa
El método collection() de CertificatesSheetImport.php creaba un ImportHistory sin enviar batch_id. La columna batch_id en import_histories es NOT NULL.

Solución
Modificar /var/www/certificados/app/Imports/Sheets/CertificatesSheetImport.php:

Agregar antes del create:

php
$this->batchId = (string) \Illuminate\Support\Str::uuid();

$importHistory = ImportHistory::create([
    'user_id'       => Auth::id(),
    'batch_id'      => $this->batchId,
    'file_name'     => null,
    // ...
]);
Eliminar el update posterior de batch_id.

Verificación
Ir a /certificados/certificate-generator.

Subir un Excel.

No debe aparecer el error de batch_id.

Bug 4: URLs generadas sin subcarpeta
Fecha: 2026-10-05
Severidad: Media (afecta recursos y enlaces)
Estado: Resuelto

Síntoma
Los assets (CSS, JS) y algunos enlaces internos apuntaban a /build/... o /login (sin /certificados). Daba 404.

Causa
Laravel generaba URLs basándose en APP_URL pero no las ajustaba para el subdirectorio.

Solución
Modificar /var/www/certificados/app/Providers/AppServiceProvider.php:

php
use Illuminate\Support\Facades\URL;

public function boot(): void
{
    if (config('app.env') === 'production') {
        URL::forceScheme('https');
    }
    URL::forceRootUrl(rtrim(config('app.url'), '/') . '/');
    // ...
}
Y en .env:

env
APP_URL=https://sied.unju.edu.ar/certificados
ASSET_URL=https://sied.unju.edu.ar/certificados
Verificación
bash
su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan tinker --execute='echo route(\"login\").PHP_EOL;'"
# Debe devolver: https://sied.unju.edu.ar/certificados/login
Bug 5: Worker de cola no procesaba jobs de certificates
Fecha: 2026-10-15
Severidad: Media (jobs de generación no se procesaban)
Estado: Resuelto

Síntoma
Los jobs de generación de certificados quedaban en la tabla jobs sin procesarse.

Causa
El archivo /etc/supervisor/conf.d/laravel-worker.conf tenía configurado el worker para escuchar solo las colas emails,default, pero el sistema usa también certificates.

Solución
Modificar /etc/supervisor/conf.d/laravel-worker.conf:

ini
command=php /var/www/certificados/artisan queue:work --queue=certificates,emails,default --sleep=3 --tries=3 --max-time=3600
Después:

bash
supervisorctl reread
supervisorctl update
supervisorctl restart laravel-worker:*
Verificación
bash
supervisorctl status
# Debe mostrar 2 workers RUNNING