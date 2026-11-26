# Fichero TXT para la AEAT

`Lib/Modelo349File::generate()` devuelve el contenido del fichero de texto de ancho fijo. La pantalla lo descarga como
`modelo_349_<ejercicio>_<periodo>.349` con `Content-Type: text/plain; charset=ISO-8859-1`.

## Cómo es hoy

- **Registro tipo 1** (`buildTipo1`): un registro de declarante de 500 posiciones más CRLF. Lleva modelo `349`, año, NIF,
  nombre, teléfono y persona de contacto (el administrador de la empresa), justificante, marcas de complementaria (C) o
  sustitutiva (S) con el justificante anterior, periodo, número de operadores e importe total. Las rectificaciones van
  a cero. El mapa de posiciones está en el docblock del método.
- **Registro tipo 2** (`buildTipo2`): uno por operador, también de 500 posiciones. Lleva el NIF del declarante, el NIF
  del operador (17), el nombre (40), la clave y la base (11 enteros y 2 decimales).
- Todo el contenido se pasa a ISO-8859-1 al final (`mb_convert_encoding`). Antes, `sanitize()` quita tildes y `ñ`/`ç`, y
  `formatString()` pasa a mayúsculas, trunca y rellena.
- **NIF del operador** (`getNifOperador`): 2 caracteres de prefijo VIES más 15 del número. El prefijo sale de
  `Vies::vatPrefix()` del core a partir del código ISO del país (Grecia usa `EL`, no `GR`). Del número se quita el
  prefijo VIES o el código ISO si el cliente los escribió, y los separadores.
- Justificante: si no se indica, se genera `349` + año + `000001`.
- El año es el del ejercicio, no su código (ver [0002](Decisiones/0002-empresa-y-anio-del-ejercicio.md)). Si no se
  pasa `idempresa`, `generate()` usa la empresa predeterminada; ya no busca un ejercicio cuyo código sea el año.
- Clave: `generate()` solo escribe los operadores cuya clave es una de las del diseño de la AEAT
  (`Clave349::esValida()`). Los demás no cuentan en el número de operadores ni en el importe del registro tipo 1 y se
  avisa con `349-operator-without-key`. `buildTipo2()` no supone ninguna clave: antes ponía `E` por defecto, y un fallo
  en el cálculo habría declarado en silencio una entrega de bienes.

## Por qué

El prefijo de Grecia se corrigió en `e524e90` con una función propia y se sustituyó por `Vies::vatPrefix()` cuando el
core la tuvo (`28e6e00`, `f895718`).

## Al cambiarlo

- Cada registro debe medir 500 caracteres: hay tests de longitud en `Test/main/Modelo349FileTest.php`.
- Un campo mal calculado desplaza todos los siguientes: revisa el mapa de posiciones antes de tocar el orden.
