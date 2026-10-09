# Guía de Deploy - Sistema Certificados UNJu

## 📋 Datos del servidor

- **IP:** 10.2.0.229
- **SO:** Debian 12 (bookworm)
- **Usuario SSH:** usersied
- **Usuario root:** root (sin acceso remoto)
- **URL producción:** https://sied.unju.edu.ar/certificados
- **Ruta del proyecto:** /var/www/certificados
- **Rama de producción:** main

## 🛠️ Stack tecnológico

- PHP 8.3
- Laravel 12
- MySQL/MariaDB 10.11
- Apache 2.4 + PHP-FPM 8.3
- Node.js 20 + Vite
- Supervisor (workers de cola)

## 🚀 Actualizar producción desde GitHub

### 1. Conectarse al servidor

ssh usersied@10.2.0.229
su -

### 2. Ir al proyecto y bajar cambios

cd /var/www/certificados
git pull origin main

### 3. Instalar dependencias PHP (si cambiaron)

su -s /bin/bash www-data -c "cd /var/www/certificados && composer install --no-dev --optimize-autoloader"

### 4. Instalar dependencias JS y compilar assets (si cambiaron)

su -s /bin/bash www-data -c "cd /var/www/certificados && npm install && npm run build"

### 5. Aplicar migraciones (si hay nuevas)

su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan migrate --force"

### 6. Limpiar y re-optimizar cachés

su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan optimize:clear"
su -s /bin/bash www-data -c "cd /var/www/certificados && php artisan optimize"

### 7. Reiniciar workers

supervisorctl restart laravel-worker:*

### 8. Verificar que funciona

curl -I https://sied.unju.edu.ar/certificados/

## 🆘 En caso de emergencia

### Restaurar archivos desde backup

ls -lh /root/backup_certificados_*.tar.gz
cd /var/www
tar -xzf /root/backup_certificados_FECHA.tar.gz

### Restaurar base de datos

ls -lh /root/certificados_db_*.sql
mysql -u certificados -pSIEDcerti.2025 certificados < /root/certificados_db_FECHA.sql

### Si la app no responde

tail -50 /var/www/certificados/storage/logs/laravel.log
tail -30 /var/log/apache2/error.log
tail -30 /var/www/certificados/storage/logs/worker.log
systemctl restart php8.3-fpm
systemctl reload apache2
supervisorctl restart laravel-worker:*

## 📁 Estructura de directorios clave

| Ruta | Descripción |
|---|---|
| /var/www/certificados/ | Proyecto actual |
| /var/www/certificados_viejo_20261002/ | Versión anterior |
| /etc/apache2/sites-enabled/default-ssl.conf | Config Apache |
| /etc/supervisor/conf.d/laravel-worker.conf | Config Supervisor |
| /var/www/certificados/storage/logs/laravel.log | Log de Laravel |
| /var/www/certificados/storage/logs/worker.log | Log de workers |

## 🔧 Configuración de Apache

El proyecto vive en un subdirectorio /certificados:

Alias /certificados /var/www/certificados/public
RewriteBase /certificados/

## 📚 Recursos

- Repositorio: https://github.com/frafigue/sistema-certificados
- Documentación Laravel: https://laravel.com/docs