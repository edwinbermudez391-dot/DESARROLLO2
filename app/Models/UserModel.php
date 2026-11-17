<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

class UserModel
{
    public function findByUsername(string $username): ?array
    {
        $stmt = Database::getConnection()->prepare('
            SELECT u.id_usuario, u.id_rol, u.username, u.password_hash, u.nombres,
                   u.apellidos, u.email, u.telefono, u.activo, r.nombre_rol
            FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.username = :username LIMIT 1
        ');
        $stmt->execute([':username' => $username]);
        return $stmt->fetch() ?: null;
    }

    public function updateLastAccess(int $id): void
    {
        Database::getConnection()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id')
            ->execute([':id' => $id]);
    }
}
