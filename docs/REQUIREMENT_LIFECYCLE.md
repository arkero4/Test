# Ciclo de un requerimiento

Flujo principal: `RECEIVED → ANALYZING → CLASSIFIED → TECHNICAL_ANALYSIS → PLANNING → QUEUED → ASSIGNED → IMPLEMENTING → TESTING → REVIEWING → [WAITING_APPROVAL] → COMPLETED`.

También existen `WAITING_INFORMATION`, `FAILED` y `CANCELLED`. Las transiciones válidas están centralizadas en `App\Services\Lifecycle::NEXT`. La clasificación exige proyecto, planificación exige diagnóstico, encolado exige plan y tarea de implementación, y cierre exige todas las tareas terminadas y aprobación si aplica. Las tareas pasan por `DRAFT → QUEUED → RUNNING → COMPLETED|FAILED`, con reintento desde `FAILED` que crea una ejecución nueva.

| Tipo de tarea | Fase de ejecución | Regla |
| --- | --- | --- |
| `technical_analysis` | `TECHNICAL_ANALYSIS` | Solo lectura, diagnóstico estructurado |
| `implementation` | `QUEUED` / `IMPLEMENTING` | Worker autorizado; rama de desarrollo |
| `testing` | `TESTING` | Comandos de prueba en lista doble de permitidos |
| `review` | `REVIEWING` | Evidencia y resultado registrados |

El panel permite avanzar fases de forma controlada. El primer worker que toma implementación mueve `QUEUED → ASSIGNED → IMPLEMENTING`. Al terminar todas las implementaciones, el sistema entra a `TESTING`. La decisión de pasar a `REVIEWING` y cerrar queda visible en el panel. Un fallo lleva a `FAILED`; luego se puede volver a planificación o cola según la transición permitida. Toda transición y acción importante agrega un `requirement_event`.

Una tarea marcada `requires_approval` crea una solicitud al terminar. La solicitud queda `PENDING` hasta decisión de un usuario autenticado. Aprobar permite avanzar; rechazar marca fallo. Una aprobación no ejecuta un deploy: es solo la autorización registrada para un proceso humano posterior.
