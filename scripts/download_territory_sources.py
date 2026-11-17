"""Descarga reproducible de mapas oficiales IDESC. No requiere credenciales."""
import concurrent.futures
import pathlib
import urllib.request
import sys
import hashlib
import json
out = pathlib.Path(sys.argv[1])
out.mkdir(parents=True, exist_ok=True)
def fetch(n):
    url = f'https://idesc.cali.gov.co/download/mapas_comunas/comuna_{n:02d}.pdf'
    path = out / f'comuna_{n:02d}.pdf'
    with urllib.request.urlopen(url, timeout=60) as response:
        body = response.read()
    if not body.startswith(b'%PDF-'):
        raise ValueError(f'El recurso no es PDF: {url}')
    path.write_bytes(body)
    return {'comuna': n, 'url': url, 'sha256': hashlib.sha256(body).hexdigest(), 'bytes': len(body)}
with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
    entries=list(pool.map(fetch,range(1,23)))
(out/'manifest.json').write_text(json.dumps(entries,ensure_ascii=False,indent=2),encoding='utf-8')
print(f'{len(entries)} mapas oficiales descargados; {sum(x["bytes"] for x in entries)} bytes.')

with urllib.request.urlopen('https://indicadores.cali.gov.co/api/get_capas_geograficas/', timeout=60) as response:
    capas=response.read()
json.loads(capas)
(out/'capas.json').write_bytes(capas)
print('Catálogo municipal de capas guardado en capas.json.')
