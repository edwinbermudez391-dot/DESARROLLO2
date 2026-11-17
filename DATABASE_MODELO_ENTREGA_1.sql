-- ============================================================================
-- MODELO DE BASE DE DATOS — Entrega 1
-- Sistema Web PHP/MySQL: Recepción y Georreferenciación de Solicitudes
-- Empresa encuestadora — Santiago de Cali (Colombia)
-- Motor: MySQL 8.0+ (InnoDB, utf8mb4)
-- Rol: DBA Senior
-- ============================================================================

CREATE DATABASE IF NOT EXISTS encuestas_cali
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE encuestas_cali;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. roles
-- Catálogo de perfiles de acceso del sistema.
-- ----------------------------------------------------------------------------
CREATE TABLE roles (
    id_rol INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_rol VARCHAR(50) NOT NULL COMMENT 'Administrador, Ventanilla, Encuestador, Calidad, Enrutador',
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_rol),
    UNIQUE KEY uk_roles_nombre (nombre_rol),
    KEY idx_roles_activo (activo)
) ENGINE=InnoDB COMMENT='Catálogo de roles del sistema (control de acceso por perfil)';

-- ----------------------------------------------------------------------------
-- 2. usuarios
-- Usuarios del sistema con autenticación y autorización basada en roles.
-- ----------------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_rol INT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL COMMENT 'Nombre de usuario para el login',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt generado con password_hash() de PHP',
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    email VARCHAR(150) NULL,
    telefono VARCHAR(20) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uk_usuarios_username (username),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_id_rol (id_rol),
    KEY idx_usuarios_activo (activo),
    CONSTRAINT fk_usuarios_id_rol FOREIGN KEY (id_rol)
        REFERENCES roles (id_rol) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Usuarios del sistema con control de acceso por rol';

-- ----------------------------------------------------------------------------
-- 3. comunas
-- Catálogo de comunas y corregimientos de Santiago de Cali.
-- Incluye las comunas con puntos de atención estáticos requeridos.
-- ----------------------------------------------------------------------------
CREATE TABLE comunas (
    id_comuna INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo_comuna VARCHAR(10) NOT NULL COMMENT 'Código interno o DANE: 01, 06, 07, 13, 14, 17, 20, 21',
    nombre_comuna VARCHAR(100) NOT NULL COMMENT 'Nombre de la comuna o corregimiento de Cali',
    tipo ENUM('Urbana','Rural','Corregimiento') NOT NULL DEFAULT 'Urbana',
    es_oficina_principal TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = oficina principal de la encuestadora',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_comuna),
    UNIQUE KEY uk_comunas_codigo (codigo_comuna),
    KEY idx_comunas_tipo (tipo),
    KEY idx_comunas_oficina_principal (es_oficina_principal),
    KEY idx_comunas_activo (activo)
) ENGINE=InnoDB COMMENT='Catálogo de comunas y corregimientos de Santiago de Cali';

-- ----------------------------------------------------------------------------
-- 4. estados_solicitud
-- Catálogo de estados del ciclo de vida de una solicitud de encuesta.
-- ----------------------------------------------------------------------------
CREATE TABLE estados_solicitud (
    id_estado INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_estado VARCHAR(50) NOT NULL COMMENT 'Pendiente, Georreferenciada, Asignada, En visita, Completada, Cancelada',
    descripcion VARCHAR(255) NULL,
    color_hex VARCHAR(7) NULL DEFAULT '#6B7280' COMMENT 'Color para la interfaz de usuario (UI)',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_estado),
    UNIQUE KEY uk_estados_nombre (nombre_estado),
    KEY idx_estados_activo (activo)
) ENGINE=InnoDB COMMENT='Catálogo de estados del ciclo de vida de una solicitud';

