# API de ingreso de requerimientos para Dot

Dot puede crear requerimientos provenientes de correo u otras fuentes. El endpoint solo ingresa datos: deja el requerimiento en `RECEIVED`, registra `integration:dot` en el timeline y no crea tareas, ejecuta workers ni aprueba acciones.

## Credencial

En el servidor, emitir una credencial exclusiva para Dot:

```bash
php artisan orchestrator:ingestion-token dot
```

El comando muestra un token `dvo_ing_...` una sola vez. Guardarlo en el almacén de secretos de Dot y enviarlo como `Authorization: Bearer <token>` sobre HTTPS. El servidor conserva solo su hash SHA-256. Volver a ejecutar el comando rota el token e invalida el anterior; `php artisan orchestrator:ingestion-token dot --revoke` lo deshabilita. Los tokens de workers no sirven para esta API.

## Crear un requerimiento

`POST https://orquestador.jet-erp.cl/api/v1/requirements`

```bash
curl -X POST 'https://orquestador.jet-erp.cl/api/v1/requirements' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $DOT_INGESTION_TOKEN" \
  --data '{
    "source": "email",
    "external_reference": "gmail:cuenta@example.com:mensaje-123",
    "sender": "cliente@example.com",
    "subject": "Corregir exportación CSV",
    "original_content": "El CSV exportado tiene una columna incorrecta.",
    "summary": "Revisar columna del CSV",
    "project_slug": "crm-nutrisco",
    "topic_key": "gmail:cuenta@example.com:hilo-456",
    "classification_confidence": 0.82,
    "priority": "NORMAL",
    "risk": "UNKNOWN",
    "requires_approval": false,
    "received_at": "2026-09-29T12:00:00-03:00"
  }'
```

`source`, `external_reference`, `subject` y `original_content` son obligatorios. `source` es un identificador en minúsculas de hasta 64 caracteres (`email`, `asana`, `slack`, etc.). `external_reference` debe ser estable y estar cualificada por cuenta o espacio de origen para evitar colisiones; la pareja `(source, external_reference)` es única. `project_slug` es opcional: si Dot no está seguro del proyecto, debe omitirlo para dejarlo por definir. Un slug desconocido o inactivo produce `422`.

Opcionales: `sender`, `summary`, `context`, `project_slug`, `topic_key`, `classification_confidence` (0 a 1), `kind` (`DEVELOPMENT`, `PROJECT_RESPONSE` o `SYSTEM_IMPROVEMENT`), `priority` (`LOW`, `NORMAL`, `HIGH`, `URGENT`), `risk` (`UNKNOWN`, `LOW`, `MEDIUM`, `HIGH`), `requires_approval` y `received_at` (fecha ISO 8601). `PROJECT_RESPONSE` representa consultas o seguimiento de un proyecto que requieren respuesta, aunque no haya código que cambiar; Dot debe marcar `requires_approval: true` para mantener la respuesta bajo revisión humana. El contenido original y el contexto tienen un máximo de 65.536 caracteres; el resumen, 8.192. Dot debe enviar texto útil para el análisis, sin credenciales ni adjuntos binarios.

`topic_key` agrupa requerimientos del mismo tema dentro de un proyecto. Para Gmail, usa una clave estable por hilo y cuenta; no la confundas con `external_reference`, que identifica cada mensaje. Si falta, cada requerimiento tendrá su propio chat Codex.

Primera entrega: `201 Created` con `created: true`. Reintento del mismo cliente de ingesta con la misma pareja `(source, external_reference)`: `200 OK` con `created: false` y el mismo ID; no modifica el requerimiento existente ni duplica su evento, incluso si el proyecto se desactiva después. El reintento puede enviar solo `source` y `external_reference`. Si esa referencia pertenece a otro cliente o a un ingreso manual, responde `409`. La respuesta incluye `id`, `status`, `source`, `external_reference` y `url` del panel. Otros resultados: `401` sin credencial válida, `422` por validación y `429` al superar 60 solicitudes por minuto desde una IP.

Dot debe conservar el ID y tratar las respuestas `5xx` o fallos de red como reintentables con la misma referencia externa. No debe generar un identificador nuevo en cada intento. La API no expone endpoints para avanzar estados, crear tareas, ejecutar agentes o desplegar producción.

## Verificación en el panel

En **Integraciones** se muestra si la credencial de Dot está emitida, cuándo fue la última llamada autenticada y cuántos requerimientos se crearon realmente. El token nunca aparece en el panel. Emitir un token no conecta Gmail ni activa Dot por sí solo; la actividad real debe comprobarse con un requerimiento legítimo y su evento de auditoría.
