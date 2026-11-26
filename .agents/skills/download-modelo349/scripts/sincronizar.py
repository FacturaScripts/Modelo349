#!/usr/bin/env python3
"""Sincroniza la documentación oficial del modelo 349 de la AEAT en la carpeta Normativa (primer argumento).

Uso:
  python3 -I sincronizar.py <carpeta Normativa>

Descarga, solo de dominios oficiales (Sede de la AEAT, portal de desarrolladores de la AEAT y BOE):
- las páginas de ayuda técnica, plazos, notas informativas y ejercicios anteriores del modelo 349 (se guarda su texto en
  paginas/),
- el diseño de registro (.pdf), las instrucciones, las especificaciones técnicas y los documentos enlazados,
- la normativa del BOE (órdenes que aprueban y modifican el modelo, Real Decreto-ley 3/2020, Ley y Reglamento del IVA).

Estructura de salida: actual/ (última versión), historico/AAAA-MM-DD/ (versiones sustituidas), paginas/ (texto de las
páginas), manifest.json (url, sha256, tamaño, fechas y cabeceras) y .log_run.json (resultado de la última ejecución).
La TSL y la LOTL no se descargan aquí: son comunes a los plugins fiscales y las mantiene la skill download-fiscales
del núcleo FiscalES.

@author Daniel Fernández Giménez <contacto@danielfg.es>
"""
import hashlib, json, os, re, shutil, sys, subprocess, html
from datetime import date
from urllib.parse import urljoin, urlparse, unquote

# Nombres cortos, comunes a los plugins fiscales (../FiscalES/.agents/skills/download-fiscales/scripts/nombres.py):
# Windows no admite rutas largas, así que ningún archivo guardado pasa de 60 caracteres.
sys.path.insert(0, os.path.join(os.path.dirname(os.path.realpath(__file__)), *[".."] * 5, "FiscalES", ".agents", "skills",
                                "download-fiscales", "scripts"))
from nombres import avisar, nombre_corto  # noqa: E402

ROOT = os.path.abspath(sys.argv[1])
TODAY = date.today().isoformat()
ALLOWED = {"sede.agenciatributaria.gob.es", "www2.agenciatributaria.gob.es", "www.agenciatributaria.es", "www.boe.es"}
UA = "Mozilla/5.0 (compatible; doc-sync)"
EXT = re.compile(r"\.(pdf|zip|7z|xsd|wsdl|xls|xlsx|xml|json|doc|docx|txt)(\?|$)", re.I)

SEDE = "https://sede.agenciatributaria.gob.es"
SEDE_HOST = urlparse(SEDE).netloc
AYUDA = SEDE + "/Sede/ayuda/consultas-informaticas"
AYUDA_INF = AYUDA + "/declaraciones-informativas-ayuda-tecnica"
# Carpeta del modelo 349 en la Sede (el nombre lleva caracteres sustituidos por guiones bajos, tal como lo publica la AEAT)
M349 = "modelo-349-decla_____n-recapitulativa-operaciones-intracomunitarias_"
GESTIONES = SEDE + "/Sede/todas-gestiones/impuestos-tasas/declaraciones-informativas/" + M349
IMPUESTOS = SEDE + "/Sede/impuestos-tasas/declaraciones-informativas/" + M349

