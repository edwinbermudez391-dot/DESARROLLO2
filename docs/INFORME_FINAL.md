# Informe final de implementación

**Proyecto:** Desarrollo de Software II — Encuestas Cali.
**Repositorio:** https://github.com/edwinbermudez391-dot/DESARROLLO2
**Fecha de verificación:** 16 de noviembre de 2026.

## Problema y alcance

La entidad recibe solicitudes de encuesta y necesita controlar su ubicación, reparto y
entrega de cargas dentro de un proceso cuyo plazo de referencia es de 15 días calendario.
La aplicación implementa los nueve puntos funcionales del enunciado. Los perfiles de
calidad y encuestador tienen acceso propio, pero no se incorporó el cuestionario ni un
motor que decida la aptitud de un ciudadano para recibir un préstamo.

## Trazabilidad de requisitos

| Punto | Resultado implementado | Evidencia técnica |
|---|---|---|
| 1 | Login para administrador, ventanilla, encuestador, calidad y enrutador | Auth, sesiones PHP, 46 comprobaciones de acceso |
| 2 | Radicación con datos personales, sede y sector territorial validado | TerritoryModel, RequestModel y pruebas de rechazo de barrios ajenos |
| 3 | Captura de coordenadas con mapa/GPS y guardado atómico | ventanilla.js y pruebas de coordenadas |
| 4 | Bitácora de solicitudes del día, con filtros por comuna y estado | RequestController::today y enrutador.js |
| 5 | Ocho sedes en las comunas enumeradas, con referencia y configuración administrativa | mapa, guardar-sede y auditoría |
| 6 | Hoja de 20 solicitudes, encuestador, orden, fecha y punto de entrega | transacción y bloqueo de filas en OperationModel |
| 7 | Registro de entrega en la mañana programada y consulta del encuestador | entrega única, actor, hora y restricción de propiedad |
| 8 | Informe administrativo y hojas de ruta en PDF descargable | PdfReport y exportaciones protegidas por rol |
| 9 | Dashboard de solicitudes, vencimientos, estados, sedes y entregas | consultas de agregación sobre datos persistidos |

Se interpretan las 20 encuestas como carga de una hoja con fecha de inicio. La capacidad
operativa de 10 visitas urbanas o 5 rurales al día no permite afirmar que la hoja completa
se ejecute en una sola jornada. Las ocho sedes explícitas del requisito 5 se implementan
sin reducirlas a las cinco citadas en el contexto. Estas decisiones están documentadas
para confirmarlas con el docente o responsable funcional.

## Tecnologías y arquitectura

PHP 8+, PDO con MySQL/MariaDB, HTML, CSS, módulos JavaScript y Leaflet/OpenStreetMap.
Tailwind y otros recursos de presentación se cargan desde CDN. Apache publica el
Front Controller `public/index.php`; controladores validan HTTP/autorización y modelos
realizan consultas, reglas de negocio y transacciones. La configuración admite variables
de entorno y Docker sirve únicamente el directorio público.

El PDF se genera en PHP sin un servicio externo. Implementa texto WinAnsi, paginación,
encabezado y numeración; cada visita permanece en la misma página. No soporta todos los
alfabetos Unicode ni pretende ser un formulario PDF interactivo.

## Datos y migraciones

El modelo incluye roles, usuarios, comunas/corregimientos, barrios, estados, solicitudes,
sedes, hojas de ruta, asignaciones, consecutivos y auditoría. El diagrama y las relaciones
principales se documentan en `ENTREGA_2.md`. Se añaden:

- `barrios`: código oficial, sector, nombre, fuente y coordenada de referencia.
- `solicitudes.id_barrio`: permite validar pertenencia en registros nuevos urbanos.
- Campos de fuente y confirmación de ubicación de la sede.
- `migraciones_datos`: copia de los sectores y sedes anteriores a la actualización.

Se cargan 22 comunas, 15 corregimientos y 340 etiquetas de barrio/sector desde fuentes
municipales. Las referencias históricas Ponce y Playa Rica no se convierten en otros
sectores: se conservan inactivas. Pichindé se vincula por su nombre normalizado al catálogo.
Los registros antiguos preservan su texto y FK; cualquier corrección de una solicitud
histórica requiere una revisión explícita, no una reasignación automática.

