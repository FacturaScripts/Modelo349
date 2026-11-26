# 0004. Periodo normalizado entre pantalla y API

- Fecha: 2026-11-26
- Commits: `80b5df5`
- Estado: vigente

## Contexto

La pantalla y el TXT usan `1T`-`4T` y `01`-`12`; la API usa `T1`-`T4`, como el resto de plugins fiscales.

## Decisión

`Modelo349Api::normalizePeriod()` unifica los alias (`1T`, `T1`, `3`, `03`...) a `T1`-`T4` y `01`-`12`.
`internalPeriod()` convierte al formato de pantalla y `screenPeriod()` lo aplica a la entrada de la pantalla: un
periodo inválido cae al trimestre actual en vez de generar un fichero con un periodo inexistente.

## Consecuencias

Un periodo nuevo debe añadirse a `PERIODS` y a `Modelo349File::getPeriodDates()`.
