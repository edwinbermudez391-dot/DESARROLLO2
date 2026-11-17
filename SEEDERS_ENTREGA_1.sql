-- ============================================================================
-- SCRIPT DE DATOS INICIALES (SEEDERS) — Entrega 1
-- Sistema Web PHP/MySQL: Recepción y Georreferenciación de Solicitudes
-- Empresa encuestadora — Santiago de Cali (Colombia)
-- Motor: MySQL 8.0+
-- ============================================================================

USE encuestas_cali;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. Roles del sistema
-- ----------------------------------------------------------------------------
INSERT INTO roles (nombre_rol, descripcion, activo) VALUES
('Administrador', 'Acceso total al sistema: gestión de usuarios, reportes y configuración general.', 1),
('Ventanilla', 'Atención al ciudadano: registra solicitudes y puede georreferenciar.', 1),
('Encuestador', 'Ejecuta visitas de campo y actualiza el estado de las solicitudes asignadas.', 1),
('Calidad', 'Control de calidad y auditoría de las encuestas realizadas.', 1),
('Enrutador', 'Planea rutas diarias: asigna solicitudes a encuestadores y lidera la bitácora.', 1);

-- ----------------------------------------------------------------------------
-- 2. Estados del ciclo de vida de una solicitud
-- ----------------------------------------------------------------------------
INSERT INTO estados_solicitud (nombre_estado, descripcion, color_hex, activo) VALUES
('Pendiente', 'Solicitud registrada en ventanilla, aún sin georreferenciar.', '#EF4444', 1),
('Georreferenciada', 'Solicitud con coordenadas (latitud/longitud) capturadas en el mapa.', '#F59E0B', 1),
('Asignada', 'Solicitud asignada a un encuestador por el enrutador.', '#3B82F6', 1),
('En visita', 'El encuestador se encuentra en la visita de campo.', '#8B5CF6', 1),
('Completada', 'Encuesta finalizada y entregada.', '#10B981', 1),
('Cancelada', 'Solicitud cancelada (duplicado, ciudadano no localizado, etc.).', '#6B7280', 1);

-- ----------------------------------------------------------------------------
-- 3. Comunas y corregimientos de Santiago de Cali
-- Se incluyen las comunas con puntos de atención estáticos requeridos:
-- 1, 6, 7, 13, 14, 17 (oficina principal), 20 y 21.
-- ----------------------------------------------------------------------------
INSERT INTO comunas (codigo_comuna, nombre_comuna, tipo, es_oficina_principal, activo) VALUES
('01', 'Comuna 1', 'Urbana', 0, 1),
('06', 'Comuna 6', 'Urbana', 0, 1),
('07', 'Comuna 7', 'Urbana', 0, 1),
('13', 'Comuna 13', 'Urbana', 0, 1),
('14', 'Comuna 14', 'Urbana', 0, 1),
('17', 'Comuna 17', 'Urbana', 1, 1),
('20', 'Comuna 20', 'Urbana', 0, 1),
('21', 'Comuna 21', 'Urbana', 0, 1);

-- Sedes académicas: referencias de mapas públicos, sin domicilios de personas.
INSERT INTO sedes_atencion (id_comuna,nombre_sede,direccion,latitud,longitud,es_oficina_principal,activo) VALUES
((SELECT id_comuna FROM comunas WHERE codigo_comuna='17'),'Oficina Principal - Encuestas Cali','Referencia territorial: El Ingenio. Dirección de la sede por configurar.',3.3830235,-76.5307539,1,1),
((SELECT id_comuna FROM comunas WHERE codigo_comuna='01'),'Punto de Atención - Comuna 1','Referencia territorial: Terrón Colorado. Dirección de la sede por configurar.',3.4529423,-76.5609925,0,1),
((SELECT id_comuna FROM comunas WHERE codigo_comuna='06'),'Punto de Atención - Comuna 6','Referencia territorial: San Luis. Dirección de la sede por configurar.',3.4805386,-76.4865423,0,1),
((SELECT id_comuna FROM comunas WHERE codigo_comuna='07'),'Punto de Atención - Comuna 7','Referencia territorial: Puerto Mallarino. Dirección de la sede por configurar.',3.4492891,-76.4796968,0,1),
((SELECT id_comuna FROM comunas WHERE codigo_comuna='13'),'Punto de Atención - Comuna 13','Referencia territorial: Ulpiano Lloreda. Dirección de la sede por configurar.',3.4414856,-76.490403,0,1),
((SELECT id_comuna FROM comunas WHERE codigo_comuna='14'),'Punto de Atención - Comuna 14','Referencia territorial: Alfonso Bonilla Aragón. Dirección de la sede por configurar.',3.4198627,-76.4829896,0,1),
((SELECT id_comuna FROM comunas WHERE codigo_comuna='20'),'Punto de Atención - Comuna 20','Referencia territorial: El Cortijo. Dirección de la sede por configurar.',3.4165422,-76.5589483,0,1),
((SELECT id_comuna FROM comunas WHERE codigo_comuna='21'),'Punto de Atención - Comuna 21','Referencia territorial: Pizamos I. Dirección de la sede por configurar.',3.4022636,-76.4751463,0,1);
SET FOREIGN_KEY_CHECKS=1;
