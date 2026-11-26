# 0001. Clave de operación según la operación de la factura

- Fecha: 2026-10-16
- Commits: `9018bd9`
- Estado: vigente

## Contexto

El TXT asignaba clave E a todas las ventas y A a todas las compras, aunque fueran servicios.

## Decisión

`Lib/Clave349` deduce la clave del campo `operacion` de la factura: intracomunitaria da E (venta) o A (compra);
intracomunitaria de servicios da S (venta) o I (compra). Las facturas con otra operación no se declaran, aunque el
tercero sea de la UE. La consulta agrupa por operación, así que un mismo operador puede salir dos veces (una por clave).

## Consecuencias

Las facturas de UE con operación sin marcar como intracomunitaria quedan fuera: el usuario debe marcarla bien. Las
claves T, M, H, R y D salen de las operaciones que añade el propio plugin (ver
[0005](0005-operaciones-propias-para-las-claves-de-venta.md)); la C no existe en el plugin.
