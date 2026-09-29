# Despliegue posterior (Nginx)

Destino previsto: `orquestador.jet-erp.cl`. Antes de desplegar, verificar DNS, vhost, versión PHP (>=8.3), extensiones, Composer, permisos, espacio y backups del servidor. Usar una ruta nueva y un usuario de servicio; no tocar otros sitios ni configurar workers legacy en el servidor.

1. Publicar la rama `orquestador` en la ruta de la aplicación sin mezclarla con `main`.
2. `composer install --no-dev --prefer-dist --optimize-autoloader`; configurar `.env` privado (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://orquestador.jet-erp.cl`, BD y sesión) y generar `APP_KEY` allí.
3. Ejecutar `php artisan migrate --force`, `php artisan db:seed --force`, `php artisan optimize`. Crear cuenta con `php artisan orchestrator:create-admin Jaime.fuentes@tecnich.cl` desde una consola interactiva; no enviar contraseña por chat.
4. Configurar Nginx con `root .../public`, `try_files $uri $uri/ /index.php?$query_string`, PHP-FPM y TLS. Probar `nginx -t` antes de recargar.
5. Programar `php artisan schedule:run` cada minuto. Comprobar `/up`, login, assets, migraciones y logs. Mantener backups y reversión de la versión anterior.

El servidor central no ejecuta Codex sobre proyectos legacy ni tiene sus credenciales. El despliegue de este propio Orchestrator es una operación humana separada de la API de workers.
