<?php
/** Restablece las contraseñas de los usuarios de prueba. Ejecutar solo por CLI. */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acceso denegado: este script solo puede ejecutarse desde la consola.');
}

require_once __DIR__ . '/../core/Database.php';

$connection = null;

try {
    if (APP_ENV !== 'development') {
        throw new RuntimeException('Esta semilla de contraseñas solo está permitida en desarrollo.');
    }

    $password=getenv('DEMO_PASSWORD');
    if (!$password || strlen($password)<16) throw new RuntimeException('Configura DEMO_PASSWORD con al menos 16 caracteres.');
    $connection = Database::getConnection();
    $connection->beginTransaction();
    $statement = $connection->prepare(
        "UPDATE usuarios SET password_hash = :hash
         WHERE username IN ('admin', 'encuestador', 'calidad', 'enrutador')"
    );

    $statement->execute([
        ':hash' => password_hash($password, PASSWORD_BCRYPT),
    ]);
    $updated = $statement->rowCount();

    $connection->commit();
    echo "Registros actualizados correctamente: {$updated}." . PHP_EOL;
} catch (Throwable $error) {
    if ($connection !== null && $connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'Error: no se aplicaron los cambios. ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
