# Prompt maestro para Codex

Construye sobre este repositorio un **Dev Orchestrator** en Laravel moderno. Debe ser el centro de control de requerimientos de desarrollo para múltiples proyectos, incluidos proyectos legacy que se ejecutan exclusivamente en máquinas locales.

## Principios
- El orquestador NO ejecuta directamente proyectos legacy.
- Los workers locales consultan el orquestador, toman trabajos autorizados y ejecutan Codex en la carpeta local correspondiente.
- Código legacy, bases de datos, credenciales y dependencias sensibles pueden permanecer exclusivamente en la máquina local.
- Ningún worker puede desplegar a producción ni ejecutar acciones destructivas sin aprobación humana explícita.
- Toda acción debe ser auditable.

## MVP
Implementa:

1. Autenticación del panel.
2. CRUD de proyectos:
   - nombre
   - slug
   - descripción
   - tipo/stack
   - estado
   - worker preferido
   - ruta lógica del proyecto (sin exigir ruta local al servidor)
3. CRUD de workers/máquinas:
   - nombre
   - identificador único
   - estado online/offline
   - capacidades
   - heartbeat
   - último contacto
4. Requerimientos:
   - fuente
   - referencia externa
   - asunto
   - descripción/contexto
   - proyecto
   - prioridad
   - estado
   - requiere aprobación
5. Ejecuciones:
   - requerimiento
   - worker
   - timestamps
   - estado
   - resumen
   - logs
   - archivos/cambios reportados
   - resultado de pruebas
6. API autenticada para workers:
   - registrar/heartbeat
   - pedir siguiente trabajo
   - aceptar trabajo
   - reportar progreso
   - finalizar/fallar trabajo
7. Estados claros: pending, classified, queued, assigned, running, waiting_approval, completed, failed, cancelled.
8. Dashboard del monitor:
   - requerimientos recientes
   - pendientes
   - ejecutándose
   - esperando aprobación
   - fallidos
   - workers online/offline
9. Timeline/auditoría de eventos.
10. Preparar puntos de integración futuros para Gmail, Asana y agentes externos.

## Worker local
Define el contrato de API y documenta un worker de referencia que pueda:
- autenticarse con token propio;
- mantener heartbeat;
- obtener un trabajo;
- mapear project_slug a una ruta LOCAL configurada solo en la máquina;
- ejecutar Codex CLI de forma controlada;
- capturar stdout/stderr;
- ejecutar comandos de prueba permitidos;
- devolver resultados al orquestador.

No guardes rutas locales, passwords, tokens, .env reales ni credenciales en Git.

## Forma de trabajo
Primero inspecciona el repositorio. Después escribe un plan breve y arquitectura propuesta. Luego implementa el MVP por etapas pequeñas y verificables. Crea migraciones, modelos, servicios, controladores, políticas/autorización, tests y documentación. Evita sobreingeniería.

Antes de cualquier acción destructiva, cambio de producción o decisión de seguridad irreversible, detente y solicita aprobación.
