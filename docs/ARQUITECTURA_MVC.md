> Actualización entrega 2: consultar [ENTREGA_2.md](ENTREGA_2.md) y `readme.txt` para las nuevas rutas, migración y módulos. Las secciones siguientes describen la reorganización de la entrega 1.

# Arquitectura MVC

## Estructura implementada

```text
ProyectoDesarrollo2/
├── .htaccess                      # Bloquea directorios privados y redirige a public/
├── config/
│   ├── config.php                 # Entorno, roles y constantes de sesión
│   └── database.php               # Parámetros de MySQL, sin conexión ni respuestas HTTP
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php     # Login y logout
│   │   ├── ComunaController.php   # Catálogo de comunas
│   │   ├── RequestController.php  # Registro, geolocalización y bitácora
│   │   └── ViewController.php     # Vistas permitidas y control de acceso
│   ├── Models/
│   │   ├── UserModel.php          # Consultas de usuarios y último acceso
│   │   ├── ComunaModel.php        # Consultas de comunas
│   │   └── RequestModel.php       # Solicitudes, reglas de datos y auditoría
│   └── Views/
│       ├── login.html
│       ├── ventanilla.html
│       ├── enrutador.html
│       └── test_login.html
├── core/
│   ├── Auth.php                   # Verificación de contraseñas, sesiones y permisos
│   ├── Database.php               # Singleton PDO; sin salida HTTP
│   ├── Controller.php             # Lectura de entrada y respuestas JSON
│   └── HttpException.php          # Errores HTTP controlados
├── public/
│   ├── index.php                  # Único punto de entrada PHP público
│   ├── .htaccess                  # URLs limpias opcionales
│   └── assets/
│       ├── css/style.css
│       └── js/
│           ├── config.js
│           ├── api.js
│           ├── auth.js
│           ├── ui.js
│           ├── ventanilla.js
│           └── enrutador.js
├── docs/
│   ├── ARQUITECTURA_MVC.md
│   └── HISTORICO_INTEGRACION_ENTREGA_1.md
├── DATABASE_MODELO_ENTREGA_1.sql
├── SEEDERS_ENTREGA_1.sql
├── ACTUALIZAR_PASSWORD_VENTANILLA.sql
└── DOCUMENTACION_METODOLOGICA_ENTREGA_1.md
```

Flujo: navegador → `public/index.php` → controlador → modelo → MySQL.
El controlador devuelve JSON o sirve una vista de la lista permitida. Las vistas
no ejecutan SQL; los modelos no leen `$_GET`, `$_POST` ni `$_SERVER`, ni emiten HTTP.
`Auth` delega el SQL en `UserModel`; lanza errores que el Front Controller convierte
en JSON. Los errores internos se registran sin exponer consultas ni credenciales.

## Movimientos realizados, paso a paso

1. `backend/config/config.php` → `config/config.php`. Se extrajeron los parámetros
   MySQL a `config/database.php` y se eliminó la cabecera JSON de la configuración.
2. `backend/config/database.php` → `core/Database.php`: contiene la conexión PDO,
   no los parámetros. Una falla lanza una excepción en vez de imprimir JSON y salir.
3. `backend/classes/RequestModel.php` → `app/Models/RequestModel.php`. La IP de
   auditoría llega desde el controlador, no desde variables HTTP dentro del modelo.
4. `backend/includes/Auth.php` → `core/Auth.php`. Su SQL se extrajo a
   `app/Models/UserModel.php` y la emisión HTTP se delegó al Front Controller.
5. `backend/api/login.php` y `logout.php` → métodos `login()` y `logout()` de
   `app/Controllers/AuthController.php`. Se retiraron los scripts públicos antiguos.
6. `backend/api/comunas.php` → `ComunaController::index()` y `ComunaModel::getActive()`.
7. `backend/api/save_request.php`, `save_geolocation.php` y `get_today_requests.php`
   → `RequestController::store()`, `geolocation()` y `today()`.
8. `frontend/*.html` → `app/Views/*.html`; `frontend/assets/` → `public/assets/`.
9. Se crearon `public/index.php`, los `.htaccess`, `ViewController`, `Controller`
   y `HttpException`. Los enlaces entre vistas pasan por `index.php?route=...`.
10. La integración anterior se conservó como documento histórico en `docs/`.

No volver a crear scripts PHP de API separados: registrar nuevas rutas en
`public/index.php` y añadir métodos en los controladores.

## Rutas y equivalencias

Base local: `http://localhost/ProyectoDesarrollo2/public/`.
Las rutas con query string no necesitan `mod_rewrite`; las limpias sí.

