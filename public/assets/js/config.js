/**
 * Configuración global del frontend
 * Encuestas Cali — Entrega 1
 */

export const CONFIG = {
  // Mismo origen y carpeta pública, incluso si cambia el nombre del proyecto.
  API_BASE: new URL('../../index.php?route=api', import.meta.url).href,

  ENDPOINTS: {
    LOGIN: '/login',
    LOGOUT: '/logout',
    COMUNAS: '/comunas',
    SAVE_REQUEST: '/solicitudes',
    SAVE_GEOLOCATION: '/solicitudes/geolocalizacion',
    TODAY_REQUESTS: '/solicitudes/hoy',
  },

  // Centro del mapa en Santiago de Cali (Colombia)
  MAP_CENTER: [3.4516, -76.5320],
  MAP_ZOOM: 13,

  // Roles del sistema (coinciden con BD)
  ROLES: {
    ADMINISTRADOR: 1,
    VENTANILLA: 2,
    ENCUESTADOR: 3,
    CALIDAD: 4,
    ENRUTADOR: 5,
  },

  // Colores de estado para la UI (coinciden con estados_solicitud.color_hex)
  ESTADO_COLORES: {
    'Pendiente': '#EF4444',
    'Georreferenciada': '#F59E0B',
    'Asignada': '#3B82F6',
    'En visita': '#8B5CF6',
    'Completada': '#10B981',
    'Cancelada': '#6B7280',
  },
};
