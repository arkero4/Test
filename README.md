# Dev Orchestrator

Centro de control para recibir, clasificar, asignar, ejecutar y auditar requerimientos de desarrollo.

## Objetivo
Centralizar requerimientos provenientes de correo, Asana, agentes u otras fuentes y delegar su ejecución a workers autorizados. Los proyectos legacy, sus bases de datos y credenciales pueden permanecer en máquinas locales.

## Flujo inicial
1. Ingesta del requerimiento.
2. Clasificación por proyecto y prioridad.
3. Creación de una tarea auditable.
4. Asignación a un worker autorizado.
5. El worker ejecuta Codex en el directorio local del proyecto.
6. Se ejecutan validaciones/pruebas locales.
7. El worker devuelve estado, resumen, logs y cambios.
8. Acciones sensibles (producción/deploy) requieren aprobación humana.

## Seguridad
- Nunca almacenar secretos reales en Git.
- No dar acceso directo del agente a producción por defecto.
- Separar orquestación, ejecución local y aprobación.
- Mantener trazabilidad completa de cada ejecución.
