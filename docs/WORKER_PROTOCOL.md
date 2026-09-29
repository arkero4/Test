# Protocolo del worker (v1)

Todas las rutas tienen prefijo `/api`, usan HTTPS y `Authorization: Bearer <token del worker>`. Cada token corresponde a un único worker y se emite desde el panel. El servidor conserva solo su hash. Los cuerpos y respuestas son JSON.

| Método | Ruta | Propósito |
| --- | --- | --- |
| POST | `/workers/register` | UUID, versión y entorno básico; verifica identidad y registra contacto |
| POST | `/workers/heartbeat` | Actualiza contacto y estado |
| GET | `/workers/jobs/next` | Devuelve metadatos de la siguiente tarea elegible o `job: null` |
| POST | `/jobs/{task}/accept` | Aceptación transaccional; responde ID de ejecución, prompt, slug, sandbox y comandos de prueba |
| POST | `/jobs/{execution}/progress` | Mensaje breve para el timeline |
| POST | `/jobs/{execution}/logs` | Hasta 50 entradas `debug|info|warning|error` |
| POST | `/jobs/{execution}/complete` | Resultado exitoso |
| POST | `/jobs/{execution}/fail` | Resultado fallido |
| POST | `/jobs/{execution}/approval` | Solicita decisión humana y detiene avance |

`register`: `{ "uuid": "...", "agent_version": "reference-1", "environment": { "platform": "darwin" } }`. `accept` devuelve `sandbox=read-only` para análisis técnico y `workspace-write` para las demás tareas. La ruta `/jobs/{task}/accept` usa ID de tarea; las demás usan ID de ejecución. Un segundo intento de aceptar una tarea ya tomada responde 422. Un token ausente/inválido responde 401. No se envía ninguna ruta física al servidor.

`complete` / `fail` reciben `summary` y opcionalmente `stdout`, `stderr`, `modified_files`, `branch`, `commit`, `tests`, `result` y `error`. El diagnóstico técnico estructurado va en `result`. El servidor rechaza el avance exitoso de un análisis que reporta archivos modificados. Los logs y campos textuales sensibles se redactan antes de guardar y tienen límite de tamaño.

El worker debe mantener heartbeat durante trabajos largos. `next` es una consulta, y solo `accept` reclama el trabajo. El servidor vuelve a comprobar proyecto permitido, fase, dependencias, aprobación pendiente, capacidad del worker, heartbeat, límite global y paralelismo durante la aceptación.
