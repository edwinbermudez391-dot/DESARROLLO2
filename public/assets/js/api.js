/**
 * Capa de llamadas al backend (API)
 * Todas las funciones retornan promesas con la respuesta JSON parseada.
 */

import { CONFIG } from './config.js';

async function request(endpoint, options = {}) {
  const url = `${CONFIG.API_BASE}${endpoint}`;
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  };

  const res = await fetch(url, {
    ...options,
    headers,
    credentials: 'include', // enviar cookies de sesión PHP si las hay
  });

  let data;
  try {
    data = await res.json();
  } catch (cause) {
    console.error('La API no devolvió JSON válido:', cause);
    throw new Error(`La API devolvió una respuesta inválida (HTTP ${res.status}). Verifica la ruta del servidor.`);
  }

  if (!res.ok) {
    const err = new Error(data.message || data.errors?.join(', ') || 'Error en el servidor');
    err.status = res.status;
    err.data = data;
    throw err;
  }

  return data;
}

/* ---------- Autenticación ---------- */
export function login(username, password) {
  return request(CONFIG.ENDPOINTS.LOGIN, {
    method: 'POST',
    body: JSON.stringify({ username, password }),
  });
}

export function logout() {
  return request(CONFIG.ENDPOINTS.LOGOUT, { method: 'POST' });
}

/* ---------- Catálogos ---------- */
export function getComunas() {
  return request(CONFIG.ENDPOINTS.COMUNAS);
}

/* ---------- Solicitudes ---------- */
export function saveRequest(data) {
  return request(CONFIG.ENDPOINTS.SAVE_REQUEST, {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export function saveGeolocation(solicitudId, latitud, longitud) {
  return request(CONFIG.ENDPOINTS.SAVE_GEOLOCATION, {
    method: 'POST',
    body: JSON.stringify({
      solicitud_id: solicitudId,
      latitud,
      longitud,
    }),
  });
}

/**
 * Obtiene solicitudes del día para la bitácora del enrutador.
 * @param {number|null} idComuna filtro opcional por comuna
 * @param {number|string|null} idEstado filtro opcional por estado
 */
export function getTodayRequests(idComuna = null, idEstado = null) {
  const qs = new URLSearchParams();
  if (idComuna) qs.append('comuna', idComuna);
  if (idEstado) qs.append('estado', idEstado);
  const query = qs.toString() ? `&${qs.toString()}` : '';
  return request(`${CONFIG.ENDPOINTS.TODAY_REQUESTS}${query}`);
}

export function getTerritory() { return request('/territorio'); }
