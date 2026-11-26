---
name: download-modelo349
description: Descarga toda la documentación oficial actualizada del modelo 349 de la AEAT (ayuda técnica, instrucciones, diseño de registro, especificaciones de desarrolladores, anexos de errores y normativa del BOE) en Doc/Context/Normativa
---

Eres un asistente que mantiene actualizada la documentación OFICIAL del modelo 349 (declaración recapitulativa de operaciones intracomunitarias, con periodos mensual y trimestral desde 2020) de la Agencia Tributaria, tanto la técnica (diseño de registro, especificaciones de presentación, anexos de errores de validación) como la de usuario (instrucciones, ayuda de presentación, plazos, novedades) y la normativa (BOE).

DOMINIOS PERMITIDOS: sede.agenciatributaria.gob.es, www2.agenciatributaria.gob.es, www.agenciatributaria.es (solo /static_files/AEAT_Desarrolladores/ y el portal de desarrolladores), www.boe.es. Nada de webs de terceros, foros, blogs ni GitHub, aunque aparezcan en buscadores. Los formularios y servicios con identificación (www1.agenciatributaria.gob.es/wlpl/..., TGVI online, Cl@ve, certificados) NO se visitan ni se usan.

DESCARGA: usa SIEMPRE /usr/bin/curl (SecureTransport de macOS) con -L y un User-Agent de navegador. NUNCA uses -k/--insecure. Existe un script ya probado que implementa esta tarea; úsalo y revisa su salida:
  python3 -I /Users/daniel89fg/Trabajos/FacturaScripts/facturascripts/Plugins/Modelo349/.agents/skills/download-modelo349/scripts/sincronizar.py /Users/daniel89fg/Trabajos/FacturaScripts/facturascripts/Plugins/Modelo349/Doc/Context/Normativa
Su salida y Doc/Context/Normativa/.log_run.json incluyen: nuevos, modificados, iguales, errores, páginas nuevas/cambiadas/desaparecidas y los enlaces descubiertos.

PÁGINAS (guardar texto en paginas/, con la URL de origen en la primera línea). El script RASTREA RECURSIVAMENTE las páginas .html de estas secciones a partir de las semillas, así que las páginas nuevas se detectan solas:
- Ficha del procedimiento GI28 (solo se guarda; de ella salen la normativa, las instrucciones y los enlaces): /Sede/procedimientoini/GI28.shtml
- Ayuda técnica: /Sede/ayuda/consultas-informaticas/declaraciones-informativas-ayuda-tecnica/modelos-349-720/modelo-349* (presentación por formulario, hasta 40.000 registros, y por fichero con TGVI online), consulta y baja de declaraciones (incluidas las de ejercicios anteriores a 2020 de empresas, colaboradores y apoderados), número de justificante y presentación de documentación complementaria.
- Gestión del modelo en la Sede: /Sede/todas-gestiones/impuestos-tasas/declaraciones-informativas/modelo-349-*/ (plazos de presentación) y /Sede/impuestos-tasas/declaraciones-informativas/modelo-349-*/ (nota informativa de la reforma de 2020 y ejercicios anteriores).
- Diseños de registro: /Sede/ayuda/disenos-registro/modelos-300-399.html (solo se descarga el fichero del 349).
- Si se amplía el rastreo, cambia CRAWL_PREFIXES / PAGES / EXCLUIR_PAGINAS en sincronizar.py.
- Las páginas guardadas antes que ya no aparecen se listan en "paginas_desaparecidas" (anótalas en el CHANGELOG; no se borran).

FICHEROS DIRECTOS (a actual/):
- Diseño de registro del 349 (Orden HAC/174/2020, ejercicio 2020 y siguientes): https://sede.agenciatributaria.gob.es/static_files/Sede/Disenyo_registro/DR_300_399/archivos_20/DR_Anexo_349.pdf
- Instrucciones del modelo 349 (ficha GI28): https://sede.agenciatributaria.gob.es/static_files/Sede/Procedimiento_ayuda/GI28/instr_mod_349.pdf
- Portal de desarrolladores (base https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/):
  Informativas/Especificaciones_Modelos/2025/espec_349_2026.pdf (especificaciones del 349 para el ejercicio 2026; el portal la publica en la carpeta 2025)
  Informativas/Especificaciones_Modelos/2025/Espec-349-2025.pdf (ídem, ejercicio 2025)
  Informativas/Especificaciones_Modelos/2026/Anexo-errores-26/PAIN-M349_2026.xml (anexo de errores de validación, 2026)
  Informativas/Especificaciones_Modelos/2027/Anexo-errores-27/PAIN-M349_2027.xml (anexo de errores de validación, 2027)
  Informativas/2020/espes349e20v10.pdf (firma y envío de declaraciones del 349, ejercicio 2020, v 1.0; es el último que publica el portal)
  Informativas/General/Especificaciones_TGVI_Online.pdf (validación y presentación por Internet de las informativas con TGVI Online)
  General/EspecificacionesServiciosDeclaracionesAEAT_ES.pdf (servicios comunes de declaraciones: presentación directa, validación e impresión, consulta)
  General/consultas.xsd (esquema del servicio de consulta de declaraciones presentadas)
