# Evolución

Historial corto (26 commits, jun-nov 2026).

| Fecha | Etapa |
|---|---|
| 2026-06-29 | Versión inicial (`86498a3`): pantalla `Controller/Modelo349.php` con su vista Twig, `Txt349Export` y CI (tests y releases). Ese día también README y `.gitignore`. |
| 2026-07-03 | Selector de empresas y mejoras de la vista (`955b607`); claves de traducción en minúsculas (`eaaea42`). |
| 2026-08-20 | Submenú `tax-models` (`3c1f4e0`). |
| 2026-09-10 | Versión 1.1 (`3703a68`). |
| 2026-10-16 | Claves de operación con `Clave349` (`9018bd9`); antes la clave era E en ventas y A en compras. |
| 2026-10-27 y 2026-11-13 | Prefijo `EL` de Grecia (`e524e90`) y uso de `Vies::vatPrefix()` (`28e6e00`, `f895718`). |
| 2026-11-13 | Avisos y títulos de pestañas (`d640a3e`); traducciones y script actualizador; versión 1.2 (`9aecdf5`). |
| 2026-11-19 | CI: los tests solo se lanzan cuando cambia el código (`3b3a895`). |
| 2026-11-26 | El informe pasa del submenú `tax-models` al subgrupo `taxation-spain` (Fiscalidad España), con el icono `fa-book`; versión 1.3. |
| 2026-11-26 | `80b5df5`: cálculo a `Lib/Modelo349` filtrando por empresa y año del ejercicio; API de solo lectura; `Txt349Export` pasa a `Modelo349File`. |

Antes de `80b5df5` el cálculo no filtraba por empresa y el año se tomaba del código del ejercicio.
Los commits de traducciones, CI y versiones no cambian el comportamiento.
