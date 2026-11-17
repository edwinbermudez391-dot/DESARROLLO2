<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Auth.php';

class AuthController extends Controller
{
    public function login(): void
    {
        $input = $this->input();
        $username = $input['username'] ?? $input['usuario'] ?? $input['correo'] ?? '';
        $password = $input['password'] ?? $input['clave'] ?? '';
        if (!is_string($username) || !is_string($password)
            || trim($username) === '' || $password === '') {
            throw new HttpException('Envía un usuario y una contraseña válidos.', 400);
        }

        $auth = new Auth();
        $user = $auth->login(trim($username), $password);
        if ($user === null) {
            $error = $auth->getLoginError();
            throw new HttpException($error['message'], $error['status']);
        }
        $this->json(['success' => true, 'message' => 'Inicio de sesión exitoso', 'user' => $user]);
    }

    public function session(): void
    {
        $auth = new Auth();
        $auth->requireAuth();
        $this->json(['success' => true, 'user' => [
            'id_usuario' => $auth->getUserId(),
            'id_rol' => $auth->getUserRole(),
            'username' => $auth->getUsername(),
            'nombres' => $_SESSION['nombres'] ?? '',
            'apellidos' => $_SESSION['apellidos'] ?? '',
            'nombre_rol' => $_SESSION['nombre_rol'] ?? '',
        ]]);
    }

    public function logout(): void
    {
        $auth = new Auth();
        $auth->requireAuth();
        $auth->logout();
        $this->json(['success' => true, 'message' => 'Sesión cerrada']);
    }
}
