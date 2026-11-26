# 0002. Empresa y año se obtienen del ejercicio

- Fecha: 2026-11-26
- Commits: `80b5df5`
- Estado: vigente

## Contexto

El código del ejercicio no es siempre el año (puede ser `0001`, `AL25`...) y cada empresa tiene sus ejercicios.

## Decisión

El ejercicio seleccionado determina la empresa (se filtran las facturas por `idempresa`) y el año (fecha de inicio del
ejercicio, `Modelo349::getYear()`). `Modelo349File::generate()` recibe el año y la empresa; sin empresa usa la
empresa predeterminada. Antes buscaba el ejercicio cuyo código fuese el año y, si no lo encontraba, la empresa 1: era
justo el supuesto que esta decisión descarta, y se quitó.

## Consecuencias

En la API, `codejercicio` manda sobre `idempresa` y `anio`. Un ejercicio que no empiece en enero declara el año de su
fecha de inicio. Las fechas del periodo se calculan sobre ese año.
