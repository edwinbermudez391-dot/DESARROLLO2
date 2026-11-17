# Evidencias verificadas

Este directorio contiene registros obtenidos durante la verificación real. Las capturas
se conservan únicamente en la carpeta local y se excluyen del repositorio público y del ZIP.

- Captura local `catalogo-territorial.jpg`: selección de una comuna y de su barrio oficial en ventanilla.
- Captura local `admin-sedes.jpg`: referencias territoriales y configuración de sedes en administración.
- `local/`: salidas íntegras de las pruebas, con fecha UTC y hashes SHA-256 en `manifest.json`.
- `docker.txt`: comprobaciones ejecutadas dentro de Docker con PHP 8.2/MySQL 8.0.

Reproducir los registros locales:

```powershell
powershell -File scripts/verify.ps1
```

Las capturas corresponden al sistema local y usan cuentas de demostración. No se
incluyen exportaciones de la base de datos actual ni datos nuevos de ciudadanos.
Los resultados del pipeline remoto se consultan en
[GitHub Actions](https://github.com/edwinbermudez391-dot/DESARROLLO2/actions).
Los enlaces específicos de run y commit se agregan únicamente después de observarlos.

La evidencia Jira requiere el proyecto real del equipo. `../backlog_jira.json` es un
backlog preparado; no es una exportación de tickets creados ni un historial de reuniones.
