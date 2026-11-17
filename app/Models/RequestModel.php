<?php
/**
 * Modelo de Solicitudes
 * Operaciones sobre la tabla solicitudes y auditoría relacionada
 */
declare(strict_types=1);
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/TerritoryModel.php';
class RequestModel
{
    private PDO $db;
    public function __construct()
    {
        $this->db = Database::getConnection();
    }
    /**
     * Genera número de solicitud consecutivo: SOL-YYYYMMDD-####
     */
    private function generateNumeroSolicitud(): string
    {
        $fecha = date('Ymd');
        // Bloqueo de fila por día: consecutivos seguros frente a registros simultáneos.
        $q=$this->db->prepare("INSERT INTO consecutivos_solicitud(fecha,ultimo) VALUES (?,0)
            ON DUPLICATE KEY UPDATE ultimo=consecutivos_solicitud.ultimo");
        $q->execute([$fecha]);
        $q=$this->db->prepare('SELECT ultimo FROM consecutivos_solicitud WHERE fecha=? FOR UPDATE');
        $q->execute([$fecha]);$last=(int)$q->fetchColumn();
        if ($last===0) {
            $q=$this->db->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_solicitud,'-',-1) AS UNSIGNED)),0) FROM solicitudes WHERE numero_solicitud LIKE ?");
            $q->execute(['SOL-'.$fecha.'-%']);$last=(int)$q->fetchColumn();
        }
        $numero=$last+1;
        $this->db->prepare('UPDATE consecutivos_solicitud SET ultimo=? WHERE fecha=?')->execute([$numero,$fecha]);
        return sprintf('SOL-%s-%04d',$fecha,$numero);
    }
    /**
     * Guarda una nueva solicitud de atención en ventanilla
     * @param array $data
     * @return array ['success'=>bool, 'data'=>...|null, 'errors'=>...]
     */
    public function createSolicitud(array $data, int $idUsuarioCreacion, ?string $ip = null): array
    {
        $errors = [];
        // ---------- Validaciones de campos obligatorios ----------
        $required = ['nombres', 'apellidos', 'telefono', 'direccion', 'id_comuna'];
        foreach ($required as $campo) {
            if (!isset($data[$campo]) || !is_scalar($data[$campo]) || trim((string) $data[$campo]) === '') {
                $errors[] = "El campo {$campo} es obligatorio";
            }
        }
        // Teléfono: 10 dígitos numéricos (formato Colombia)
        if (empty($errors) && !preg_match('/^[0-9]{10}$/', trim((string) $data['telefono']))) {
            $errors[] = 'El celular debe contener exactamente 10 dígitos numéricos';
        }
        $territory=(new TerritoryModel())->validate($data);
        if (isset($territory['errors'])) $errors=array_merge($errors,$territory['errors']);
        else $data=array_merge($data,$territory);
        $site=filter_var($data['id_sede']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $q=$this->db->prepare('SELECT id_sede FROM sedes_atencion WHERE id_sede=? AND activo=1');
        $q->execute([$site ?: 0]);if (!$q->fetch()) $errors[]='Selecciona una sede de atención activa.';
        foreach (['nombres'=>100,'apellidos'=>100,'barrio'=>100,'corregimiento'=>100,'direccion'=>1000] as $field=>$max) {
            if (isset($data[$field]) && strlen((string)$data[$field])>$max) $errors[]="El campo {$field} supera la longitud permitida.";
        }
        $lat=$data['latitud']??null;$lng=$data['longitud']??null;
        if ($lat==='' || $lat===null) $lat=null;
        if ($lng==='' || $lng===null) $lng=null;
        if (($lat===null)!==($lng===null) || ($lat!==null &&
            (!is_numeric($lat)||!is_numeric($lng)||!is_finite((float)$lat)||!is_finite((float)$lng)||(float)$lat < -90||(float)$lat > 90||(float)$lng < -180||(float)$lng > 180))) $errors[]='Coordenadas inválidas.';
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        try {
            $this->db->beginTransaction();
            $numero = $this->generateNumeroSolicitud();
            $stmt = $this->db->prepare("
                INSERT INTO solicitudes (
                    numero_solicitud, id_usuario_creacion, nombres, apellidos, telefono,
                    direccion, barrio, id_comuna, corregimiento, id_estado, id_sede, latitud, longitud, id_barrio
                ) VALUES (
                    :numero, :usuario, :nombres, :apellidos, :telefono,
                    :direccion, :barrio, :comuna, :corregimiento, :estado, :sede, :lat, :lng, :barrio_id
                )
            ");
            $stmt->execute([
                ':numero'          => $numero,
                ':usuario'         => $idUsuarioCreacion,
                ':nombres'         => trim((string) $data['nombres']),
                ':apellidos'       => trim((string) $data['apellidos']),
                ':telefono'        => trim((string) $data['telefono']),
                ':direccion'       => trim((string) $data['direccion']),
                ':barrio'          => trim((string) $data['barrio']),
                ':comuna'          => (int) $data['id_comuna'],
                ':corregimiento'   => !empty($data['corregimiento']) ? trim((string) $data['corregimiento']) : null,
                ':estado'          => $lat===null?ESTADO_PENDIENTE:ESTADO_GEOREFERENCIADA,
                ':sede'=>$site, ':lat'=>$lat, ':lng'=>$lng, ':barrio_id'=>$data['id_barrio'],
            ]);
            $idSolicitud = (int) $this->db->lastInsertId();
            // ---------- Auditoría ----------
            $this->logAuditoria(
                idUsuario: $idUsuarioCreacion,
                accion: 'INSERT',
                tabla: 'solicitudes',
                registroId: $idSolicitud,
                datosNuevos: json_encode([
                    'numero_solicitud' => $numero,
                    'nombres' => $data['nombres'],
                    'apellidos' => $data['apellidos'],
                    'comuna' => $data['id_comuna'],
                    'estado' => $lat===null?'Pendiente':'Georreferenciada',
                ], JSON_UNESCAPED_UNICODE),
                ip: $ip
            );
            $this->db->commit();
            return [
                'success' => true,
                'data' => [
                    'id_solicitud' => $idSolicitud,
                    'numero_solicitud' => $numero,
                    'estado' => $lat===null?'Pendiente':'Georreferenciada',
                ],
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            return [
                'success' => false,
                'errors' => ['Error al guardar la solicitud. Intente nuevamente.'],
            ];
        }
    }
    /**
     * Guarda coordenadas geográficas de una solicitud
     */
    public function saveGeolocalizacion(int $idSolicitud, float $latitud, float $longitud, int $idUsuario, ?string $ip = null): array
    {
        // Validar que la solicitud exista
        $stmt = $this->db->prepare("SELECT id_solicitud, id_estado FROM solicitudes WHERE id_solicitud = :id LIMIT 1");
        $stmt->execute([':id' => $idSolicitud]);
        $solicitud = $stmt->fetch();
        if (!$solicitud) {
            return ['success' => false, 'errors' => ['Solicitud no encontrada']];
        }
        if (!in_array((int)$solicitud['id_estado'],[1,2],true)) return ['success'=>false,'errors'=>['Solo se puede cambiar la ubicación antes de asignar la solicitud.']];
        // Validar rangos de coordenadas (Cali approx 3.3-3.6 lat, -76.6 -76.3 lng)
        if ($latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
            return ['success' => false, 'errors' => ['Coordenadas inválidas']];
        }
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("
                UPDATE solicitudes
                SET latitud = :lat, longitud = :lng, id_estado = :estado, fecha_actualizacion = NOW()
                WHERE id_solicitud = :id AND id_estado IN (1,2)
            ");
            $stmt->execute([
                ':lat'  => $latitud,
                ':lng'  => $longitud,
                ':id'   => $idSolicitud,
                ':estado' => ESTADO_GEOREFERENCIADA,
            ]);
            if ($stmt->rowCount()===0) {
                $this->db->rollBack();return ['success'=>false,'errors'=>['La solicitud ya fue asignada o conserva las mismas coordenadas.']];
            }
            // ---------- Auditoría ----------
            $this->logAuditoria(
                idUsuario: $idUsuario,
                accion: 'GEOREFERENCIAR',
                tabla: 'solicitudes',
                registroId: $idSolicitud,
                datosNuevos: json_encode([
                    'latitud' => $latitud,
                    'longitud' => $longitud,
                    'estado' => 'Georreferenciada',
                ], JSON_UNESCAPED_UNICODE),
                ip: $ip
            );
            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            return ['success' => false, 'errors' => ['Error al guardar la georreferenciación']];
        }
    }
    /**
     * Retorna solicitudes creadas hoy (bitácora del enrutador)
     * Filtros opcionales: comuna, estado
     */
    public function getSolicitudesHoy(?int $idComuna = null, ?int $idEstado = null): array
    {
        $sql = "
            SELECT s.id_solicitud, s.numero_solicitud, s.nombres, s.apellidos, s.telefono,
                   s.direccion, s.barrio, s.corregimiento, s.latitud, s.longitud,
                   s.fecha_creacion, s.fecha_actualizacion,
                   c.codigo_comuna, c.nombre_comuna,
                   es.nombre_estado, es.color_hex,
                   u.nombres AS usuario_nombres, u.apellidos AS usuario_apellidos
            FROM solicitudes s
            JOIN comunas c ON s.id_comuna = c.id_comuna
            JOIN estados_solicitud es ON s.id_estado = es.id_estado
            JOIN usuarios u ON s.id_usuario_creacion = u.id_usuario
            WHERE s.fecha_creacion >= CURDATE() AND s.fecha_creacion < CURDATE() + INTERVAL 1 DAY
        ";
        $params = [];
        if ($idComuna !== null) {
            $sql .= " AND s.id_comuna = :comuna";
            $params[':comuna'] = $idComuna;
        }
        if ($idEstado !== null) {
            $sql .= " AND s.id_estado = :estado";
            $params[':estado'] = $idEstado;
        }
        $sql .= " ORDER BY s.fecha_creacion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        // Formatear fechas y asegurar JSON seguro
        $resultado = [];
        foreach ($rows as $r) {
            $resultado[] = [
                'id_solicitud'       => (int) $r['id_solicitud'],
                'numero_solicitud'   => $r['numero_solicitud'],
                'nombres'            => $r['nombres'],
                'apellidos'          => $r['apellidos'],
                'telefono'           => $r['telefono'],
                'direccion'          => $r['direccion'],
                'barrio'             => $r['barrio'],
                'corregimiento'      => $r['corregimiento'],
                'latitud'            => $r['latitud'] !== null ? (float) $r['latitud'] : null,
                'longitud'           => $r['longitud'] !== null ? (float) $r['longitud'] : null,
                'fecha_creacion'     => $r['fecha_creacion'],
                'fecha_actualizacion'=> $r['fecha_actualizacion'],
                'comuna'             => [
                    'codigo' => $r['codigo_comuna'],
                    'nombre' => $r['nombre_comuna'],
                ],
                'estado'             => [
                    'nombre' => $r['nombre_estado'],
                    'color'  => $r['color_hex'],
                ],
                'usuario_creacion'   => [
                    'nombres' => $r['usuario_nombres'],
                    'apellidos' => $r['usuario_apellidos'],
                ],
            ];
        }
        return $resultado;
    }
    /**
     * Escribe en la bitácora de auditoría
     */
    private function logAuditoria(
        int|null $idUsuario,
        string $accion,
        string $tabla,
        int|null $registroId,
        ?string $datosNuevos,
        ?string $ip,
        ?string $datosAnteriores = null
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO bitacora_auditoria (
                id_usuario, accion, tabla_afectada, registro_id,
                datos_anteriores, datos_nuevos, ip_address
            ) VALUES (
                :usuario, :accion, :tabla, :registro, :ant, :nuevos, :ip
            )
        ");
        $stmt->execute([
            ':usuario'  => $idUsuario,
            ':accion'   => $accion,
            ':tabla'    => $tabla,
            ':registro' => $registroId,
            ':ant'      => $datosAnteriores,
            ':nuevos'   => $datosNuevos,
            ':ip'       => $ip,
        ]);
    }
}
