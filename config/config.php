<?php
/**
 * Configuración de la aplicación
 * Entrega 1 - Sistema de Encuestas Cali
 * Backend modular y seguro (PHP + MySQL + PDO)
 */

declare(strict_types=1);

require_once __DIR__.'/environment.php';
date_default_timezone_set('America/Bogota');

// ---------- Entorno ----------
define('APP_ENV', 'development'); // development | production
define('APP_DEBUG', true);
define('APP_BASE_URL', '/ProyectoDesarrollo2/public');

// ---------- Base de datos ----------
require_once __DIR__ . '/database.php';

// ---------- Sesiones ----------
define('SESSION_NAME', 'encuestas_cali_session');
define('SESSION_LIFETIME', 3600); // 1 hora
define('SESSION_COOKIE_SECURE', (APP_ENV === 'production')); // true solo en HTTPS

// ---------- Roles del sistema ----------
define('ROL_ADMINISTRADOR', 1);
define('ROL_VENTANILLA', 2);
define('ROL_ENCUESTADOR', 3);
define('ROL_CALIDAD', 4);
define('ROL_ENRUTADOR', 5);

$ROLES_NOMBRES = [
    ROL_ADMINISTRADOR   => 'Administrador',
    ROL_VENTANILLA      => 'Ventanilla',
    ROL_ENCUESTADOR     => 'Encuestador',
    ROL_CALIDAD         => 'Calidad',
    ROL_ENRUTADOR       => 'Enrutador',
];

// ---------- Estados de solicitud ----------
define('ESTADO_PENDIENTE', 1);
define('ESTADO_GEOREFERENCIADA', 2);
