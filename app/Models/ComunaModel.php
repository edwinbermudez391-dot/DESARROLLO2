<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/Database.php';

class ComunaModel
{
    public function getActive(): array
    {
        return Database::getConnection()->query('
            SELECT id_comuna, codigo_comuna, nombre_comuna, tipo, es_oficina_principal
            FROM comunas WHERE activo = 1 ORDER BY CAST(codigo_comuna AS UNSIGNED)
        ')->fetchAll();
    }
}
