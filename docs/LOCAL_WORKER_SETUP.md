# Worker local

Requisitos: Python 3.10+, Git, Codex CLI autenticado localmente y acceso HTTPS al Orchestrator. No instalar dependencias del proyecto legacy en el servidor central.

1. En el panel, crear worker, asociar únicamente proyectos permitidos y emitir token.
2. Copiar `worker/config.example.json` fuera del repositorio como `config.json`. Reemplazar servidor, UUID, `token_file` y rutas privadas. Cada proyecto requiere `environment: development` y una lista local de comandos de prueba permitidos.
3. Guardar token en archivo privado y aplicar `chmod 600 /ruta/worker.token`. No ponerlo en plist, variables de Git ni comandos registrados en historial.
4. Mapear cada slug al checkout local del proyecto Codex correspondiente. Debe ser un checkout Git de desarrollo en una rama distinta de `main`, `master`, `production` y `prod`. El worker no acepta chats guardados cuyo directorio no coincida exactamente con el proyecto mapeado.
5. Ejecutar `python3 worker/worker.py --config /ruta/privada/config.json --healthcheck` para verificar configuración, registro y heartbeat sin consultar ni ejecutar trabajos. Después ejecutarlo sin `--healthcheck`. `--once` hace un ciclo real y puede ejecutar una tarea en cola.
6. Para arranque automático en macOS, adaptar `worker/com.example.dev-orchestrator-worker.plist` con rutas reales y cargarlo con `launchctl bootstrap gui/$(id -u) /ruta/al/plist`. Mantener token y configuración fuera de Git. En Linux, usar una unidad systemd con usuario no privilegiado y las mismas rutas privadas.

El worker rechaza un slug no mapeado, proyecto fuera de desarrollo, checkout sin Git, ramas protegidas, análisis con cambios locales o comando de prueba que no aparezca en ambas listas. Mientras Codex ejecuta, sigue enviando heartbeat. Si el proceso muere durante una tarea, un administrador revisa la ejecución y el checkout antes de reintentar.

Los trabajos crean un chat Codex persistente en el directorio del proyecto y guardan su ID en el requerimiento. Los trabajos posteriores del mismo tema reanudan ese ID. Para unir varios requerimientos de un mismo asunto, asignarles la misma `topic_key` dentro del mismo proyecto; para continuar un chat que ya existía, introducir su UUID en el formulario del requerimiento. Si no hay `topic_key`, el tema queda limitado a ese requerimiento. El panel muestra un enlace directo al chat.

El análisis técnico propone si conviene abrir una rama, indica motivo y nombre. Si propone una nueva, el panel crea una aprobación `create_branch`. Solo después de aprobarla el worker puede crear o cambiar a esa rama al ejecutar una tarea de implementación. Si se rechaza, sigue en la rama actual. El worker exige checkout limpio antes de cambiar de rama y nunca crea ramas durante el análisis.
