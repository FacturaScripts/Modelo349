# Modelo349
Plugin para FacturaScripts con el modelo 349 para la hacienda española.
- https://facturascripts.com/plugins/modelo349

## Estructura y API
El plugin sigue la misma estructura que el resto de modelos fiscales: `Lib/Modelo349` (cálculo), `Lib/Modelo349Api` (respuesta de la API) y `Lib/Modelo349File` (fichero de la AEAT), además de `Lib/Clave349` para las claves de operación. `Clave349::register()` añade al campo «Operación» de las facturas de venta las operaciones de las claves T, M, H, R y D.

`GET /api/3/modelo349` es de solo lectura: `periodo` T1-T4 o 01-12 (obligatorio), `codejercicio` o `idempresa` y `anio`, y `codserie`. Devuelve el resumen, las casillas, los operadores intracomunitarios y los avisos. Es informativo: `resultado` vale `null`. Una clave API sin acceso completo necesita el permiso de lectura del recurso `modelo349`.

## Issues / Feedback
https://facturascripts.com/contacto

## Links
- [Curso de FacturaScripts](https://www.youtube.com/watch?v=rGopZA3ErzE&list=PLNxcJ5CWZ8V6nfeVu6vieKI_d8a_ObLfY)
- [Programa de contabilidad gratis para autónomos](https://facturascripts.com/software-contabilidad)
- [Programa para hacer facturas gratis](https://facturascripts.com/programa-para-hacer-facturas)
- [Programa para hacer presupuestos gratis](https://facturascripts.com/programa-de-presupuestos)