| Antes | Ahora (relativa a public/) | Método / controlador |
|---|---|---|
| `frontend/login.html` | `index.php?route=login` | GET / ViewController |
| `frontend/ventanilla.html` | `index.php?route=ventanilla` | GET / ViewController |
| `frontend/enrutador.html` | `index.php?route=enrutador` | GET / ViewController |
| `frontend/test_login.html` | `index.php?route=test-login` | GET / ViewController |
| `backend/api/login.php` | `index.php?route=api/login` | POST / AuthController::login |
| `backend/api/logout.php` | `index.php?route=api/logout` | POST / AuthController::logout |
| `backend/api/comunas.php` | `index.php?route=api/comunas` | GET / ComunaController::index |
| `backend/api/save_request.php` | `index.php?route=api/solicitudes` | POST / RequestController::store |
| `backend/api/save_geolocation.php` | `index.php?route=api/solicitudes/geolocalizacion` | POST / RequestController::geolocation |
| `backend/api/get_today_requests.php` | `index.php?route=api/solicitudes/hoy` | GET / RequestController::today |

Ejemplo de filtro: `index.php?route=api/solicitudes/hoy&comuna=1&estado=2`.
Las rutas antiguas se retiraron deliberadamente, sin adaptadores que evadan el
Front Controller. Actualizar marcadores e integraciones externas.

El login deja de aceptar GET para evitar contraseñas en historial y registros.
Acepta JSON o formulario POST, conserva los alias `usuario`/`correo` y `clave`.
Logout requiere POST y sesión activa. Los roles autorizados para solicitudes
siguen siendo los mismos. El rol Encuestador todavía no tiene una vista propia
y no tiene permiso para consultar la bitácora; no se le concedieron permisos nuevos.

## Cambios específicos de importación

### Front Controller (sustituye a login.php como punto de entrada)

```php
// public/index.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/HttpException.php';
// La clase y acción se obtienen de un mapa fijo, nunca de una ruta de archivo del cliente.
require_once __DIR__ . '/../app/Controllers/' . $controller . '.php';
(new $controller())->$action();
```

### Login y Auth

```php
// app/Controllers/AuthController.php
require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';

// core/Auth.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Models/UserModel.php';
require_once __DIR__ . '/HttpException.php';
```

### Configuración y modelos

```php
// config/config.php: parámetros sin abrir la conexión
require_once __DIR__ . '/database.php';

// core/Database.php: carga constantes antes de conectar
require_once __DIR__ . '/../config/config.php';

// app/Models/{UserModel,ComunaModel,RequestModel}.php
require_once __DIR__ . '/../../core/Database.php';
```

### AJAX y recursos del cliente

```js
// public/assets/js/config.js
API_BASE: new URL('../../index.php?route=api', import.meta.url).href,
// ENDPOINTS.LOGIN: '/login'

// public/assets/js/api.js
fetch(`${CONFIG.API_BASE}${endpoint}`, {
  ...options,
  headers,
  credentials: 'include',
});
```

La URL se calcula desde el módulo y conserva el mismo origen, puerto y carpeta
de despliegue. No depende de `http://localhost` fijo ni requiere CORS permisivo.
Los filtros se agregan con `&`, pues `route` ya ocupa el query string.
Los imports `./api.js` y `./auth.js` siguen siendo relativos entre módulos.
Las vistas referencian `./assets/...`, resueltos desde la URL pública que las sirve.

## Ejecución y seguridad

1. Iniciar Apache y MySQL en XAMPP. Requiere PHP 8.0+ (el modelo existente usa
   argumentos nombrados y tipos unión), PDO MySQL y `AllowOverride All`.
2. Usar la base existente `encuestas_cali`; la reorganización no cambia datos.
   En una instalación nueva importar DDL y seeders. No reimportar los seeders
   sobre datos existentes: sus INSERT no son una migración idempotente.
3. Para reparar solo la contraseña existente de ventanilla, ejecutar
   `ACTUALIZAR_PASSWORD_VENTANILLA.sql` manualmente si aún está pendiente.
4. Abrir `http://localhost/ProyectoDesarrollo2/public/index.php?route=login`.
   Los usuarios ya existentes conservan sus claves. El seeder corregido usa
   la clave privada de `.env` para instalaciones nuevas.
5. Recomendado en producción: configurar el DocumentRoot del VirtualHost a
   `C:/xampp/htdocs/ProyectoDesarrollo2/public`, con AllowOverride habilitado.
   Así `app/`, `config/`, `core/`, SQL y documentación quedan fuera del área pública.
   En el montaje actual, el `.htaccess` raíz deniega las carpetas privadas y SQL/MD.
6. Cambiar APP_ENV a production únicamente bajo HTTPS y sustituir las claves
   de prueba. El hash inválido se describe en desarrollo; en producción se devuelve
   el mensaje genérico para evitar revelar la existencia o el estado de las cuentas.

La cookie PHP sigue siendo la autoridad de autenticación. `sessionStorage` solo
guarda datos de presentación: el servidor comprueba sesión y roles al servir las
vistas privadas y en cada endpoint protegido. No se migraron ni restablecieron
las contraseñas de la base de datos durante esta refactorización.
