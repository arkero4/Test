# Worker local

Requisitos: Python 3.10+, Git, Codex CLI autenticado localmente y acceso HTTPS al Orchestrator. No instalar dependencias del proyecto legacy en el servidor central.

1. En el panel, crear worker, asociar únicamente proyectos permitidos y emitir token.
2. Copiar `worker/config.example.json` fuera del repositorio como `config.json`. Reemplazar servidor, UUID, `token_file` y rutas privadas. Cada proyecto requiere `environment: development` y una lista local de comandos de prueba permitidos.
3. Guardar token en archivo privado y aplicar `chmod 600 /ruta/worker.token`. No ponerlo en plist, variables de Git ni comandos registrados en historial.
4. Preparar un checkout Git de desarrollo en una rama distinta de `main`, `master`, `production` y `prod`.
5. Ejecutar `python3 worker/worker.py --config /ruta/privada/config.json --once` para verificar registro y heartbeat. Después ejecutarlo sin `--once`.
6. Para arranque automático en macOS, adaptar `worker/com.example.dev-orchestrator-worker.plist` con rutas reales y cargarlo con `launchctl bootstrap gui/$(id -u) /ruta/al/plist`. Mantener token y configuración fuera de Git. En Linux, usar una unidad systemd con usuario no privilegiado y las mismas rutas privadas.

El worker rechaza un slug no mapeado, proyecto fuera de desarrollo, checkout sin Git, ramas protegidas, análisis con cambios locales o comando de prueba que no aparezca en ambas listas. Mientras Codex ejecuta, sigue enviando heartbeat. Si el proceso muere durante una tarea, un administrador revisa la ejecución y el checkout antes de reintentar.
