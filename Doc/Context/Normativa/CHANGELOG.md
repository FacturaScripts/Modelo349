# CHANGELOG – Documentación oficial del modelo 349

## 2026-11-26 (primera ejecución, carpeta vacía)

### Qué se ha descargado
- **Páginas (14)** en `paginas/`, con la URL de origen en la primera línea: ficha del procedimiento GI28, ayuda técnica del
  349 (presentación por formulario y por fichero con TGVI online), consulta y baja de declaraciones (también las de ejercicios
  anteriores a 2020 de empresas, colaboradores y apoderados), número de justificante, presentación de documentación
  complementaria, plazos de presentación, nota informativa de la reforma de 2020, ejercicios anteriores y la lista de
  diseños de registro de los modelos 300 a 399.
- **Diseño de registro** (`actual/`): `DR_Anexo_349.pdf` (Orden HAC/174/2020, ejercicio 2020 y siguientes; fichero publicado
  el 13/07/2021). Dos tipos de registro (1: declarante y hoja resumen; 2: operador intracomunitario y rectificaciones),
  ISO-8859-1.
- **Instrucciones** (`actual/`): `instr_mod_349.pdf` (ficha GI28, publicado el 12/06/2026).
- **Normativa del BOE** (`actual/normativa/`, 10 textos consolidados): Orden EHA/769/2010 (aprueba el modelo 349) y sus
  modificaciones EHA/3062/2010, EHA/1721/2011, HAP/2725/2012, HAP/2194/2013, HAC/174/2020 y HAC/1274/2020, más el Real
  Decreto-ley 3/2020, la Ley 37/1992 del IVA y el Reglamento del IVA (RD 1624/1992, art. 79 y siguientes).
- **Del portal de desarrolladores** (comunes a otros modelos, copiados de Modelo111 con el SHA-256 verificado contra el
  fichero publicado, `origen_descarga: manual`): `Especificaciones_TGVI_Online.pdf` (v 2.8),
  `EspecificacionesServiciosDeclaracionesAEAT_ES.pdf` y `consultas.xsd`.

### Versiones vigentes comprobadas
- Modelo 349: diseño de registro de la **Orden HAC/174/2020** (2020 y siguientes); ficha GI28 actualizada el 16/11/2026 y
  lista de diseños de registro el 26/10/2026.
- Periodicidad: mensual o trimestral (desde 2020 no existe el periodo anual). Plazo: los 20 primeros días naturales del mes
  siguiente; la de julio puede presentarse en agosto y los 20 primeros días de septiembre; la de diciembre y la del último
  trimestre, en los 30 primeros días de enero.
- Presentación: por formulario web (hasta 40.000 registros) o por fichero con TGVI online (cualquier número de registros);
  en ambos casos se pueden presentar parcialmente los registros correctos y corregir los erróneos con una complementaria.
- La Orden EHA/769/2010 consolidada muestra como última actualización la del 30/12/2020 (HAC/1274/2020). Sin nuevas
  modificaciones desde entonces.
- Portal de desarrolladores (no tiene página propia del 349; salen de la lista de especificaciones por ejercicio):
  especificaciones del **ejercicio 2026** publicadas en la carpeta 2025 (`espec_349_2026.pdf`, 18/11/2025), del 2025
  (`Espec-349-2025.pdf`, 08/11/2024) y anexos de errores de validación del **2026** (05/10/2026) y del **2027**
  (16/10/2026, ya publicado).

### ALERTA
- Las especificaciones `espec_349_2026.pdf` son la versión 1 del año 2026, con **fecha de efectos 01-02-2026**.
- Existen especificaciones del 349 para 2026 y un **anexo de errores para 2027** (`PAIN-M349_2027.xml`, mayor que el de
  2026: 61 KB frente a 41 KB). Cuando se descarguen, compara las especificaciones 2026 con el diseño de la Orden
  HAC/174/2020 y los anexos de errores con las validaciones del plugin, por si cambian claves, campos o errores:
  `Doc/Context/03-fichero-aeat.md` puede necesitar ajustes.

### Descarga manual (el portal de desarrolladores no se pudo leer con curl) – HECHA el 2026-11-26
`www.agenciatributaria.es` devuelve «SSL certificate problem: self signed certificate in certificate chain» (la raíz
«AC RAIZ FNMT-RCM SERVIDORES SEGUROS» no está en la lista de confianza de este equipo) y no se desactiva la verificación.
Con el navegador integrado se comprobó que los cinco ficheros existen (HTTP 200) y se calculó su SHA-256; el navegador no
los guarda en disco: el usuario los descargó a mano y se registraron con `registrar_descarga.py` (`--origen manual`); los cinco
SHA-256 coinciden con los publicados y están en `actual/`.

| Fichero (enlace directo de descarga) | Tamaño | Last-Modified | SHA-256 publicado |
|---|---|---|---|
| [`espec_349_2026.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Informativas/Especificaciones_Modelos/2025/espec_349_2026.pdf) (349, ejercicio 2026) | 376.689 | 18/11/2025 | `f0ea261fb1d3456fa2afcf475e42f3d16bd6fef6831616ffb1694792691c7ccc` |
| [`Espec-349-2025.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Informativas/Especificaciones_Modelos/2025/Espec-349-2025.pdf) (349, ejercicio 2025) | 124.007 | 08/11/2024 | `c90f6a6f05a3ba9166a827756473ae684b2b31975c32abbee1e7111b7dd39561` |
| [`PAIN-M349_2026.xml`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Informativas/Especificaciones_Modelos/2026/Anexo-errores-26/PAIN-M349_2026.xml) (anexo de errores 2026) | 41.089 | 05/10/2026 | `85fe01d44f3a8213e218d50eb896912257ed1b3970f54efd61d6794310acbb14` |
| [`PAIN-M349_2027.xml`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Informativas/Especificaciones_Modelos/2027/Anexo-errores-27/PAIN-M349_2027.xml) (anexo de errores 2027) | 61.647 | 16/10/2026 | `48b11e9a8441f961b2f2929b11936b0e547981d63d63ff467326f98925cb7599` |
| [`espes349e20v10.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Informativas/2020/espes349e20v10.pdf) (firma y envío del 349, 2020, v 1.0) | 98.693 | 12/11/2019 | `a8acc6ddab796afaf60c92aab7b7eb777534d5e0a3c19930a6594d1dcbb480b2` |

Páginas del portal leídas con el navegador (su texto no se guarda en `paginas/`): `Documentacion/Declaraciones_Informativas/Especificaciones_Modelos`
(y sus páginas de ejercicio 2016 a 2027), `Declaraciones_Informativas/General` y `Declaraciones_Informativas/Resto_de_modelos`.

### Fuera de alcance (decisión de la primera ejecución)
No se descargan las especificaciones del 349 de ejercicios anteriores a 2025 (`Espec-349-2018` a `Espec-349-2024`, de hasta
1,5 MB cada una; también existen las de 2017 y anteriores) ni las de firma y envío de 2012 a 2019: el diseño vigente es el de
la Orden HAC/174/2020. Están en el portal (`Especificaciones_Modelos/Ejercicio_AAAA` y `Resto_de_modelos`) y se añaden a
`DIRECT` de `sincronizar.py` si hacen falta.

### Resultado
- 20 ficheros en el manifest (12 por script, 3 comunes registrados desde Modelo111 y 5 manuales del portal de desarrolladores).
  Sin nombres largos. Nada pendiente de descarga manual.
