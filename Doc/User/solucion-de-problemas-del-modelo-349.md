Si el modelo 349 sale vacío, le falta una operación que esperabas o el programa muestra un aviso, casi siempre es porque una factura, un cliente o la empresa no cumplen alguno de los requisitos del plugin. Esta página recoge los casos más habituales y cómo corregirlos.

## 🔍 No aparece una factura

Repasa estos puntos por orden hasta encontrar el que falla:

1. **El ejercicio y el periodo.** El ejercicio decide la empresa y el año. Comprueba que has elegido el ejercicio de la empresa a la que pertenece la factura, que el periodo incluye su fecha y que la **Serie** es **Todos** o la de esa factura.
2. **La factura está emitida.** Las facturas editables, como los borradores, no se cuentan. Emítela para que entre en el modelo.
3. **La operación de la factura.** Abre la factura, pulsa **Detalle** y mira el campo **Operación**: tiene que ser **Intracomunitaria**, **Intracomunitaria de servicios** o, en las ventas, una de las operaciones que añade el plugin (triangular, tras importación exenta o en consigna). Con cualquier otro valor la factura se deja fuera. Está explicado en [Claves de operación del modelo 349](claves-de-operacion-del-modelo-349.md).
4. **El país del cliente o proveedor.** Se toma el país de su contacto, no el de la dirección de envío ni el que haya escrito la factura: en las ventas, la dirección de facturación del cliente, y en las compras, el contacto principal del proveedor. Tiene que ser un país de la UE distinto de España.
5. **El CIF/NIF.** Si el cliente o proveedor no lo tiene informado, la operación no se incluye. Escríbelo con el prefijo del país.
6. **El importe.** Un operador solo sale si la suma de sus facturas en esa clave es mayor que cero. Una factura rectificativa que compensa por completo a la original lo deja fuera.

Los países de la UE que se tienen en cuenta son los 26 estados miembros distintos de España.

## ⚠️ Avisos al calcular el modelo

- **La empresa no tiene teléfono y es obligatorio** y **La empresa no tiene administrador y es obligatorio**: el fichero oficial pide el teléfono de la empresa y una persona de contacto, que el programa toma del campo **Administrador**. Complétalos desde **menú Administrador** → **Empresas** y vuelve a calcular el modelo.
- **No hay operaciones intracomunitarias en el período seleccionado**: ninguna factura del periodo cumple los requisitos de arriba. Cambia el periodo o revisa los puntos de la sección anterior.
- **Ejercicio no encontrado**: el ejercicio elegido ya no existe. Elige otro de la lista.

## 🧾 El NIF del operador en el fichero

En el fichero, el NIF de cada operador lleva delante el código de dos letras de su país, que es el de su NIF-IVA. Coincide con el código del país salvo en Grecia, que usa **EL** en lugar de GR. Si el CIF/NIF ya empieza por ese código o por el del país, el programa no lo repite, y quita los espacios y guiones.

## 📅 Se declara un año distinto del que esperabas

El año del modelo es el de la fecha de inicio del ejercicio elegido, no su código. Un ejercicio con código `0001` que empieza el 1 de enero de 2026 declara el año 2026. La línea bajo los filtros muestra la empresa y el año que se van a declarar.

## Ver también

- [Cómo rellenar el modelo 349](como-rellenar-el-modelo-349.md)
- [Claves de operación del modelo 349](claves-de-operacion-del-modelo-349.md)
