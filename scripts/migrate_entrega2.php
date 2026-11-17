<?php
/** Migración aditiva e idempotente. No borra ni reinserta datos existentes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../core/Database.php';
(static function(): void {
$db = Database::getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS hojas_ruta (
 id_ruta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 id_encuestador INT UNSIGNED NOT NULL, id_sede INT UNSIGNED NOT NULL,
 fecha_visita DATE NOT NULL, creada_por INT UNSIGNED NOT NULL,
 fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 entregada_por INT UNSIGNED NULL, fecha_entrega DATETIME NULL,
 UNIQUE KEY uk_ruta_jornada (id_encuestador, fecha_visita),
 FOREIGN KEY (id_encuestador) REFERENCES usuarios(id_usuario),
 FOREIGN KEY (id_sede) REFERENCES sedes_atencion(id_sede),
 FOREIGN KEY (creada_por) REFERENCES usuarios(id_usuario),
 FOREIGN KEY (entregada_por) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$hasColumn = static function(PDO $db, string $table, string $column): bool {
 $q=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
 $q->execute([$table,$column]); return (bool)$q->fetchColumn();
};
if (!$hasColumn($db,'solicitudes','id_sede')) {
 $db->exec('ALTER TABLE solicitudes ADD id_sede INT UNSIGNED NULL, ADD CONSTRAINT fk_solicitud_sede FOREIGN KEY(id_sede) REFERENCES sedes_atencion(id_sede)');
}
if (!$hasColumn($db,'asignaciones','id_ruta')) {
 $db->exec('ALTER TABLE asignaciones ADD id_ruta INT UNSIGNED NULL, ADD orden SMALLINT UNSIGNED NULL, ADD CONSTRAINT fk_asignacion_ruta FOREIGN KEY(id_ruta) REFERENCES hojas_ruta(id_ruta)');
}
$db->exec("CREATE TABLE IF NOT EXISTS consecutivos_solicitud (fecha CHAR(8) PRIMARY KEY, ultimo INT UNSIGNED NOT NULL) ENGINE=InnoDB");
echo "Migración entrega 2 completada. Los registros anteriores conservan su sede sin especificar.\n";

require __DIR__.'/migrate_territory.php';

})();