Las migraciones son aditivas e idempotentes. DDL de MySQL hace commits implícitos;
por eso no se presenta la migración completa como una transacción reversible.
El origen y SHA-256 de los mapas están en `data/territorio/fuentes.json`.

## Seguridad y calidad

- Autorización de vistas y API en el servidor; el encuestador consulta solo sus hojas.
- Verificación de hashes, renovación del ID de sesión y cookies HttpOnly/SameSite.
- Consultas preparadas, validación de tipos, teléfono, longitudes y coordenadas.
- Escape de datos antes de insertarlos en HTML.
- Bloqueos y transacciones para consecutivos, asignación, entrega y configuración de sedes.
- Zona horaria de Colombia en PHP y en la conexión MySQL.
- Restricción HTTP de configuración, fuentes de datos, scripts, pruebas y documentación.

Los PDF contienen datos de contacto y solo están disponibles para los roles permitidos.
La configuración por defecto es de desarrollo. Un despliegue público requiere credenciales
propias, HTTPS y configuración del entorno; no se expuso una instancia con datos existentes.

## Metodología Scrum: trabajo comprobable y propuesta de seguimiento

El backlog funcional está preparado en `backlog_jira.json`. La planificación de entregas
se organiza en base (puntos 1–4), operación (5–7) e indicadores/evidencia (8–9). Los criterios
de aceptación y definición de terminado exigen pruebas, revisión de interfaz y documentación.

No se atribuyen reuniones, responsables, horas, aprobaciones del docente ni sesiones Daily
Scrum que no hayan sido registradas por el equipo. El equipo debe incorporar sus nombres y
sus actas reales al proyecto Jira. El archivo de backlog es preparación de seguimiento,
no evidencia de que existan tickets remotos o un sprint cerrado.

Retrospectiva técnica verificable de esta implementación:

1. El puerto 8080 fue rechazado por Windows; se comprobó y utilizó 18080 para Docker.
2. El portal de datos principal estaba suspendido; se usaron mapas GeoPDF y API oficiales
   accesibles, conservando sus URL y huellas para reproducir la extracción.
3. Los GeoPDF usan espaciado de letras en algunas comunas; el extractor lo normaliza y
   separa los códigos de los nombres. El catálogo final se revisó por comuna.
4. Una prueba detectó contaminación de variables al incluir migraciones; se aislaron en
   cierres y se comprobó la repetición de migraciones y la limpieza de la base efímera.
5. Los valores geográficos ficticios de los seeders se distinguieron de ubicaciones reales;
   se añadió configuración auditada para que la entidad aporte sus direcciones.

## DevOps y pruebas

GitHub contiene la versión reproducible y un workflow de integración con Docker,
PHP 8.2 y MySQL 8.0. Los registros locales se generan con `scripts/verify.ps1` y el workflow
sube sus salidas como un artifact de GitHub Actions. `docs/evidencias/README.md` enlaza
los runs y commits que hayan sido realmente observados, distinguiéndolos de la prueba local.

Las pruebas cubren roles/sesiones, 60 combinaciones de API, catálogo y pertenencia de barrios,
registro atómico, tres registros concurrentes, cargas de 20, propiedad de hojas, duplicidad,
ventana de entrega con reloj de prueba, dashboard y PDF. La integración utiliza una base
separada de la aplicación y elimina únicamente esa base al finalizar.

Para la sustentación se incluyen código fuente, instalación, informe, catálogo de fuentes,
pruebas y evidencia de Docker/Git/CI. Jira requiere acceso al proyecto del equipo; no se
simula su actividad. La aplicación está disponible por URL local y no se ha contratado ni
publicado hosting externo.

## Publicación del código

Los seeders públicos contienen catálogos y referencias territoriales públicas. No incluyen
ciudadanos, contactos, hashes de contraseña ni claves fijas. Las cuentas de demostración
se crean localmente con una clave privada del entorno. Las pruebas generan sus cuentas
en una base efímera y utilizan contactos sintéticos de ceros. Los archivos históricos
con ejemplos de credenciales y las capturas se conservan fuera de la publicación.
