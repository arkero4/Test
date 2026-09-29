# Seguridad

- Panel con autenticación de sesión Laravel, CSRF y limitación de intentos de login; sin registro público. Crear administrador solo por Artisan.
- Cada worker tiene UUID y token independiente de 256 bits, visible una vez. La base almacena únicamente SHA-256. Rotar token invalida el anterior; deshabilitar worker lo revoca.
- Un worker solo recibe trabajos de proyectos asociados en `worker_project`. La aceptación comprueba de nuevo el permiso en una transacción.
- Las rutas reales, credenciales legacy y bases de datos quedan en el worker. Configuración local y token fuera de Git, con permisos restrictivos.
- `technical_analysis` usa `codex exec --sandbox read-only`. El worker exige checkout limpio y verifica que siga limpio; el servidor rechaza reportes de archivos modificados.
- Para implementación, el worker exige un checkout de desarrollo y una rama diferente de `main`, `master`, `production` o `prod`. Comandos de prueba requieren coincidencia exacta con la lista local y la lista del servidor. No se usa shell para ejecutarlos.
- No hay endpoint ni comando del worker para deploy, merge productivo, migración productiva o borrado de datos. Esas acciones requieren un flujo humano separado.
- Campos de entrada y logs aplican redacción básica de tokens, contraseñas y Bearer. Esta redacción es defensa adicional: nunca se deben introducir secretos en requerimientos, contexto ni resultados. No es un detector perfecto de secretos.
- Revisar HTTPS, `APP_DEBUG=false`, backups, permisos de archivos, rotación de tokens y monitoreo antes de exponer públicamente el servidor.

El MVP no proporciona aislamiento de contenedor por tarea ni aprobación interactiva de cada comando generado por Codex. El operador del worker debe mapear únicamente checkouts de desarrollo sin acceso a producción. La cancelación de un trabajo en curso se transmite por heartbeat (hasta 20 segundos con la configuración de referencia). Tampoco recupera automáticamente ejecuciones interrumpidas: requieren revisión y reintento humano.
