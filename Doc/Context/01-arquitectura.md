# Arquitectura

Plugin con una pantalla y un endpoint de solo lectura para el modelo 349 (declaración recapitulativa de operaciones
intracomunitarias). Sin tablas ni modelos propios: lee las facturas del core. No declara dependencias (`facturascripts.ini`
solo tiene `min_version = 2026.72`).

## Cómo es hoy

| Pieza | Qué hace |
|---|---|
| `Controller/Modelo349.php` | Pantalla (menú Informes > Fiscalidad España). Lee el formulario, llama al cálculo y descarga el TXT. |
| `View/Modelo349.html.twig` | Formulario (ejercicio, periodo, serie, tipo de declaración, justificantes) y tablas de ventas y compras. |
| `Controller/ApiModelo349.php` | Endpoint `GET /api/3/modelo349`. Solo valida el método y delega en `Lib/Modelo349Api`. |
| `Init.php` | Registra la ruta de la API, el recurso `modelo349` en `ApiRoot` y las operaciones de venta del plugin (`Clave349::register()`). |
| `Lib/Modelo349.php` | Cálculo: operadores por empresa, ejercicio y periodo. Compartido por pantalla y API. |
| `Lib/Clave349.php` | Clave de operación según el campo `operacion` de la factura: E, S, A e I con las operaciones del core, y T, M, H, R y D con las que añade el plugin. |
| `Lib/Modelo349File.php` | Genera el TXT de la AEAT (`generate()`). |
| `Lib/Modelo349Api.php` | Construye la respuesta JSON: resolución de empresa y ejercicio, periodo, casillas y avisos. |
| `Test/main/` | Tests de `Clave349`, `Modelo349File` y `Modelo349Api`. |

Las clases se enlazan siempre por `FacturaScripts\Dinamic\...` para respetar las extensiones de otros plugins.

## Por qué

Los plugins fiscales Modelo111 a Modelo390 comparten la misma estructura (`Lib/ModeloNNN`, `ModeloNNNApi`,
`ModeloNNNFile`) para que fs-mcp (`get_modelo_349`) los consuma igual (`80b5df5`). Hasta ese commit el cálculo y el
TXT vivían en el controlador y en `Txt349Export`.

## Al cambiarlo

- Un cambio de cálculo afecta a pantalla, TXT y API a la vez: se prueba en los tres.
- Si renombras clases, recuerda que fs-mcp depende de la forma de la respuesta de la API.