- No se descargan (decisión de alcance): las especificaciones de ejercicios anteriores a 2025 (Espec-349-2018 a 2024, hasta 1,5 MB cada una) y las de firma y envío de 2012 a 2019, porque el diseño vigente es el de la Orden HAC/174/2020 (2020 y siguientes). Si hacen falta, añádelas a DIRECT en sincronizar.py (el portal las enlaza en `Especificaciones_Modelos/Ejercicio_AAAA` y `Resto_de_modelos`).
- Cualquier otro fichero (.pdf .zip .xls .xlsx .xsd .xml .doc .docx .txt) enlazado desde las páginas anteriores, salvo los de otros modelos (EXCLUIR_ENLACES; vacío al crear la skill: si una ejecución trae ficheros ajenos al 349, mira "enlaces" en .log_run.json y ajústalo).

NORMATIVA (guardar el texto consolidado en actual/normativa/, desde https://www.boe.es/buscar/act.php?id=BOE-A-...): las normas que la propia Sede enlaza como normativa del modelo en la ficha GI28, más la base legal:
- Orden EHA/769/2010 (BOE-A-2010-5098), que aprueba el modelo 349, sus diseños físicos y lógicos y el lugar, forma y plazo de presentación.
- Sus modificaciones: EHA/3062/2010 (BOE-A-2010-18368, formas de presentación de las informativas), EHA/1721/2011 (BOE-A-2011-10827), HAP/2725/2012 (BOE-A-2012-15380), HAP/2194/2013 (BOE-A-2013-12385, procedimientos y condiciones generales de presentación), HAC/174/2020 (BOE-A-2020-2898, fin del periodo anual y ventas en consigna) y HAC/1274/2020 (BOE-A-2020-17269).
- Real Decreto-ley 3/2020 (BOE-A-2020-1651), que modifica el IVA de las ventas en consigna y origina la reforma del modelo 349 (nota informativa de la Sede).
- Base legal: Ley 37/1992 del IVA (BOE-A-1992-28740) y Reglamento del IVA, RD 1624/1992 (BOE-A-1992-28925, art. 79 y siguientes: declaración recapitulativa de operaciones intracomunitarias).
- En cada ejecución, mira en la ficha GI28 y en la nota informativa si aparece una orden nueva que no esté en la lista (p. ej. una modificación del modelo 349 o un proyecto de orden que se publique en el BOE) y añádela a DIRECT en sincronizar.py. Anota en el CHANGELOG si el texto consolidado de la Orden EHA/769/2010 muestra nuevas modificaciones (su última actualización publicada era la del 30/12/2020).

REGLAS COMUNES
1. Fuentes: SOLO los dominios oficiales listados en esta tarea.
2. Estructura dentro de la carpeta Doc/Context/Normativa del plugin:
   - actual/            → última versión de cada fichero (nombre original conservado)
   - actual/normativa/  → textos consolidados del BOE
   - Nombres: ningún nombre de archivo pasa de 60 caracteres (el script los acorta con `nombre_corto()` de `../FiscalES/.agents/skills/download-fiscales/scripts/nombres.py`, que añade una huella del nombre completo; el oficial queda en la URL del manifest), para que el plugin se pueda descargar en Windows. Si el script avisa de un `NOMBRE LARGO`, corrígelo antes de terminar.
   - historico/AAAA-MM-DD/ → versiones anteriores sustituidas (mover aquí, nunca borrar)
   - paginas/           → copia en texto/Markdown de cada página (primera línea: URL de origen) para detectar cambios
   - manifest.json      → por fichero: url, ruta, sha256, tamaño, fechas de descarga y de comprobación, cabeceras ETag/Last-Modified
   - CHANGELOG.md       → añade una entrada con fecha por cada ejecución
3. Procedimiento en cada ejecución:
   a) Descarga cada URL de la lista. Calcula SHA-256 y compáralo con manifest.json.
   b) Si el hash cambió: mueve la versión vieja a historico/AAAA-MM-DD/ y guarda la nueva en actual/.
   c) En cada página, extrae TODOS los enlaces a documentos y descarga los que no estén en el manifest (así detectas documentos nuevos con nombre distinto).
   d) Anota en el CHANGELOG: ficheros nuevos, modificados (con versión/fecha que figure dentro del documento), desaparecidos (404) y páginas cuyo texto cambió. Si una página muestra "Página actualizada: fecha", guárdala.
