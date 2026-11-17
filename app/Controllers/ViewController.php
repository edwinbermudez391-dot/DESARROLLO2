<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Auth.php';

class ViewController
{
    public function show(string $view): void
    {
        $views = ['login', 'admin', 'calidad', 'ventanilla', 'enrutador', 'encuestador'];
        if (!in_array($view, $views, true)) {
            throw new HttpException('Vista no encontrada', 404);
        }
        $roleViews = [
            ROL_ADMINISTRADOR => 'admin',
            ROL_VENTANILLA => 'ventanilla',
            ROL_ENCUESTADOR => 'encuestador',
            ROL_CALIDAD => 'calidad',
            ROL_ENRUTADOR => 'enrutador',
        ];
        if (in_array($view, $roleViews, true)) {
            $auth = new Auth();
            if (!$auth->isLoggedIn()) {
                header('Location: index.php?route=login');
                return;
            }
            $assignedView = $roleViews[$auth->getUserRole()] ?? 'login';
            if ($assignedView !== $view) {
                header('Location: index.php?route=' . $assignedView);
                return;
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        readfile(__DIR__ . '/../Views/' . ($view === 'test-login' ? 'test_login' : $view) . '.html');
    }
}
