# Modelo349: contexto del equipo

Esta carpeta guarda **por qué el código es como es**: las decisiones que no se ven leyendo una sola clase, para que
todo el equipo y los agentes trabajen con el mismo contexto. Lee este índice y abre solo el tema o la decisión que
necesites.

Se puede dividir en tantos archivos y subcarpetas como haga falta; este índice dice qué hay en cada uno y se actualiza
en el mismo trabajo que cambia el código.

## Temas

- [`01-arquitectura.md`](01-arquitectura.md): piezas del plugin, qué toca del core y la estructura compartida con los demás modelos fiscales.
- [`02-calculo.md`](02-calculo.md): consulta SQL, países de la UE, empresa y año del ejercicio, solo facturas no editables.
- [`03-fichero-aeat.md`](03-fichero-aeat.md): registros tipo 1 y 2 de 500 posiciones, ISO-8859-1 y NIF del operador con prefijo VIES.
- [`04-api.md`](04-api.md): endpoint `GET /api/3/modelo349`, parámetros, respuesta y errores.
- [`05-evolucion.md`](05-evolucion.md): etapas del plugin según el historial git.

## Decisiones

- [`0001-clave-por-operacion-de-la-factura.md`](Decisiones/0001-clave-por-operacion-de-la-factura.md): por qué la clave sale del campo `operacion`.
- [`0002-empresa-y-anio-del-ejercicio.md`](Decisiones/0002-empresa-y-anio-del-ejercicio.md): por qué empresa y año se toman del ejercicio.
- [`0003-api-de-solo-lectura-e-informativa.md`](Decisiones/0003-api-de-solo-lectura-e-informativa.md): por qué la API solo lee y devuelve `resultado` nulo.
- [`0004-periodo-normalizado.md`](Decisiones/0004-periodo-normalizado.md): cómo se unifican los formatos de periodo de pantalla y API.
- [`0005-operaciones-propias-para-las-claves-de-venta.md`](Decisiones/0005-operaciones-propias-para-las-claves-de-venta.md): por qué el plugin añade operaciones al campo `operacion` para T, M, H, R y D, y por qué no la C.
