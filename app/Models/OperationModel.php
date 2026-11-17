<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/HttpException.php';
class OperationModel
{
    private PDO $db;
    private DateTimeImmutable $now;
    public function __construct(?DateTimeImmutable $now=null) { $this->db = Database::getConnection(); $this->now=$now??new DateTimeImmutable(); }
    public function catalogs(): array {
        return [
            'sedes'=>$this->db->query('SELECT s.*, c.codigo_comuna FROM sedes_atencion s JOIN comunas c ON c.id_comuna=s.id_comuna WHERE s.activo=1 ORDER BY s.es_oficina_principal DESC,c.codigo_comuna')->fetchAll(),
            'encuestadores'=>$this->db->query('SELECT id_usuario,nombres,apellidos FROM usuarios WHERE activo=1 AND id_rol=3 ORDER BY nombres,apellidos')->fetchAll(),
            'estados'=>$this->db->query('SELECT id_estado,nombre_estado FROM estados_solicitud WHERE activo=1 ORDER BY id_estado')->fetchAll()
        ];
    }
    public function pending(): array {
        return $this->db->query("SELECT s.*, c.nombre_comuna,c.tipo,
            DATE_ADD(DATE(s.fecha_creacion), INTERVAL 15 DAY) fecha_limite,
            DATEDIFF(DATE_ADD(DATE(s.fecha_creacion), INTERVAL 15 DAY),CURDATE()) dias_restantes
            FROM solicitudes s JOIN comunas c ON c.id_comuna=s.id_comuna
            WHERE s.id_estado IN (1,2) AND s.latitud IS NOT NULL AND s.longitud IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM asignaciones a WHERE a.id_solicitud=s.id_solicitud)
            ORDER BY s.fecha_creacion,s.id_solicitud")->fetchAll();
    }
    public function routes(?int $owner=null): array {
        $q=$this->db->prepare("SELECT r.*, CONCAT(u.nombres,' ',u.apellidos) encuestador,
            s.nombre_sede, (SELECT COUNT(*) FROM asignaciones a WHERE a.id_ruta=r.id_ruta) total,
            CONCAT(v.nombres,' ',v.apellidos) entregado_por_nombre
            FROM hojas_ruta r JOIN usuarios u ON u.id_usuario=r.id_encuestador
            JOIN sedes_atencion s ON s.id_sede=r.id_sede
            LEFT JOIN usuarios v ON v.id_usuario=r.entregada_por
            WHERE (? IS NULL OR r.id_encuestador=?)
            ORDER BY r.fecha_visita DESC,r.id_ruta DESC");
        $q->execute([$owner,$owner]); return $q->fetchAll();
    }
    public function route(int $id, ?int $owner=null): array {
        $route=null;
        foreach ($this->routes($owner) as $r) { if ((int)$r['id_ruta']===$id) { $route=$r; break; } }
        if (!$route) throw new HttpException('Hoja de ruta no encontrada.',404);
        $q=$this->db->prepare('SELECT s.*, a.orden, c.nombre_comuna, e.nombre_estado FROM asignaciones a JOIN solicitudes s ON s.id_solicitud=a.id_solicitud JOIN comunas c ON c.id_comuna=s.id_comuna JOIN estados_solicitud e ON e.id_estado=s.id_estado WHERE a.id_ruta=? ORDER BY a.orden');
        $q->execute([$id]); $route['solicitudes']=$q->fetchAll(); return $route;
    }
    public function create(int $surveyor,int $site,string $date,array $ids,int $actor): int {
        if (count($ids)!==20 || count(array_unique($ids))!==20) throw new HttpException('Selecciona exactamente 20 solicitudes diferentes.',400);
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$d || $d->format('Y-m-d')!==$date || $date<$this->now->modify('+1 day')->format('Y-m-d')) throw new HttpException('La visita debe programarse desde mañana.',400);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario=? AND id_rol=3 AND activo=1 FOR UPDATE');
            $q->execute([$surveyor]); if (!$q->fetch()) throw new HttpException('Encuestador no disponible.',400);
            $q=$this->db->prepare('SELECT id_sede FROM sedes_atencion WHERE id_sede=? AND activo=1');
            $q->execute([$site]); if (!$q->fetch()) throw new HttpException('Sede no disponible.',400);
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $q=$this->db->prepare("SELECT id_solicitud,id_estado,latitud,longitud FROM solicitudes WHERE id_solicitud IN ($marks) ORDER BY id_solicitud FOR UPDATE");
            $q->execute($ids); $rows=$q->fetchAll();
            if (count($rows)!==20) throw new HttpException('Hay solicitudes inexistentes.',400);
            foreach ($rows as $r) if (!in_array((int)$r['id_estado'],[1,2],true) || $r['latitud']===null || $r['longitud']===null) throw new HttpException('La selección incluye solicitudes sin ubicación o ya asignadas.',409);
            $q=$this->db->prepare("SELECT COUNT(*) FROM asignaciones WHERE id_solicitud IN ($marks)");
            $q->execute($ids); if ($q->fetchColumn()) throw new HttpException('Una solicitud ya fue asignada. Actualiza la lista.',409);
            $q=$this->db->prepare('INSERT INTO hojas_ruta(id_encuestador,id_sede,fecha_visita,creada_por) VALUES(?,?,?,?)');
            $q->execute([$surveyor,$site,$date,$actor]); $id=(int)$this->db->lastInsertId();
            $insert=$this->db->prepare('INSERT INTO asignaciones(id_solicitud,id_encuestador,asignado_por,id_ruta,orden) VALUES(?,?,?,?,?)');
            foreach ($ids as $i=>$request) $insert->execute([$request,$surveyor,$actor,$id,$i+1]);
            $this->db->prepare("UPDATE solicitudes SET id_estado=3 WHERE id_solicitud IN ($marks)")->execute($ids);
            $this->audit($actor,'CREAR_RUTA',$id,['solicitudes'=>$ids,'fecha_visita'=>$date]);
            $this->db->commit(); return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            if ($e instanceof PDOException && $e->getCode()==='23000') throw new HttpException('El encuestador ya tiene una ruta para esa fecha.',409);
            throw $e;
        }
    }
    public function deliver(int $id,int $site,int $actor): void {
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT * FROM hojas_ruta WHERE id_ruta=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch();
            if (!$r) throw new HttpException('Hoja de ruta no encontrada.',404);
            if ((int)$r['id_sede']!==$site) throw new HttpException('La ruta pertenece a otro punto de atención.',409);
            if ($r['fecha_entrega']) throw new HttpException('La ruta ya fue entregada.',409);
            if ($r['fecha_visita']!==$this->now->format('Y-m-d') || (int)$this->now->format('H')>=12) throw new HttpException('La entrega se registra en la mañana de la fecha programada.',409);
            $this->db->prepare('UPDATE hojas_ruta SET entregada_por=?,fecha_entrega=NOW() WHERE id_ruta=?')->execute([$actor,$id]);
            $this->audit($actor,'ENTREGAR_RUTA',$id,['id_sede'=>$site]);$this->db->commit();
        } catch(Throwable $e) { $this->db->rollBack();throw $e; }
    }
    public function dashboard(): array {
        return [
            'totales'=>$this->db->query("SELECT COUNT(*) total,
              COALESCE(SUM(DATE(fecha_creacion)=CURDATE()),0) hoy,
              COALESCE(SUM(id_estado NOT IN(5,6) AND DATE_ADD(DATE(fecha_creacion),INTERVAL 15 DAY)<CURDATE()),0) vencidas,
              COALESCE(SUM(id_estado NOT IN(5,6) AND DATE_ADD(DATE(fecha_creacion),INTERVAL 15 DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 3 DAY)),0) por_vencer
              FROM solicitudes")->fetch(),
            'estados'=>$this->db->query('SELECT e.nombre_estado,COUNT(s.id_solicitud) total FROM estados_solicitud e LEFT JOIN solicitudes s ON s.id_estado=e.id_estado GROUP BY e.id_estado ORDER BY e.id_estado')->fetchAll(),
            'sedes'=>$this->db->query("SELECT COALESCE(p.nombre_sede,'Sin sede (registro anterior)') nombre_sede,COUNT(*) total FROM solicitudes s LEFT JOIN sedes_atencion p ON p.id_sede=s.id_sede GROUP BY s.id_sede ORDER BY total DESC")->fetchAll(),
            'rutas'=>$this->db->query('SELECT COUNT(*) total,COALESCE(SUM(fecha_entrega IS NOT NULL),0) entregadas FROM hojas_ruta')->fetch()
        ];
    }
    public function saveSite(int $id,array $data,int $actor): void {
        foreach(['direccion'=>500,'fuente_ubicacion'=>500] as $field=>$max) {
            if (!is_string($data[$field]??null) || trim($data[$field])==='' || strlen($data[$field])>$max) throw new HttpException('Completa la dirección y la fuente de verificación de la sede (máximo 500 caracteres).',400);
        }
        $lat=filter_var($data['latitud']??null,FILTER_VALIDATE_FLOAT);
        $lng=filter_var($data['longitud']??null,FILTER_VALIDATE_FLOAT);
        if($lat===false || $lng===false || !is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) throw new HttpException('Coordenadas de sede inválidas.',400);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT * FROM sedes_atencion WHERE id_sede=? AND activo=1 FOR UPDATE');$q->execute([$id]);$old=$q->fetch();
            if(!$old)throw new HttpException('Sede no encontrada.',404);
            $this->db->prepare('UPDATE sedes_atencion SET direccion=?,latitud=?,longitud=?,fuente_ubicacion=?,ubicacion_confirmada=1 WHERE id_sede=?')->execute([trim($data['direccion']),$lat,$lng,trim($data['fuente_ubicacion']),$id]);
            $this->db->prepare("INSERT INTO bitacora_auditoria(id_usuario,accion,tabla_afectada,registro_id,datos_anteriores,datos_nuevos) VALUES(?,'CONFIGURAR_SEDE','sedes_atencion',?,?,?)")->execute([$actor,$id,json_encode($old,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    private function audit(int $actor,string $action,int $id,array $data): void {
        $this->db->prepare("INSERT INTO bitacora_auditoria(id_usuario,accion,tabla_afectada,registro_id,datos_nuevos) VALUES(?,?,'hojas_ruta',?,?)")->execute([$actor,$action,$id,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
}