# Páginas semilla: desde ellas se rastrean recursivamente las de CRAWL_PREFIXES.
PAGES = [
    # ficha del procedimiento GI28 (solo se guarda; sus enlaces a ficheros sí se descargan)
    SEDE + "/Sede/procedimientoini/GI28.shtml",
    # ayuda técnica del 349: formulario (hasta 40.000 registros) y fichero (TGVI online)
    AYUDA_INF + "/modelos-349-720/modelo-349.html",
    AYUDA_INF + "/modelos-349-720/modelo-349-presentacion-mediante-formulario.html",
    AYUDA_INF + "/modelos-349-720/modelo-349-presentacion-mediante-fichero.html",
    # ayuda técnica común a las declaraciones informativas y a la presentación
    AYUDA_INF + "/consulta-baja-declaraciones-informativas.html",
    AYUDA_INF + "/numero-justificante-debe-ser-cumplimentado-correcto.html",
    # consulta y baja de declaraciones de ejercicios anteriores a 2020 (las enlaza la página de ejercicios anteriores)
    AYUDA_INF + "/consultas-modificaciones-declaraciones-empresas-profesionales.html",
    AYUDA_INF + "/consultas-modificaciones-declaraciones-colaboradores.html",
    AYUDA_INF + "/consultas-modificaciones-declaraciones-apoderados.html",
    AYUDA + "/presentacion-declaraciones-ayuda-tecnica/presentacion-documentacion-complementaria.html",
    # plazos, nota informativa y ejercicios anteriores
    GESTIONES + "/plazos-presentacion.html",
    IMPUESTOS + "/nota-informativa.html",
    IMPUESTOS + "/ejercicios-anteriores.html",
    # diseños de registro: solo se descarga el del modelo 349 (ver FILTROS_ENLACES)
    SEDE + "/Sede/ayuda/disenos-registro/modelos-300-399.html",
]
# Secciones oficiales que se rastrean completas: se siguen los enlaces .html cuya ruta empieza por uno de estos prefijos.
CRAWL_PREFIXES = (
    "/Sede/ayuda/consultas-informaticas/declaraciones-informativas-ayuda-tecnica/modelos-349-720/modelo-349",
    "/Sede/impuestos-tasas/declaraciones-informativas/modelo-349-",
    "/Sede/todas-gestiones/impuestos-tasas/declaraciones-informativas/modelo-349-",
)
# Páginas que no se guardan (charlas informativas por comunidad, etc.)
EXCLUIR_PAGINAS = re.compile(r"charlas-informativas")
# Ficheros enlazados que no son del modelo 349 (notas de otros modelos que enlazan las páginas generales).
# Nunca coincide: se ajusta si una ejecución descubre ficheros ajenos (ver "enlaces" en .log_run.json).
EXCLUIR_ENLACES = re.compile(r"(?!x)x")
# Fichas .shtml (procedimientos): se guardan y se descargan sus ficheros, pero no se rastrean sus páginas.
SOLO_GUARDAR = re.compile(r"\.shtml$")
# De algunas páginas (listas de diseños de registro de todos los modelos) solo se descargan los enlaces que cumplan el filtro.
FILTROS_ENLACES = {
    "/Sede/ayuda/disenos-registro/modelos-300-399.html": re.compile(r"(?i)(?<!\d)349(?!\d)"),
}
MAX_PAGES = 100
DEV = "https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/"
ESPEC = DEV + "Informativas/Especificaciones_Modelos/"

DIRECT = [
    # diseño de registro (Orden HAC/174/2020, ejercicio 2020 y siguientes) e instrucciones de la ficha GI28
    # (se descubren también desde las páginas, pero se fijan aquí por si cambia el enlace)
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_20/DR_Anexo_349.pdf", "actual", None),
    (SEDE + "/static_files/Sede/Procedimiento_ayuda/GI28/instr_mod_349.pdf", "actual", None),
    # portal de desarrolladores (www.agenciatributaria.es): especificaciones del 349 (la de 2026 está publicada en la
    # carpeta 2025), anexos de errores de validación (XML), firma y envío del 349 y especificaciones comunes.
    # Su certificado (cadena FNMT) no lo verifica siempre curl: lo que falle va a la lista de descarga manual de SKILL.md.
    (ESPEC + "2025/espec_349_2026.pdf", "actual", None),
    (ESPEC + "2025/Espec-349-2025.pdf", "actual", None),
    (ESPEC + "2026/Anexo-errores-26/PAIN-M349_2026.xml", "actual", None),
    (ESPEC + "2027/Anexo-errores-27/PAIN-M349_2027.xml", "actual", None),
    (DEV + "Informativas/2020/espes349e20v10.pdf", "actual", None),
    (DEV + "Informativas/General/Especificaciones_TGVI_Online.pdf", "actual", None),
    (DEV + "General/EspecificacionesServiciosDeclaracionesAEAT_ES.pdf", "actual", None),
    (DEV + "General/consultas.xsd", "actual", None),
    # normativa: órdenes que aprueban y modifican el modelo 349 (los enlaces que da la Sede en la ficha GI28)
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2010-5098", "actual/normativa", "BOE-A-2010-5098_Orden-EHA-769-2010_mod349.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2010-18368", "actual/normativa", "BOE-A-2010-18368_Orden-EHA-3062-2010.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2011-10827", "actual/normativa", "BOE-A-2011-10827_Orden-EHA-1721-2011.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2012-15380", "actual/normativa", "BOE-A-2012-15380_Orden-HAP-2725-2012.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2013-12385", "actual/normativa", "BOE-A-2013-12385_Orden-HAP-2194-2013.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2020-2898", "actual/normativa", "BOE-A-2020-2898_Orden-HAC-174-2020.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2020-17269", "actual/normativa", "BOE-A-2020-17269_Orden-HAC-1274-2020.html"),
    # nota informativa de la Sede: origen de la reforma de 2020 (ventas en consigna, fin del periodo anual)
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2020-1651", "actual/normativa", "BOE-A-2020-1651_RDL-3-2020.html"),
    # base legal: Ley y Reglamento del IVA (declaración recapitulativa de operaciones intracomunitarias)
    ("https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740", "actual/normativa", "BOE-A-1992-28740_Ley-37-1992-IVA.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-1992-28925", "actual/normativa", "BOE-A-1992-28925_RD-1624-1992-Reglamento-IVA.html"),
]

