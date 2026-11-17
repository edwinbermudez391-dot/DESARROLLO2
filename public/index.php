<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/HttpException.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

try {
    $route = $_GET['route'] ?? 'login';
    if (!is_string($route)) {
        throw new HttpException('Ruta inválida', 400);
    }
    $route = trim($route, '/');
    $routes = [
        'api/operacion/catalogos' => ['GET', 'OperationController', 'catalogs'],
        'api/operacion/pendientes' => ['GET', 'OperationController', 'pending'],
        'api/operacion/rutas' => ['GET', 'OperationController', 'routes'],
        'api/operacion/crear-ruta' => ['POST', 'OperationController', 'create'],
        'api/operacion/entregar-ruta' => ['POST', 'OperationController', 'deliver'],
        'api/operacion/dashboard' => ['GET', 'OperationController', 'dashboard'],
        'api/operacion/reporte' => ['GET', 'OperationController', 'report'],
        'api/operacion/ruta-pdf' => ['GET', 'OperationController', 'routePdf'],
        'api/login' => ['POST', 'AuthController', 'login'],
        'api/logout' => ['POST', 'AuthController', 'logout'],
        'api/sesion' => ['GET', 'AuthController', 'session'],
        'api/territorio' => ['GET', 'TerritoryController', 'index'],
        'api/operacion/guardar-sede' => ['POST', 'OperationController', 'saveSite'],
        'api/comunas' => ['GET', 'ComunaController', 'index'],
        'api/solicitudes' => ['POST', 'RequestController', 'store'],
        'api/solicitudes/geolocalizacion' => ['POST', 'RequestController', 'geolocation'],
        'api/solicitudes/hoy' => ['GET', 'RequestController', 'today'],
    ];
    $method = $_SERVER['REQUEST_METHOD'];
    if (isset($routes[$route])) {
        [$allowedMethod, $controller, $action] = $routes[$route];
        if ($method !== $allowedMethod) {
            header('Allow: ' . $allowedMethod);
            throw new HttpException('Método no permitido', 405);
        }
        require_once __DIR__ . '/../app/Controllers/' . $controller . '.php';
        (new $controller())->$action();
    } elseif (in_array($route, ['login', 'admin', 'calidad', 'ventanilla', 'enrutador', 'encuestador'], true)) {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new HttpException('Método no permitido', 405);
        }
        require_once __DIR__ . '/../app/Controllers/ViewController.php';
        (new ViewController())->show($route);
    } else {
        throw new HttpException('Ruta no encontrada', 404);
    }
} catch (Throwable $e) {
    $status = $e instanceof HttpException ? $e->getCode() : 500;
    if (!$e instanceof HttpException) {
        error_log((string) $e);
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $message = $e instanceof HttpException ? $e->getMessage() : 'Error interno del servidor. Inténtalo nuevamente.';
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
}
