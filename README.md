# Dev Orchestrator

Centro de operaciones para requerimientos, proyectos, workers y agentes de desarrollo. El servidor coordina y audita; los proyectos legacy y sus dependencias permanecen en máquinas locales autorizadas.

## Estado del MVP

Laravel 13, PHP 8.3+, autenticación de panel, CRUD de proyectos y workers, memoria técnica por proyecto, requerimientos, planes, subtareas, ejecuciones, timeline, aprobaciones, API de workers y worker Python de referencia. El dashboard se actualiza cada 20 segundos. Gmail, Asana, Dot, memoria vectorial y despliegues automáticos quedan fuera de esta versión.

## Inicio local

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurar DB_CONNECTION=sqlite y crear database/database.sqlite, o configurar otra BD
php artisan migrate --force
php artisan db:seed
php artisan orchestrator:create-admin admin@example.test
php artisan serve
```

El comando de administrador pide una contraseña de al menos 12 caracteres por consola. No hay registro público. Para tests: `php artisan test`. Para mantener estados de workers: `php artisan schedule:work` o cron con `schedule:run` cada minuto. `QUEUE_CONNECTION=database` habilita la cola Laravel, aunque la cola de trabajo del worker es la tabla `tasks` con estado `QUEUED`.

## Primer flujo

1. Crear un worker y autorizarle proyectos en el panel. Emitir su token, visible una sola vez.
2. Configurar el mapa privado `slug → ruta local` en la máquina worker (`worker/config.example.json`).
3. Crear un requerimiento, asignar proyecto y avanzar `RECEIVED → ANALYZING → CLASSIFIED → TECHNICAL_ANALYSIS`.
4. Crear y encolar una tarea `technical_analysis`. El worker ejecuta Codex en modo de solo lectura y devuelve diagnóstico.
5. Crear un plan y tareas; pasar a `QUEUED`. El worker toma implementaciones autorizadas. Después de la ejecución, el flujo continúa por `TESTING`, `REVIEWING` y aprobación cuando corresponda.

## Documentación

- [Arquitectura](docs/ARCHITECTURE.md)
- [Protocolo worker](docs/WORKER_PROTOCOL.md)
- [Agentes](docs/AGENT_ARCHITECTURE.md)
- [Seguridad](docs/SECURITY.md)
- [Ciclo del requerimiento](docs/REQUIREMENT_LIFECYCLE.md)
- [Configuración worker local](docs/LOCAL_WORKER_SETUP.md)
- [Memoria de proyecto](docs/PROJECT_CONTEXT.md)
- [Mejora del sistema](docs/SELF_IMPROVEMENT.md)
- [Despliegue](docs/DEPLOYMENT.md)

## Seguridad

Nunca guardar `.env`, tokens, contraseñas, rutas locales ni credenciales reales en Git. El servidor solo conoce slugs y workers autorizados. El token de cada worker se almacena como hash SHA-256 y puede rotarse o revocarse. La producción no es un destino de ejecución del worker.
