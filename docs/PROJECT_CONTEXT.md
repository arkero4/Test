# Contexto técnico por proyecto

Cada proyecto puede tener entradas `project_contexts` con tipo, título, contenido, versión y timestamps. Tipos iniciales: arquitectura, módulo, convención, comando, restricción, error conocido, integración, decisión, instrucción para agentes y documentación.

Al aceptar una tarea, `Lifecycle` compone un snapshot con instrucciones, restricciones y entradas de contexto del proyecto y lo guarda en `executions.prompt`. Así una ejecución puede reconstruirse aun si la ficha cambia después. El contexto no contiene rutas físicas del worker, tokens ni secretos.

La tabla separada permite indexar entradas por proyecto/tipo y añadir más adelante embeddings o búsqueda semántica sin alterar las relaciones principales. El MVP envía todas las entradas del proyecto; antes de escalar a fichas grandes, se deberá seleccionar un subconjunto relevante y limitar tamaño de prompt.
