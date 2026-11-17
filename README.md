# Encuestas Cali · Desarrollo de Software II

Aplicación PHP/MySQL para radicar solicitudes, georreferenciarlas y organizar hojas de ruta.

## Ejecución con XAMPP

```powershell
python scripts/configure_docker.py
php scripts/migrate_entrega2.php
php scripts/create_demo_users.php
```

Abrir [el login local](http://localhost/ProyectoDesarrollo2/public/index.php?route=login).
En una instalación nueva, importar primero `DATABASE_MODELO_ENTREGA_1.sql` y
`SEEDERS_ENTREGA_1.sql`. No reimportarlos sobre una base con información existente.

## Ejecución con Docker

```sh
python scripts/configure_docker.py
docker compose up --build -d --wait
```

Abrir [localhost:18080](http://localhost:18080/index.php?route=login).
`docker compose down` detiene el entorno y conserva el volumen de datos.

Cuentas de demostración: `admin`, `ventanilla`, `encuestador`, `calidad` y `enrutador`.
Las cuentas se crean desde `DEMO_PASSWORD`, guardada localmente en `.env`; no se publican
contraseñas ni hashes. Las cuentas existentes conservan sus claves.

## Funciones

- Acceso y autorización con cinco perfiles.
- Registro por sede, comuna/corregimiento y barrio oficial.
- Catálogo local de 340 barrios/sectores, 22 comunas y 15 corregimientos.
- Mapa interactivo, GPS y referencias territoriales de mapas IDESC.
- Bitácora diaria y hojas de ruta de 20 solicitudes sin doble asignación.
- Entrega auditada por ventanilla y consulta privada del encuestador.
- Hojas e informe administrativo en PDF; indicadores con datos reales.
- Configuración auditada de la dirección, coordenadas y fuente de cada sede.

## Verificación

```sh
php tests/role_access.php
node tests/role_frontend.mjs
php tests/api_access.php
php tests/integration.php
```

La integración crea una base efímera separada y la elimina al terminar. Necesita permisos
CREATE/DROP DATABASE en el entorno de pruebas. El workflow en `.github/workflows/ci.yml`
ejecuta estas verificaciones con PHP 8.2 y MySQL 8.0 dentro de Docker.

## Documentación y evidencia

- [Instalación y uso](readme.txt)
- [Informe final y trazabilidad](docs/INFORME_FINAL.md)
- [Implementación de entrega 2](docs/ENTREGA_2.md)
- [Fuentes territoriales y procedimiento de extracción](data/territorio/README.md)
- [Evidencias reales](docs/evidencias/README.md)
- [Backlog para registrar en Jira](docs/backlog_jira.json)

Las referencias de barrio no son domicilios. Las sedes del caso académico deben
configurarse con la dirección real de la entidad antes de utilizarlas en operación.
Los registros históricos no se reclasifican automáticamente. El sistema no incluye
optimización automática ni diligenciamiento/revisión digital de la encuesta completa.
