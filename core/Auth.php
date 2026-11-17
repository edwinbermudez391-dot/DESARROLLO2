<?php
/**
 * Autenticación y control de acceso basado en roles (RBAC)
 * Manejo seguro de sesiones PHP
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Models/UserModel.php';
require_once __DIR__ . '/HttpException.php';

class Auth
{
    private UserModel $users;
    private string $loginError = 'Credenciales inválidas o usuario inactivo';
    private int $loginErrorStatus = 401;

    public function __construct()
    {
        $this->users = new UserModel();
        $this->initSession();
    }

    private function initSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', SESSION_COOKIE_SECURE ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
            session_name(SESSION_NAME);
            session_start();
        }
    }

    /**
     * Autentica un usuario por username y contraseña
     * @return array|null Datos del usuario sin password_hash o null
     */
    public function login(string $username, string $password): ?array
    {
        $this->loginError = 'Credenciales inválidas o usuario inactivo';
        $this->loginErrorStatus = 401;
        $user = $this->users->findByUsername($username);

        if (!$user || !$user['activo']) {
            return null;
        }

        $hash = (string) $user['password_hash'];
        $hashInfo = password_get_info($hash);
        if ($hashInfo['algoName'] === 'unknown'
            || ($hashInfo['algoName'] === 'bcrypt'
                && !preg_match('/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/D', $hash))) {
            error_log('Login rechazado: hash inválido para id_usuario=' . (int) $user['id_usuario']);
            if (APP_ENV === 'development') {
                $this->loginError = 'La contraseña almacenada tiene un hash inválido. Solicita al administrador restablecerla.';
                $this->loginErrorStatus = 500;
            }
            return null;
        }

        if (!password_verify($password, $hash)) {
            return null;
        }

        // Regenerar ID de sesión para evitar fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = (int) $user['id_usuario'];
        $_SESSION['user_rol']  = (int) $user['id_rol'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['nombres']   = $user['nombres'];
        $_SESSION['apellidos'] = $user['apellidos'];
        $_SESSION['nombre_rol'] = $user['nombre_rol'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // Actualizar último acceso en BD
        $this->users->updateLastAccess((int) $user['id_usuario']);

        // Eliminar password del retorno
        unset($user['password_hash']);
        return $user;
    }

    public function getLoginError(): array
    {
        return [
            'status' => $this->loginErrorStatus,
            'message' => $this->loginError,
        ];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function getUserRole(): ?int
    {
        return $_SESSION['user_rol'] ?? null;
    }

    public function getUsername(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Verifica si el usuario actual tiene alguno de los roles permitidos
     */
    public function hasRole(array $allowedRoles): bool
    {
        $rol = $this->getUserRole();
        return $rol !== null && in_array($rol, $allowedRoles, true);
    }

    /**
     * Middleware: devuelve JSON 401/403 si no cumple el rol
     */
    public function requireRole(array $allowedRoles): void
    {
        if (!$this->isLoggedIn()) {
            throw new HttpException('No autenticado', 401);
        }
        if (!$this->hasRole($allowedRoles)) {
            throw new HttpException('Acceso denegado: rol insuficiente', 403);
        }
    }

    /**
     * Middleware genérico para APIs
     */
    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            throw new HttpException('No autenticado', 401);
        }
    }
}
