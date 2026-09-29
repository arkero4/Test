# Arquitectura del MVP

## Límites

- **Panel Laravel**: usuarios autenticados gestionan proyectos, workers, requerimientos, planes, tareas y aprobaciones.
- **Orquestador**: `Lifecycle` decide transiciones, elegibilidad, límites y auditoría; no ejecuta código de proyectos.
- **Worker local**: consulta trabajos por HTTPS, resuelve slug a ruta privada, ejecuta Codex CLI con sandbox y reporta resultados.
- **Proyecto**: ficha técnica central sin ruta física. La ruta, bases de datos y credenciales legacy quedan en la máquina local.

## Datos

`users`, `projects`, `project_contexts`, `workers`, `worker_project`, `requirements`, `plans`, `tasks`, `task_dependencies`, `executions`, `execution_logs`, `requirement_events`, `approvals`, `agent_runs`. Se reutilizan `jobs`, `job_batches`, `failed_jobs`, `cache` y `sessions` del esqueleto Laravel.

Relaciones: `Requirement → Plans → Tasks → Executions`; una tarea puede tener subtareas, dependencias y reintentos. `worker_project` es la lista de proyectos permitidos. `requirement_events` conserva actor, estado anterior y nuevo, IDs relacionados y momento. No se borra historial de ejecución desde el panel.

## Flujo

1. Usuario registra y clasifica un requerimiento.
2. Durante `TECHNICAL_ANALYSIS`, crea tarea de solo análisis y la encola.
3. Worker autorizado hace heartbeat, consulta y acepta. La aceptación es transaccional y crea una ejecución.
4. El resultado estructurado se guarda como diagnóstico y el requerimiento pasa a `PLANNING`.
5. Usuario registra plan y tareas. Los tipos de tarea se ejecutan en su fase: implementación, pruebas y revisión.
6. Cada resultado y transición genera eventos; las aprobaciones humanas son explícitas.

## Operación

La tabla `tasks` es la cola persistente que consultan workers. Laravel Scheduler marca workers sin heartbeat como `OFFLINE`. El límite global inicial es `ORCHESTRATOR_MAX_ACTIVE=4`, y cada worker procesa una ejecución a la vez. En un mismo proyecto solo se permite paralelismo si todas las tareas implicadas lo permiten. La pantalla usa actualización por recarga cada 20 segundos. No hay WebSockets en el MVP.

## Límites actuales

El worker de referencia y Codex local son la única implementación operativa. No hay clasificación IA automática, detector de costes, aprovisionamiento de workers, recuperación automática de ejecuciones huérfanas, PR automático ni implementación de conectores Gmail/Asana/Dot. Estas extensiones quedan detrás de interfaces, no en el camino crítico del MVP.
