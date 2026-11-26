# 0003. API de solo lectura e informativa

- Fecha: 2026-11-26
- Commits: `80b5df5`
- Estado: vigente

## Contexto

fs-mcp necesita leer los modelos fiscales con una estructura común.

## Decisión

`GET /api/3/modelo349` solo lee: no genera el TXT ni modifica registros. Devuelve las casillas 01 y 02 y
`resultado = null`, porque el 349 es informativo y no tiene cuota a ingresar. Reutiliza `Lib/Modelo349`, por lo que los
importes son los de la pantalla.

## Consecuencias

Cualquier ampliación (escritura, generar el fichero) sería un cambio de enfoque. El historial no explica por qué no se
expone el TXT.
