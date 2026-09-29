# Arquitectura de agentes

`DevelopmentAgent` define identificador y política de sandbox por tarea. `CodexLocalAgent` es el adaptador inicial; el proceso Codex corre en el worker, nunca en el servidor Laravel. El campo `required_agent` de la tarea y `agent` de la ejecución preservan la elección. Futuras implementaciones pueden añadir Codex Cloud u otro agente sin cambiar el modelo Requirement → Task → Execution.

`CoordinatorAgent` define un punto de extensión para propuestas de plan. En el MVP el coordinador es humano mediante el panel: lee el diagnóstico, crea planes y tareas y decide secuencia/paralelismo. Dot u OpenAI Agents podrán proponer, pero sus propuestas pasarán por `Lifecycle` y las políticas del worker. No podrán crear ejecuciones directamente ni saltar aprobaciones.

Una ejecución registra un `agent_run` con inicio, término y estado; el uso/costo se puede completar cuando el agente lo reporte. El orquestador no asume que todos los agentes tengan el mismo formato de salida o capacidad de acceso.
