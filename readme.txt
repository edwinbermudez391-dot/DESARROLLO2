ENCUESTAS CALI - INSTALACION Y USO

Requisitos: PHP 8.0 o superior, PDO MySQL, iconv, Apache y MySQL/MariaDB.

XAMPP (base ya existente):
1. Iniciar Apache y MySQL desde XAMPP.
2. Revisar config/database.php. Admite DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS por entorno.
3. En la carpeta del proyecto ejecutar: php scripts/migrate_entrega2.php
   La migracion agrega tablas y columnas; no borra datos y se puede repetir.
4. Abrir http://localhost/ProyectoDesarrollo2/public/index.php?route=login

Instalacion nueva:
1. Importar DATABASE_MODELO_ENTREGA_1.sql y luego SEEDERS_ENTREGA_1.sql una sola vez.
2. Ejecutar python scripts/configure_docker.py, luego php scripts/migrate_entrega2.php
   y php scripts/create_demo_users.php. La clave de demostracion queda en .env.
3. Cuentas de demostracion: admin, ventanilla, encuestador, calidad, enrutador.
   Contrasena de los seeders: la clave privada configurada en .env. Las cuentas existentes conservan su clave.
   No reimportar seeders ni ejecutar restablecimientos sobre una base con datos.

Docker, entorno local separado de XAMPP:
  python scripts/configure_docker.py
  docker compose up --build -d --wait
  Abrir http://localhost:18080/index.php?route=login
  docker compose down
Los datos persisten en el volumen mysql_data. No usar down -v si desea conservarlos.
Las claves se toman del archivo privado .env; este archivo se excluye del repositorio.

Flujo:
- Ventanilla selecciona sede de radicacion, sector, datos del ciudadano y punto del mapa.
  Solicitud, coordenadas, consecutivo y auditoria se guardan en una transaccion.
- Enrutador consulta bitacora diaria y selecciona 20 solicitudes ubicadas y sin asignacion.
  Escoge encuestador, sede de entrega y fecha de inicio desde el dia siguiente.
- Ventanilla selecciona el punto de entrega y registra la entrega en la manana del dia programado.
- Encuestador consulta solo sus rutas, su detalle y descarga la hoja en PDF.
- Administrador consulta indicadores reales y descarga el informe PDF.
- Calidad dispone de indicadores. El diligenciamiento y revision digital de encuestas
  no forman parte de los nueve requisitos evaluables y no estan implementados.

Pruebas:
  php tests/role_access.php
  node tests/role_frontend.mjs
  php tests/api_access.php
  php tests/integration.php
Integracion crea una base encuestas_test_<pid>_<aleatorio> y la elimina al finalizar.
Requiere una cuenta local con permiso CREATE/DROP DATABASE. No toca encuestas_cali.

Limitaciones y decisiones:
- Se usan las ocho sedes enumeradas en el requisito 5. Sus direcciones y coordenadas
  de referencia provienen de etiquetas de mapas oficiales IDESC. El administrador puede
  configurar la direccion y coordenadas reales, indicando fuente de verificacion.
- La hoja contiene exactamente 20 encuestas. La fecha indica inicio de carga;
  no se afirma que 20 visitas puedan terminarse en una jornada.
- No hay optimizacion automatica de recorridos: el orden es el de antiguedad de solicitudes.
- Catalogo IDESC 2026: 340 barrios/sectores, 22 comunas y 15 corregimientos.
  El barrio urbano se selecciona por comuna y el servidor valida su pertenencia.
  En zona rural se escribe la vereda o sector y se deriva el corregimiento seleccionado.
- Registros antiguos conservan sede NULL: no se atribuyen a una sede sin evidencia.
- Las asignaciones antiguas sin hoja de ruta se conservan y no se reasignan automaticamente.
- La capa visual y mapa usan CDN y teselas externas: requieren internet.
- Los reportes muestran estado actual, no resultados de elegibilidad de ciudadanos.
- Docker se verifico localmente. Consultar docs/evidencias/README.md para los runs reales de CI.

Entregables academicos:
Consultar docs/ENTREGA_2.md para historias de usuario, criterios, modelo y verificaciones.
Informe vigente: docs/INFORME_FINAL.md. Backlog preparado: docs/backlog_jira.json.
El equipo debe aportar sus autores, actas reales y acceso al proyecto Jira.
No se fabrican bitacoras ni resultados de ejecuciones externas.