-- ----------------------------------------------------------------------------
-- 5. solicitudes
-- Registro principal de solicitudes recibidas por ventanilla.
-- Incluye datos del ciudadano, dirección sectorial de Cali y georreferenciación.
-- ----------------------------------------------------------------------------
CREATE TABLE solicitudes (
    id_solicitud INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero_solicitud VARCHAR(20) NOT NULL COMMENT 'Consecutivo tipo SOL-20261108-0001',
    id_usuario_creacion INT UNSIGNED NOT NULL COMMENT 'Usuario con rol Ventanilla que registró la solicitud',
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion TEXT NOT NULL COMMENT 'Dirección sectorial de Cali',
    barrio VARCHAR(100) NOT NULL,
    id_comuna INT UNSIGNED NOT NULL COMMENT 'Comuna de Cali donde se ubica el ciudadano',
    corregimiento VARCHAR(100) NULL COMMENT 'Corregimiento si aplica zona rural',
    latitud DECIMAL(10,7) NULL COMMENT 'Coordenada geográfica (latitud)',
    longitud DECIMAL(10,7) NULL COMMENT 'Coordenada geográfica (longitud)',
    id_estado INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Estado actual (FK a estados_solicitud)',
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_solicitud),
    UNIQUE KEY uk_solicitudes_numero (numero_solicitud),
    KEY idx_solicitudes_usuario_creacion (id_usuario_creacion),
    KEY idx_solicitudes_comuna (id_comuna),
    KEY idx_solicitudes_estado (id_estado),
    KEY idx_solicitudes_fecha_creacion (fecha_creacion),
    KEY idx_solicitudes_coordenadas (latitud, longitud),
    CONSTRAINT fk_solicitudes_usuario_creacion FOREIGN KEY (id_usuario_creacion)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_solicitudes_comuna FOREIGN KEY (id_comuna)
        REFERENCES comunas (id_comuna) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_solicitudes_estado FOREIGN KEY (id_estado)
        REFERENCES estados_solicitud (id_estado) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Registro de solicitudes de encuesta recibidas por ventanilla';

-- ----------------------------------------------------------------------------
-- 6. asignaciones
-- Asignación de una solicitud a un encuestador, realizada por el Enrutador.
-- ----------------------------------------------------------------------------
CREATE TABLE asignaciones (
    id_asignacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_solicitud INT UNSIGNED NOT NULL,
    id_encuestador INT UNSIGNED NOT NULL COMMENT 'Usuario con rol Encuestador',
    fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_completado TIMESTAMP NULL DEFAULT NULL,
    observaciones TEXT NULL,
    asignado_por INT UNSIGNED NOT NULL COMMENT 'Usuario con rol Enrutador que asigna',
    PRIMARY KEY (id_asignacion),
    KEY idx_asignaciones_solicitud (id_solicitud),
    KEY idx_asignaciones_encuestador (id_encuestador),
    KEY idx_asignaciones_asignado_por (asignado_por),
    CONSTRAINT fk_asignaciones_solicitud FOREIGN KEY (id_solicitud)
        REFERENCES solicitudes (id_solicitud) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_asignaciones_encuestador FOREIGN KEY (id_encuestador)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_asignaciones_asignado_por FOREIGN KEY (asignado_por)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Asignación de solicitudes a encuestadores por parte del enrutador';

-- ----------------------------------------------------------------------------
-- 7. bitacora_auditoria
-- Bitácora de auditoría y trazabilidad de acciones críticas del sistema.
-- ----------------------------------------------------------------------------
CREATE TABLE bitacora_auditoria (
    id_bitacora BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT UNSIGNED NULL COMMENT 'Usuario que ejecutó laacción (nullable para acciones del sistema)',
    accion VARCHAR(50) NOT NULL COMMENT 'INSERT, UPDATE, DELETE, LOGIN, LOGOUT, ASIGNAR, GEOREFERENCIAR',
    tabla_afectada VARCHAR(50) NOT NULL COMMENT 'Nombre de la tabla afectada',
    registro_id INT UNSIGNED NULL COMMENT 'ID del registro afectado en la tabla',
    datos_anteriores JSON NULL COMMENT 'Estado anterior del registro (JSON)',
    datos_nuevos JSON NULL COMMENT 'Estado nuevo del registro (JSON)',
    fecha_accion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IPv4 o IPv6 del usuario',
    PRIMARY KEY (id_bitacora),
    KEY idx_bitacora_usuario (id_usuario),
    KEY idx_bitacora_fecha (fecha_accion),
    KEY idx_bitacora_tabla_registro (tabla_afectada, registro_id),
    KEY idx_bitacora_accion (accion),
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Bitácora de auditoría y trazabilidad de acciones en el sistema';

-- ----------------------------------------------------------------------------
-- 8. sedes_atencion
-- Puntos de atención estáticos de la encuestadora en Cali.
-- ----------------------------------------------------------------------------
CREATE TABLE sedes_atencion (
    id_sede INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_comuna INT UNSIGNED NOT NULL,
    nombre_sede VARCHAR(150) NOT NULL COMMENT 'Nombre del punto de atención estático',
    direccion TEXT NOT NULL,
    latitud DECIMAL(10,7) NULL,
    longitud DECIMAL(10,7) NULL,
    es_oficina_principal TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = oficina principal',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_sede),
    KEY idx_sedes_comuna (id_comuna),
    KEY idx_sedes_oficina_principal (es_oficina_principal),
    KEY idx_sedes_activo (activo),
    CONSTRAINT fk_sedes_id_comuna FOREIGN KEY (id_comuna)
        REFERENCES comunas (id_comuna) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Puntos de atención estáticos de la encuestadora en Cali';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIN DEL SCRIPT DDL
-- ============================================================================