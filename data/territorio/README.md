# Catálogo territorial de Cali

Fuente: **Infraestructura de Datos Espaciales de Santiago de Cali (IDESC)**,
Departamento Administrativo de Planeación, Alcaldía de Santiago de Cali.
Consulta: 16 de noviembre de 2026.

- Mapas oficiales: `https://idesc.cali.gov.co/download/mapas_comunas/comuna_01.pdf`
  a `comuna_22.pdf`, según las URL exactas de `fuentes.json`.
- [Catálogo de capas geográficas del SIIED](https://indicadores.cali.gov.co/api/get_capas_geograficas/).
- [Documentación del API municipal](https://indicadores.cali.gov.co/api-documentation/).
- [Ficha de barrios en Datos Abiertos Cali](https://datos.cali.gov.co/dataset/servicio-wms-barrios-de-cali),
  que declara atribución Creative Commons Attribution y actualización de 2026.

`barrios.json` contiene **340 etiquetas de barrios/sectores** extraídas de los mapas
2026 de las 22 comunas. Conserva código, nombre, comuna y URL de origen de cada etiqueta.
No se deduce pertenencia por similitud de nombres ni por una lista de un tercero.
`corregimientos.json` contiene 15 nombres únicos del catálogo municipal.
Los códigos internos rurales R01–R15 son identificadores del sistema; no son códigos DANE.

Las coordenadas de referencia se interpolan desde la posición de la etiqueta y el
viewport georreferenciado `/VP/Measure` del propio GeoPDF. Son **referencias aproximadas**
del mapa para centrar la interfaz; no son polígonos, límites oficiales, centros exactos,
direcciones de oficinas ni puntos medidos del domicilio del ciudadano.
Seleccionar un barrio centra el mapa, pero no guarda coordenadas hasta una acción explícita.

`fuentes.json` incluye URL, tamaño y SHA-256 de cada PDF descargado. Los 93 MB de PDF
fuente se conservaron en el área temporal de trabajo; no se incorporan al repositorio.
La extracción se puede reproducir con Python y `pypdf`:

```sh
python -m pip install -r scripts/requirements-territory.txt
python scripts/download_territory_sources.py /ruta/temporal/fuentes
python scripts/build_territory_catalog.py /ruta/temporal/fuentes
```

El catálogo se sirve desde la base de datos local; no se consulta el API municipal
al registrar solicitudes. La consulta del portal CKAN principal encontró un comunicado
oficial de suspensión temporal de servicios, por lo que se utilizaron los GeoPDF y el
API municipal accesibles. Las actualizaciones deben volver a comprobar las fuentes y
revisar las diferencias antes de ejecutar la migración.
