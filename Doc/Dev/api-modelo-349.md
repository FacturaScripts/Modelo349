El plugin Modelo349 añade a la API REST un endpoint de **solo lectura** con los datos del modelo 349: `GET /api/3/modelo349`. Devuelve los operadores intracomunitarios de una empresa en un periodo, con los mismos importes que la pantalla del plugin, junto con los totales por clave, las casillas del resumen y los avisos. No genera el fichero de la AEAT ni crea, modifica o borra ningún registro.

## 1. Autenticación y permisos

Como el resto de endpoints de la API, se autentica con una clave API en la cabecera `Token`. Una clave con acceso completo puede llamarlo sin más. Una clave con acceso limitado necesita el permiso de lectura del recurso `modelo349`.

El plugin registra la ruta y el recurso en su clase `FacturaScripts\Plugins\Modelo349\Init`, con `Kernel::addRoute('/api/3/modelo349', 'ApiModelo349', -1)` y `ApiRoot::addCustomResource('modelo349')`, por lo que el recurso aparece también en el listado de la raíz de la API.

## 2. Parámetros

Se pasan en la query string:

| Parámetro | Obligatorio | Qué indica |
|---|---|---|
| `periodo` | Sí | Trimestre `T1` a `T4` (también `1T` a `4T`) o mes `01` a `12` (también `1` a `12`) |
| `codejercicio` | No | Ejercicio a declarar. Si se indica, manda sobre `idempresa` y `anio`: la empresa es la del ejercicio |
| `idempresa` | No | Empresa declarante. Por defecto, la empresa predeterminada |
| `anio` | No | Año natural, de cuatro cifras, del ejercicio de esa empresa cuya fecha de inicio cae en ese año. Por defecto, el año actual |
| `codserie` | No | Serie de facturas. Vacío para todas |

El código de un ejercicio no tiene por qué coincidir con su año (`0001`, `AL25`...), por eso el año se busca por la fecha de inicio del ejercicio de la empresa.

## 3. Ejemplo

```bash
curl -s -H "Token: TU_CLAVE_API" \
  "https://tu-instalacion/api/3/modelo349?codejercicio=MF25&periodo=T1"
```

La respuesta, abreviada:

```json
{
    "modelo": "349",
    "plugin_version": 1.2,
    "empresa": {"idempresa": 5, "nombre": "Empresa Ejemplo SL", "cifnif": "00000000T"},
    "ejercicio": {
        "codejercicio": "MF25",
        "nombre": "MF Pruebas 2025",
        "fechainicio": "2025-01-01",
        "fechafin": "2025-12-31",
        "estado": "ABIERTO"
    },
    "periodo": "T1",
    "fechainicio": "2025-01-01",
    "fechafin": "2025-03-31",
    "parametros": {"codserie": ""},
    "resumen": {
        "num_operadores": 2,
        "total_entregas": 3000,
        "total_servicios_prestados": 0,
        "total_adquisiciones": 2000,
        "total_servicios_adquiridos": 0,
        "totales_por_clave": {"E": 3000, "S": 0, "A": 2000, "I": 0},
        "total_ventas": 3000,
        "total_compras": 2000
    },
    "casillas": {"01": 2, "02": 5000},
    "resultado": null,
    "detalle": {
        "ventas": [
            {"cifnif": "DE123456789", "nombre": "Cliente Ejemplo GmbH", "codpais": "DEU", "clave": "E", "base": 3000}
        ],
        "compras": [
            {"cifnif": "DE987654321", "nombre": "Proveedor Ejemplo GmbH", "codpais": "DEU", "clave": "A", "base": 2000}
        ]
    },
    "avisos": []
}
```

## 4. Qué devuelve cada bloque

- **`resumen`**: número de operadores y el total por clave: `E` entregas de bienes, `S` prestaciones de servicios, `A` adquisiciones de bienes e `I` adquisiciones de servicios. `totales_por_clave` trae siempre `E`, `S`, `A` e `I` y, si el periodo las tiene, también `T`, `M`, `H`, `R` y `D`. `total_ventas` y `total_compras` suman cada lado.
- **`casillas`**: las del registro del declarante del fichero. La `01` es el número de operadores y la `02`, el importe total de las operaciones. El plugin no calcula rectificaciones.
- **`resultado`**: siempre `null`. El modelo 349 es informativo y no tiene importe a ingresar.
- **`detalle`**: un elemento por operador y clave, en `ventas` y en `compras`, con el NIF-IVA, el nombre, el código de país (alfa-3), la clave y la base imponible.
- **`avisos`**: textos en español, por ejemplo que a la empresa le falta el teléfono o el administrador, o que el periodo no tiene operaciones.

La clave de cada operador sale del campo `operacion` de la factura: `intracomunitaria` da `E` en ventas y `A` en compras, e `intracom-servicios` da `S` e `I`. En las ventas, las operaciones que añade el plugin dan `T` (`intracom-triangular`), `M` (`intracom-post-importacion`), `H` (`intracom-post-import-repr`), `R` (`intracom-consigna`) y `D` (`intracom-consigna-devol`). Solo cuentan las facturas no editables.

## 5. Operaciones que añade el plugin

**`FacturaScripts\Plugins\Modelo349\Lib\Clave349::register()`**, llamado desde `Init::init()`, añade esas cinco operaciones con `InvoiceOperation::add($operacion, $traduccion, InvoiceOperation::TYPE_SALE)`, así que solo aparecen en los documentos de venta. Sus códigos están en las constantes `Clave349::OPERACION_*` y su mapa, en `Clave349::OPERACIONES_PLUGIN`. Otro plugin que quiera marcar estas operaciones debe usar esas constantes. El cálculo de documentos no les aplica ningún IVA especial.

## 6. Errores

Los errores devuelven `{"status": "error", "message": "..."}` con estos códigos:

| Código HTTP | `message` | Causa |
|---|---|---|
| 400 | `missing-period` | Falta `periodo`. Incluye `allowed` con los valores admitidos |
| 400 | `invalid-period` | El periodo no es válido. Incluye `allowed` |
| 400 | `invalid-year` | `anio` no tiene cuatro cifras |
| 404 | `serie-not-found` | La serie de `codserie` no existe |
| 404 | `exercise-not-found` | No existe el ejercicio o la empresa no tiene ninguno que empiece en ese año |
| 404 | `company-not-found` | La empresa no existe |
| 405 | `method-not-allowed` | Se ha llamado con otro método distinto de `GET` |

## 7. Dónde está el código

- **`FacturaScripts\Plugins\Modelo349\Controller\ApiModelo349`**: lee los parámetros y devuelve el JSON.
- **`FacturaScripts\Plugins\Modelo349\Lib\Modelo349Api`**: valida, resuelve la empresa y el ejercicio y monta la respuesta con `Modelo349Api::build(array $params)`.
- **`FacturaScripts\Plugins\Modelo349\Lib\Modelo349`**: el cálculo, que comparten la pantalla y la API mediante `Modelo349::calculate($codejercicio, $periodo, $codserie)`.

Desde otro plugin, usa siempre las clases a través de `FacturaScripts\Dinamic\Lib\...`.
