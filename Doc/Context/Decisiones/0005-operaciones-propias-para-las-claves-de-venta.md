# 0005. Operaciones propias para las claves de venta T, M, H, R y D

## Contexto

La clave del modelo sale del campo `operacion` de la factura ([0001](0001-clave-por-operacion-de-la-factura.md)). El
core solo tiene dos operaciones intracomunitarias (`intracomunitaria` e `intracom-servicios`), así que el plugin solo
podía declarar E, S, A e I. Las claves T (triangulares), M y H (entregas posteriores a una importación exenta, la H por
representante fiscal), R (envíos en consigna) y D (devoluciones de consigna) quedaban para ajustarlas a mano en la Sede.

`InvoiceOperation::add($clave, $traduccion, $tipo)` del core permite que un plugin añada opciones al campo.

## Decisión

`Clave349::register()`, llamado desde `Init::init()`, añade cinco operaciones de **venta**
(`InvoiceOperation::TYPE_SALE`): `intracom-triangular` (T), `intracom-post-importacion` (M),
`intracom-post-import-repr` (H), `intracom-consigna` (R) e `intracom-consigna-devol` (D). Sus códigos están en las
constantes `Clave349::OPERACION_*` (máximo 30 caracteres, el largo del campo) y se mapean en `Clave349::VENTAS`, así
que la consulta del cálculo las incluye sin más cambios. Todas son claves del declarante como vendedor o remitente:
ninguna existe en compras.

La C (sustitución del destinatario en consigna) no se añade: el registro de tipo 2 necesita además el NIF y el nombre
del empresario sustituido (posiciones 179-235), que la factura no guarda.

## Consecuencias

- El cálculo de documentos (`CalculatorModSpain`), la contabilidad y la clasificación de FiscalES solo tratan de forma
  especial las operaciones del core. Con estas cinco no se pone IVA 0 % ni excepción automática: el usuario elige el
  impuesto y la excepción de cada línea. La documentación de uso lo avisa.
- Si se desactiva el plugin, las facturas conservan el valor en `operacion`, pero la opción deja de aparecer en el
  desplegable.
- La API añade `resumen.totales_por_clave` para que estas claves tengan su total sin cambiar los campos existentes.
