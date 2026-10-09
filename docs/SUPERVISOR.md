# Configuración de Supervisor - Workers de Cola

## 📌 ¿Qué es Supervisor?

Supervisor es un gestor de procesos que mantiene corriendo los workers de cola de Laravel. Si un worker se cae, Supervisor lo reinicia automáticamente.

## 📁 Archivo de configuración

/etc/supervisor/conf.d/laravel-worker.conf

## 📄 Contenido

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/certificados/artisan queue:work --queue=certificates,emails,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/certificados/storage/logs/worker.log
stopwaitsecs=3600

## 🎯 Colas usadas por la aplicación

| Cola | Uso | Job típico |
|---|---|---|
| certificates | Generación de certificados | GenerateCertificateJob |
| emails | Envío de emails | SendCertificateEmail |
| default | Otros trabajos | (varios) |

IMPORTANTE: Si agregás una nueva cola en el código, hay que agregarla al --queue= del Supervisor y reiniciar.

## 🔧 Comandos útiles

### Ver estado

supervisorctl status

### Reiniciar workers (después de cambios de código)

supervisorctl restart laravel-worker:*

### Ver log de workers en vivo

tail -f /var/www/certificados/storage/logs/worker.log

### Recargar configuración

supervisorctl reread
supervisorctl update

## 🔍 Diagnosticar problemas

### Ver jobs pendientes

su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan tinker --execute='echo DB::table(\"jobs\")->count();'"

### Ver jobs fallidos

su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan queue:failed"

### Reintentar un job fallido

su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan queue:retry ID"

### Limpiar jobs fallidos

su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan queue:flush"