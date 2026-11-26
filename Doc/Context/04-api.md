# API

`GET /api/3/modelo349`, de solo lectura e informativa. Se registra en `Init.php` y la atiende
`Controller/ApiModelo349.php`; la lógica está en `Lib/Modelo349Api::build()`.

## Cómo es hoy

- Otro método HTTP devuelve 405 `method-not-allowed`.
- Parámetros: `periodo` (obligatorio), `codejercicio`, `idempresa`, `anio` y `codserie`. El detalle de operadores
  se devuelve siempre: no hay parámetro `detalle` (se leía en el controlador pero nunca se usaba y se quitó).
- Periodo: `T1`-`T4` y `01`-`12`, con alias (`1T`, `3`...). El modelo no tiene periodo anual: `normalizePeriod()` ya
  no traduce `ANUAL` y sus alias, que llegan tal cual y `build()` responde `invalid-period`.
- Empresa y ejercicio (`resolveExercise()`): si llega `codejercicio` manda y la empresa es la del ejercicio. Si no,
  `idempresa` (por defecto la predeterminada) y `anio` (por defecto el actual): se toma el ejercicio de esa empresa cuya
  fecha de inicio cae en ese año.
- Errores: 400 (`missing-period`, `invalid-period`, `invalid-year`) y 404 (`serie-not-found`, `exercise-not-found`,
  `company-not-found`).
- Respuesta 200: `modelo`, `plugin_version`, `empresa`, `ejercicio`, `periodo`, fechas ISO, `parametros`, `resumen`
  (nº de operadores, totales de E, S, A e I, `totales_por_clave` con las demás claves si las hay, y totales de
  ventas y compras), `casillas`, `resultado`, `detalle`
  (`ventas` y `compras`) y `avisos` (en español).
- `casillas`: `01` = nº total de operadores, `02` = importe total de las operaciones. `resultado` es siempre `null`: el
  349 es informativo, no hay importe a ingresar.
- Permisos: lo gestiona `ApiController` del core; según lo acordado con el equipo, una clave sin acceso completo necesita
  permiso de lectura del recurso `modelo349`. No está comprobado en el código del plugin.
- La pantalla reutiliza `Modelo349Api::screenPeriod()` para aceptar periodos en formato API y descartar los inválidos.

## Por qué

Para que fs-mcp (`get_modelo_349`) lea el modelo con los mismos importes que la pantalla (`80b5df5`). Ver
[0003](Decisiones/0003-api-de-solo-lectura-e-informativa.md) y [0004](Decisiones/0004-periodo-normalizado.md).

## Al cambiarlo

- Mantén el formato de la respuesta: lo consume fs-mcp.
- Cubierta por `Test/main/Modelo349ApiTest.php`.
