<?php
/** Catálogo territorial oficial. Conserva solicitudes y sedes personalizadas existentes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../core/Database.php';
(static function(): void {
$db=Database::getConnection();
$hasColumn=static function(string $table,string $column) use($db): bool {
    $q=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$table,$column]);return (bool)$q->fetchColumn();
};
$db->exec("CREATE TABLE IF NOT EXISTS barrios (
 id_barrio INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo_barrio CHAR(4) NOT NULL UNIQUE, nombre_barrio VARCHAR(100) NOT NULL,
 id_comuna INT UNSIGNED NOT NULL, latitud_referencia DECIMAL(10,7) NULL,
 longitud_referencia DECIMAL(10,7) NULL, fuente VARCHAR(255) NOT NULL,
 activo TINYINT(1) NOT NULL DEFAULT 1,
 FOREIGN KEY(id_comuna) REFERENCES comunas(id_comuna), INDEX idx_barrio_comuna(id_comuna)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS migraciones_datos (
 clave VARCHAR(80) PRIMARY KEY, aplicada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, detalle JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if(!$hasColumn('solicitudes','id_barrio')) $db->exec('ALTER TABLE solicitudes ADD id_barrio INT UNSIGNED NULL, ADD CONSTRAINT fk_solicitud_barrio FOREIGN KEY(id_barrio) REFERENCES barrios(id_barrio)');
if(!$hasColumn('sedes_atencion','ubicacion_confirmada')) $db->exec('ALTER TABLE sedes_atencion ADD ubicacion_confirmada TINYINT(1) NOT NULL DEFAULT 0, ADD fuente_ubicacion VARCHAR(500) NULL, ADD barrio_referencia CHAR(4) NULL');
$catalog=json_decode(file_get_contents(__DIR__.'/../data/territorio/barrios.json'),true,512,JSON_THROW_ON_ERROR);
$rural=json_decode(file_get_contents(__DIR__.'/../data/territorio/corregimientos.json'),true,512,JSON_THROW_ON_ERROR);
if(count($catalog['barrios'])<300 || count($rural)!==15) throw new RuntimeException('El catálogo territorial está incompleto.');
$normalize=static fn(string $s): string=>strtolower(trim(str_replace('Corregimiento ','',iconv('UTF-8','ASCII//TRANSLIT',$s))));
$db->beginTransaction();
try {
    $old=$db->query('SELECT id_comuna,codigo_comuna,nombre_comuna,tipo,activo FROM comunas')->fetchAll();
    // Se conserva la identidad de sectores históricos; no se convierten Ponce/Playa Rica en otros lugares.
    foreach($old as $sector) if($sector['tipo']!=='Urbana') {
        $match=null;foreach($rural as $i=>$name) if($normalize($name)===$normalize($sector['nombre_comuna'])){$match=$i;break;}
        if($match===null) {
            $code=ctype_digit($sector['codigo_comuna']) && (int)$sector['codigo_comuna']<=22?'H'.$sector['codigo_comuna']:$sector['codigo_comuna'];
            $db->prepare('UPDATE comunas SET activo=0,codigo_comuna=? WHERE id_comuna=?')->execute([$code,$sector['id_comuna']]);
        } else $db->prepare('UPDATE comunas SET codigo_comuna=?,nombre_comuna=?,activo=1 WHERE id_comuna=?')->execute([sprintf('R%02d',$match+1),$rural[$match],$sector['id_comuna']]);
    }
    $insert=$db->prepare("INSERT INTO comunas(codigo_comuna,nombre_comuna,tipo,es_oficina_principal,activo) VALUES(?,?,'Urbana',?,1) ON DUPLICATE KEY UPDATE nombre_comuna=VALUES(nombre_comuna),activo=1");
    for($i=1;$i<=22;$i++) $insert->execute([sprintf('%02d',$i),'Comuna '.$i,$i===17?1:0]);
    $insert=$db->prepare("INSERT INTO comunas(codigo_comuna,nombre_comuna,tipo,activo) VALUES(?,?,'Corregimiento',1) ON DUPLICATE KEY UPDATE nombre_comuna=VALUES(nombre_comuna),activo=1");
    foreach($rural as $i=>$name)$insert->execute([sprintf('R%02d',$i+1),$name]);
    $ids=[];foreach($db->query('SELECT id_comuna,codigo_comuna FROM comunas') as $s)$ids[$s['codigo_comuna']]=$s['id_comuna'];
    $insert=$db->prepare('INSERT INTO barrios(codigo_barrio,nombre_barrio,id_comuna,latitud_referencia,longitud_referencia,fuente) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nombre_barrio=VALUES(nombre_barrio),id_comuna=VALUES(id_comuna),latitud_referencia=VALUES(latitud_referencia),longitud_referencia=VALUES(longitud_referencia),fuente=VALUES(fuente),activo=1');
    $byCode=[];
    foreach($catalog['barrios'] as $b){$insert->execute([$b['codigo'],$b['nombre'],$ids[$b['comuna']],$b['latitud_referencia'],$b['longitud_referencia'],$b['fuente']]);$byCode[$b['codigo']]=$b;}
    // Solo sustituye ubicaciones exactas de los seeders originales. Nunca toca una sede ya configurada.
    $references=['17'=>['1709',3.4516,-76.532],'01'=>['0101',3.458,-76.535],'06'=>['0601',3.442,-76.523],'07'=>['0705',3.431,-76.540],'13'=>['1301',3.421,-76.555],'14'=>['1401',3.468,-76.515],'20'=>['2001',3.478,-76.525],'21'=>['2101',3.489,-76.532]];
    $previousSites=[];
    foreach($references as $comuna=>[$code,$lat,$lng]) {
        if(!isset($byCode[$code]))throw new RuntimeException('Falta barrio de referencia '.$code);
        $b=$byCode[$code];
        $q=$db->prepare('SELECT s.* FROM sedes_atencion s JOIN comunas c ON c.id_comuna=s.id_comuna WHERE c.codigo_comuna=? AND s.fuente_ubicacion IS NULL AND s.ubicacion_confirmada=0 AND s.latitud=? AND s.longitud=?');$q->execute([(string)$comuna,$lat,$lng]);
        foreach($q->fetchAll() as $site){
            $previousSites[]=$site;
            $db->prepare('UPDATE sedes_atencion SET direccion=?,latitud=?,longitud=?,fuente_ubicacion=?,barrio_referencia=? WHERE id_sede=?')->execute(['Referencia territorial: '.$b['nombre'].'. Dirección de la sede por configurar.',$b['latitud_referencia'],$b['longitud_referencia'],$b['fuente'],$code,$site['id_sede']]);
        }
    }
    $db->prepare('INSERT IGNORE INTO migraciones_datos(clave,detalle) VALUES(?,?)')->execute(['territorio-IDESC-2026',json_encode(['sectores_anteriores'=>$old,'sedes_anteriores'=>$previousSites,'barrios'=>count($byCode)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    $db->commit();echo 'Catálogo territorial aplicado: '.count($byCode)." barrios/sectores, 22 comunas y 15 corregimientos.\n";
} catch(Throwable $e){$db->rollBack();throw $e;}

})();