mpath = os.path.join(ROOT, "manifest.json")
manifest = json.load(open(mpath)) if os.path.exists(mpath) else {}
log = {"nuevos": [], "modificados": [], "iguales": 0, "errores": [], "paginas_cambiadas": [], "paginas_nuevas": [],
       "paginas_desaparecidas": [], "enlaces": {}}


def fetch(url):
    """Descarga con /usr/bin/curl (SecureTransport de macOS). Devuelve código HTTP, cuerpo, cabeceras y error."""
    url = url.replace(" ", "%20")  # algunos enlaces de la Sede llevan espacios en el nombre del fichero
    hdr = os.path.join(ROOT, ".hdr.tmp"); body = os.path.join(ROOT, ".body.tmp")
    r = subprocess.run(["/usr/bin/curl", "-sS", "-L", "-m", "120", "-A", UA, "-D", hdr, "-o", body, "-w", "%{http_code}", url],
                       capture_output=True, text=True)
    code = r.stdout.strip()
    heads = {}
    if os.path.exists(hdr):
        blocks = [b for b in open(hdr, errors="ignore").read().split("\r\n\r\n") if b.strip()]
        for ln in (blocks[-1] if blocks else "").splitlines():
            if ":" in ln:
                k, v = ln.split(":", 1); heads[k.strip().lower()] = v.strip()
    data = open(body, "rb").read() if os.path.exists(body) else b""
    for f in (hdr, body):
        if os.path.exists(f): os.remove(f)
    return code, data, heads, r.stderr.strip()


def store(url, data, heads, dest_dir, fname):
    """Guarda el fichero en dest_dir; si ya existía y cambió, mueve el anterior a historico/AAAA-MM-DD/."""
    if fname.lower().endswith(".html"):
        # el BOE añade parámetros aleatorios (?rnd=NNN) a sus scripts: se eliminan para no detectar cambios falsos
        data = re.sub(rb"\?rnd=\d+", b"", data)
    sha = hashlib.sha256(data).hexdigest()
    os.makedirs(os.path.join(ROOT, dest_dir), exist_ok=True)
    rel = os.path.join(dest_dir, fname)
    old = manifest.get(url)
    if old and old["sha256"] == sha:
        old["comprobado"] = TODAY
        log["iguales"] += 1
        return "igual"
    status = "nuevo"
    if old and os.path.exists(os.path.join(ROOT, old["ruta"])):
        hd = os.path.join(ROOT, "historico", TODAY, os.path.dirname(old["ruta"]))
        os.makedirs(hd, exist_ok=True)
        shutil.move(os.path.join(ROOT, old["ruta"]), os.path.join(hd, os.path.basename(old["ruta"])))
        status = "modificado"
    open(os.path.join(ROOT, rel), "wb").write(data)
    manifest[url] = {"url": url, "ruta": rel, "sha256": sha, "tamano": len(data), "descargado": TODAY, "comprobado": TODAY,
                     "etag": heads.get("etag"), "last_modified": heads.get("last-modified")}
    log["nuevos" if status == "nuevo" else "modificados"].append(rel)
    return status


def to_text(h):
    """Convierte el HTML de una página en Markdown sencillo conservando los enlaces."""
    h = re.sub(r"(?is)<(script|style|noscript)[^>]*>.*?</\1>", "", h)
    h = re.sub(r"(?i)<br\s*/?>|</(p|div|li|tr|h\d|ul|ol|table)>", "\n", h)
    h = re.sub(r"(?is)<a\s[^>]*href=[\"']([^\"']+)[\"'][^>]*>(.*?)</a>",
               lambda m: "[%s](%s)" % (re.sub(r"<[^>]+>", "", m.group(2)).strip(), m.group(1)), h)
    h = re.sub(r"<[^>]+>", "", h)
    h = html.unescape(h)
    h = re.sub(r"[ \t\r\f\v]+", " ", h)
    h = re.sub(r"\n\s*\n+", "\n\n", h)
    return h.strip() + "\n"


