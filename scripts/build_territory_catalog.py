"""Extrae catálogo de los mapas GeoPDF oficiales, conservando códigos, fuente y referencia."""
from pathlib import Path
from pypdf import PdfReader, overwrite_configuration
overwrite_configuration(stream_parsing_work_maximum_count=12000000)
import hashlib, json, re, sys
folder=Path(sys.argv[1]); output=Path('data/territorio');output.mkdir(parents=True,exist_ok=True)
records={};manifest=json.loads((folder/'manifest.json').read_text(encoding='utf-8'))
for n in range(1,23):
    page=PdfReader(folder/f'comuna_{n:02d}.pdf').pages[0]
    vp=page['/VP'][0];box=[float(x) for x in vp['/BBox']];measure=vp['/Measure'].get_object()
    gps=[float(x) for x in measure['/GPTS']];local=[float(x) for x in measure['/LPTS']]
    chunks=[]
    def visit(text,cm,tm,font,size):
        if not font or 'Arial-BoldMT' not in str(font.get('/BaseFont')):return
        if not text.strip():return
        if re.search(r'\b(?:\w ){3,}\w\b',text):
            text=re.sub(r' {2,}', '\u0000',text).replace(' ','').replace('\u0000',' ')
        chunks.append((text.strip(),float(tm[4]),float(tm[5]),float(tm[0])))
    page.extract_text(visitor_text=visit)
    pending=[]
    for text,x,y,size in chunks:
        # Las etiquetas de barrios emplean la misma tipografía; el código cierra cada etiqueta.
        if pending and (abs(size-pending[-1][3])>.05 or abs(y-pending[-1][2])>35 or abs(x-pending[-1][1])>180):pending=[]
        pending.append((text,x,y,size))
        match=re.search(r'(?<!\d)(\d{4})\s*$',text)
        if not match:continue
        code=match.group(1)
        name=' '.join(p[0] for p in pending)
        name=re.sub(r'\s*-?\s*'+code+r'\s*$','',name).strip(' -')
        pending=[]
        if not code.startswith(f'{n:02d}') or not name or len(name)>100:continue
        # El GeoPDF define coordenadas geográficas de las esquinas de su viewport.
        u=(x-box[0])/(box[2]-box[0]);v=(y-box[1])/(box[3]-box[1])
        # LPTS usa [0,1],[0,0],[1,0],[1,1]. Interpolación bilineal del mapa norte-arriba.
        lat=(1-u)*((1-v)*gps[2]+v*gps[0])+u*((1-v)*gps[4]+v*gps[6])
        lng=(1-u)*((1-v)*gps[3]+v*gps[1])+u*((1-v)*gps[5]+v*gps[7])
        records[code]={'codigo':code,'nombre':name,'comuna':f'{n:02d}','latitud_referencia':round(lat,7),'longitud_referencia':round(lng,7),'fuente':manifest[n-1]['url']}
    print(f'Comuna {n:02d}: {sum(1 for r in records.values() if r["comuna"]==f"{n:02d}")} barrios/sectores')
if len(records)<300:raise ValueError(f'Extracción incompleta: {len(records)}')
catalog={'version':'IDESC-mapas-2026','descripcion':'Catálogo transcrito de etiquetas de los mapas GeoPDF IDESC. Los puntos interpolados son referencias de etiquetas, no domicilios ni límites de barrio.','barrios':sorted(records.values(),key=lambda r:r['codigo'])}
(output/'barrios.json').write_text(json.dumps(catalog,ensure_ascii=False,indent=2),encoding='utf-8')
(output/'fuentes.json').write_text(json.dumps({'consultado':'2026-11-16','mapas':manifest,'sectores_api':'https://indicadores.cali.gov.co/api/get_capas_geograficas/'},ensure_ascii=False,indent=2),encoding='utf-8')
# Listado oficial por nombre; los códigos rurales internos no se presentan como códigos DANE.
capas=json.loads((folder/'capas.json').read_text(encoding='utf-8-sig'))
rural=sorted({r['nombre'] for r in capas if r['nivel_geografico_id']==4 and not r['nombre'].startswith('Comuna ')})
assert len(rural)==15,rural
(output/'corregimientos.json').write_text(json.dumps(rural,ensure_ascii=False,indent=2),encoding='utf-8')
print('TOTAL',len(records),'barrios y sectores;',len(rural),'corregimientos')