4. Seguridad: no ejecutes ni abras macros de ningún fichero descargado; no instales nada; no rellenes formularios ni inicies sesión; no accedas a nada que requiera certificado o contraseña. No desactives nunca la verificación SSL.
5. Hay que intentar descargar siempre todo (decisión del usuario del 27/10/2026). El portal de desarrolladores (www.agenciatributaria.es) usa la cadena de certificados de la FNMT (raíz «AC RAIZ FNMT-RCM SERVIDORES SEGUROS»), que `curl` puede no verificar en algunos equipos («SSL certificate problem: self signed certificate in certificate chain»): en ese caso no se usa -k. Sigue con el navegador integrado y, si tampoco, con la lista de descarga manual del usuario, que se registra con `registrar_descarga.py` del núcleo (`<base>` = `Doc/Context/Normativa`). Procedimiento común a las skills de descarga: `../FiscalES/.agents/skills/download-fiscales/NAVEGADOR.md`. El navegador entra como un visitante normal: no se resuelven ni se saltan CAPTCHAs ni comprobaciones anti-bots, no se falsean cabeceras ni el agente de usuario y no se inicia sesión. Apunta en el CHANGELOG qué se comprobó con el navegador y qué queda en "Descarga manual".
   Ficheros comunes a otros modelos (TGVI online, servicios de declaraciones, consultas.xsd): si ya están en `Doc/Context/Normativa/actual/` de otro plugin fiscal (p. ej. Modelo111), puedes registrarlos desde ahí con `registrar_descarga.py` solo cuando su SHA-256 coincida con el que el navegador calcula del fichero publicado; anótalo en el CHANGELOG.
   Páginas del portal de desarrolladores a revisar con el navegador (su texto no se guarda en paginas/): `AEAT.desarrolladores/Desarrolladores/_menu_/Documentacion/Declaraciones_Informativas/Especificaciones_Modelos/Especificaciones_Modelos.html` y la de cada ejercicio (`.../Ejercicio_2026/Ejercicio_2026.html`, `.../Ejercicio_2027/Ejercicio_2027.html`), `.../Declaraciones_Informativas/General/General.html`, `.../Declaraciones_Informativas/Resto_de_modelos/Resto_de_modelos.html` (firma y envío del 349 hasta 2020) y `.../Documentacion/General/General.html`; el tablón de anuncios (`Desarrolladores.html`) avisa cuando un modelo estrena diseño o pasa al entorno de pruebas. El portal no tiene página propia del 349: sus ficheros salen de la lista de especificaciones por ejercicio (busca «349»).
   Siempre que quede algo por descargar a mano, el CHANGELOG lo lista en una tabla con el **enlace directo y completo de descarga** de cada fichero (URL clicable, nunca solo la ruta relativa), su tamaño, fecha y SHA-256 publicado; el resumen final repite esos mismos enlaces.
6. Resumen final (máx. 20 líneas): nº de ficheros comprobados, nuevos, modificados, errores y una lista destacada de los cambios relevantes para un desarrollador (nuevo diseño de registro, versión de las especificaciones, claves de operación nuevas, órdenes nuevas, plazos, formas de presentación, anexos de errores). Sección "DESCARGA MANUAL" con lo que el usuario tiene que bajar (URL, motivo y dónde guardarlo), o "Nada que descargar a mano". Si no hay cambios, dilo en una línea.
7. Si algún documento nuevo o modificado menciona un nuevo diseño de registro, nuevas claves de operación, cambios en la forma de presentación o fechas de entrada en vigor, ponlo en una sección "ALERTA" al inicio del resumen, y avisa de que el código del plugin (Doc/Context/03-fichero-aeat.md) puede necesitar ajustes.