def slug(url):
    """Nombre del Markdown de una página: su ruta en la sede, acortada con nombre_corto() si pasa de 60 caracteres."""
    ruta = urlparse(url).path.split("/Sede/")[-1]
    ruta = re.sub(r"\.s?html$", "", ruta)
    return nombre_corto(re.sub(r"[^A-Za-z0-9._-]+", "_", ruta) + ".md")


links = {}
# 1) páginas: rastreo recursivo dentro de CRAWL_PREFIXES a partir de las semillas
queue, visited, saved = list(PAGES), set(), set()
while queue and len(visited) < MAX_PAGES:
    url = queue.pop(0)
    if url in visited: continue
    visited.add(url)
    code, data, heads, err = fetch(url)
    if code != "200" or not data:
        log["errores"].append((url, code, err)); continue
    h = data.decode("utf-8", errors="ignore")
    txt = "<!-- Fuente: %s -->\n\n" % url + to_text(h)
    fname = slug(url)
    saved.add(fname)
    pth = os.path.join(ROOT, "paginas", fname)
    os.makedirs(os.path.dirname(pth), exist_ok=True)
    prev = open(pth).read() if os.path.exists(pth) else None
    if prev is None: log["paginas_nuevas"].append(fname)
    elif prev != txt: log["paginas_cambiadas"].append(fname)
    open(pth, "w").write(txt)
    m = re.search(r"(?i)p[aá]gina actualizada[^\n]{0,80}", txt)
    if m: log.setdefault("actualizada", {})[fname] = re.sub(r"\s+", " ", m.group(0)).strip()
    seguir = not SOLO_GUARDAR.search(urlparse(url).path)
    filtro = FILTROS_ENLACES.get(urlparse(url).path)
    for href in re.findall(r"href=[\"']([^\"'#]+)[\"']", h):
        full = urljoin(url, html.unescape(href))
        pu = urlparse(full)
        if seguir and pu.netloc == SEDE_HOST and pu.path.endswith(".html") and pu.path.startswith(CRAWL_PREFIXES) \
                and not EXCLUIR_PAGINAS.search(pu.path):
            clean = SEDE + pu.path
            if clean not in visited and clean not in queue: queue.append(clean)
        elif pu.netloc in ALLOWED and EXT.search(pu.path):
            if (filtro is None or filtro.search(os.path.basename(pu.path))) and not EXCLUIR_ENLACES.search(os.path.basename(pu.path)):
                links.setdefault(full, url)
if queue:
    log["errores"].append(("rastreo", "", "se alcanzó MAX_PAGES=%d; quedan %d páginas sin revisar" % (MAX_PAGES, len(queue))))
# páginas guardadas en ejecuciones anteriores que ya no se encuentran (borradas o sin enlace)
if os.path.isdir(os.path.join(ROOT, "paginas")):
    for f in sorted(os.listdir(os.path.join(ROOT, "paginas"))):
        if f.endswith(".md") and f not in saved:
            log["paginas_desaparecidas"].append(f)

# 2) ficheros directos
direct_urls = set()
for item in DIRECT:
    url, d = item[0], item[1]
    fname = nombre_corto(item[2] if len(item) > 2 and item[2] else unquote(os.path.basename(urlparse(url).path)))
    direct_urls.add(url)
    code, data, heads, err = fetch(url)
    if code != "200" or not data:
        log["errores"].append((url, code, err)); continue
    if fname.lower().endswith((".xsd", ".wsdl", ".xml")) and not data.lstrip(b"\xef\xbb\xbf \r\n\t").startswith(b"<?xml"):
        log["errores"].append((url, code, "no empieza por <?xml (posible HTML de mantenimiento); no guardado")); continue
    store(url, data, heads, d, fname)


# 3) enlaces extraídos de las páginas que no estén ya en la lista de ficheros directos
def norm(u): return u.split("?")[0].lower()


known = {norm(u) for u in direct_urls}
for full, origen in sorted(links.items()):
    if norm(full) in known: continue
    fname = nombre_corto(unquote(os.path.basename(urlparse(full).path)).replace(" ", "_"))
    code, data, heads, err = fetch(full)
    if code != "200" or not data:
        log["errores"].append((full, code, err)); continue
    store(full, data, heads, "actual", fname)
    log["enlaces"][full] = origen

json.dump(manifest, open(mpath, "w"), indent=1, ensure_ascii=False)
log["nombres_largos"] = [rel for rel, _ in avisar(ROOT)]
json.dump(log, open(os.path.join(ROOT, ".log_run.json"), "w"), indent=1, ensure_ascii=False)
print(json.dumps({k: (v if k not in ("enlaces", "actualizada") else len(v)) for k, v in log.items()}, indent=1, ensure_ascii=False))
