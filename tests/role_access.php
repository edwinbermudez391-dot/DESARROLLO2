<?php
/** Pruebas de rutas con sesiones aisladas; no modifica usuarios ni contraseñas. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

$views = [1 => 'admin', 2 => 'ventanilla', 3 => 'encuestador', 4 => 'calidad', 5 => 'enrutador'];
$cases = [];
foreach ($views as $role => $assignedView) {
    foreach ($views as $view) {
        $cases[] = [$role, $view, $assignedView === $view ? 200 : 302];
    }
    $cases[] = [null, $assignedView, 302];
    $cases[] = [$role, 'api/sesion', 200];
    $cases[] = [$role, 'api/logout', 200];
}
$cases[] = [null, 'api/sesion', 401];
foreach ($views as $view) {
    $cases[] = [99, $view, 302];
}

foreach ($cases as [$role, $view, $expectedStatus]) {
    $code = 'require "core/Auth.php"; new Auth(); $_SESSION = [];';
    if ($role !== null) {
        $code .= '$_SESSION = ["logged_in" => true, "user_id" => 1, "user_rol" => '
            . $role . ', "username" => "test", "nombres" => "Test", "nombre_rol" => "Test"];';
    }
    $code .= '$_GET["route"] = ' . var_export($view, true) . ';';
    $code .= '$_SERVER["REQUEST_METHOD"] = ' . var_export($view === 'api/logout' ? 'POST' : 'GET', true) . ';';
    $code .= 'http_response_code(200); ob_start(); require "public/index.php"; $body = ob_get_clean();';
    $code .= '$ok = http_response_code() === ' . $expectedStatus . ';';
    if ($expectedStatus === 302) {
        $code .= '$ok = $ok && $body === "";';
    } elseif ($view === 'api/sesion' && $role !== null) {
        $code .= '$ok = $ok && json_decode($body, true)["user"]["id_rol"] === ' . $role . ';';
    } elseif ($view === 'api/logout') {
        $code .= '$ok = $ok && empty($_SESSION) && session_status() !== PHP_SESSION_ACTIVE;';
    } elseif ($expectedStatus === 200) {
        $code .= '$ok = $ok && str_contains($body, "<html") && (str_contains($body, "role-page.js")'
            . ' || str_contains($body, "role-dashboard.js") || str_contains($body, "encuestador.js"));';
    }
    $code .= 'if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }';
    $code .= 'echo $ok ? "PASS" : "FAIL HTTP " . http_response_code(); exit($ok ? 0 : 1);';

    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar PHP.');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    echo $output . ': rol=' . ($role ?? 'anonimo') . ', ruta=' . $view . PHP_EOL;
    if ($exit !== 0) {
        fwrite(STDERR, $error);
        exit(1);
    }
}
echo count($cases) . ' pruebas correctas.' . PHP_EOL;
