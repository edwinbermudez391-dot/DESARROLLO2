/**
 * Gestión de autenticación del lado del cliente
 * La sesión PHP es la fuente de autenticación. sessionStorage es solo una caché.
 */

import { CONFIG } from './config.js';

const USER_KEY = 'encuestas_user';

export function saveUser(user) {
  try { sessionStorage.setItem(USER_KEY, JSON.stringify(user)); }
  catch (error) { console.warn('No se pudo guardar el usuario local:', error); }
}

export function getCurrentUser() {
  try {
    const raw = sessionStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (error) {
    console.warn('No se pudo leer el usuario local:', error);
    return null;
  }
}

export function clearUser() {
  try { sessionStorage.removeItem(USER_KEY); }
  catch (error) { console.warn('No se pudo limpiar el usuario local:', error); }
}

export function isAuthenticated() {
  return !!getCurrentUser();
}

export function hasRole(roleId) {
  const user = getCurrentUser();
  return user && Number(user.id_rol) === Number(roleId);
}

export const ROLE_ROUTES = Object.freeze({
  1: 'index.php?route=admin',
  2: 'index.php?route=ventanilla',
  3: 'index.php?route=encuestador',
  4: 'index.php?route=calidad',
  5: 'index.php?route=enrutador',
});

export function redirectToRole(roleId) {
  window.location.replace(ROLE_ROUTES[Number(roleId)] || 'index.php?route=login');
}

export async function requireViewRole(roleId) {
  const response = await fetch(`${CONFIG.API_BASE}/sesion`, {
    credentials: 'include',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  });
  if (response.status === 401 || response.status === 403) {
    clearUser();
    window.location.replace('index.php?route=login');
    return null;
  }
  const data = await response.json();
  if (!response.ok || !data.success || !data.user) {
    throw new Error(data.message || 'No se pudo verificar la sesión.');
  }
  // Un rol desconocido nunca puede habilitar una vista, aunque coincida con roleId.
  if (!ROLE_ROUTES[Number(data.user.id_rol)]) {
    clearUser();
    redirectToRole(data.user.id_rol);
    return null;
  }
  saveUser(data.user);
  if (Number(data.user.id_rol) !== Number(roleId)) {
    redirectToRole(data.user.id_rol);
    return null;
  }
  return data.user;
}

export async function logout() {
  try {
    const response = await fetch(`${CONFIG.API_BASE}${CONFIG.ENDPOINTS.LOGOUT}`, {
      method: 'POST',
      credentials: 'include',
    });
    if (!response.ok && response.status !== 401) {
      throw new Error('El servidor no pudo cerrar la sesión.');
    }
    clearUser();
    window.location.replace('index.php?route=login');
  } catch (e) {
    console.error('No se pudo cerrar la sesión:', e);
    window.alert('No se pudo cerrar la sesión. Inténtalo nuevamente.');
  }
}
