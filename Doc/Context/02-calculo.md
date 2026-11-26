# Cálculo del modelo

`Lib/Modelo349::calculate($codejercicio, $periodo, $codserie)` devuelve ejercicio, empresa, año, fechas, ventas, compras
y avisos.

## Cómo es hoy

1. Carga el ejercicio por su código. Si no existe, solo devuelve el aviso `accounting-exercise-not-found`.
2. La empresa es la del ejercicio (`idempresa`). El año sale de la fecha de inicio del ejercicio (`getYear()`).
3. Las fechas del periodo las da `Modelo349File::getPeriodDates()` (1T-4T o 01-12).
4. `queryOperators()` ejecuta una consulta SQL para ventas (`facturascli`, `clientes`, contacto `idcontactofact`) y
   otra para compras (`facturasprov`, `proveedores`, contacto `idcontacto`).
5. Avisos: empresa sin teléfono o sin administrador, y periodo sin operaciones (`349-no-data`).

Condiciones de la consulta:

- Facturas de la empresa del ejercicio, con fecha dentro del periodo y, si se indica, de la serie elegida.
- Solo facturas no editables (`editable = false`): las editables aún no están cerradas.
- Solo `operacion` intracomunitaria o intracomunitaria de servicios (`Clave349::operaciones()`).
- País del contacto del tercero dentro de `EU_COUNTRIES` (códigos alfa-3 de la UE sin España). El país no es el de la
  factura: es el del contacto de facturación del cliente (ventas) o el contacto del proveedor (compras).
- Terceros con `cifnif` no vacío.
- Agrupa por `cifnif`, razón social, país y operación; `HAVING SUM(neto) > 0` descarta operadores con saldo cero o
  negativo. La base es `SUM(neto)` redondeada a 2 decimales.

La clave de cada fila sale de `Clave349::porOperacion()` (ver la decisión
[0001](Decisiones/0001-clave-por-operacion-de-la-factura.md)).

## Por qué

Ver las decisiones [0002](Decisiones/0002-empresa-y-anio-del-ejercicio.md) (empresa y año) y
[0001](Decisiones/0001-clave-por-operacion-de-la-factura.md) (clave).

## Al cambiarlo

- La lista de países es fija; un país nuevo en la UE exige añadirlo a `EU_COUNTRIES`.
- Las claves T, M, H, R y D salen de las operaciones de venta que añade el plugin
  ([0005](Decisiones/0005-operaciones-propias-para-las-claves-de-venta.md)). La C no se calcula: necesita el NIF y el
  nombre del sustituto, que una factura no tiene.
- Tampoco calcula rectificaciones (el TXT las deja a cero).
