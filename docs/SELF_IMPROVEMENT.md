# Auto-mejora controlada

El seeder registra `dev-orchestrator` como proyecto. Una oportunidad de mejora se registra como Requirement con `kind=SYSTEM_IMPROVEMENT`, fuente `agent`, `system` o manual, y sigue el mismo análisis técnico, plan, tareas, pruebas, revisión y aprobación que cualquier proyecto.

Un coordinador futuro podrá proponer mejoras y división de tareas, pero `Lifecycle` mantiene la autoridad sobre fases, workers, límites y aprobación. Los agentes pueden preparar cambios y PR en una rama de desarrollo; no hay deploy automático ni modificación directa de producción en el MVP.

Ejemplos: añadir a la ficha un esquema solicitado repetidamente, corregir clasificaciones recurrentes, mejorar prompts o UX del panel. Cada propuesta debe citar evidencia y quedar auditable antes de ejecutarse.
