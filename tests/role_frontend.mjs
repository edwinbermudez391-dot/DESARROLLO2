// Ejecutar con node tests/role_frontend.mjs. No requiere dependencias.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = (await readFile(new URL('../public/assets/js/auth.js', import.meta.url), 'utf8'))
  .replace("import { CONFIG } from './config.js';", "const CONFIG = { API_BASE: 'http://localhost/index.php?route=api', ENDPOINTS: { LOGOUT: '/logout' } };");
const storage = new Map();
globalThis.sessionStorage = {
  setItem: (key, value) => storage.set(key, value),
  getItem: (key) => storage.get(key),
  removeItem: (key) => storage.delete(key),
};
let destination;
globalThis.window = { location: { replace: (url) => { destination = url; } }, alert: () => {} };
const auth = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const routes = { 1: 'admin', 2: 'ventanilla', 3: 'encuestador', 4: 'calidad', 5: 'enrutador' };

for (const [role, route] of Object.entries(routes)) {
  const view = await readFile(new URL(`../app/Views/${route}.html`, import.meta.url), 'utf8');
  assert.ok(view.includes(`data-role="${role}"`), `${route}: identificador de rol`);
  assert.ok(view.includes(`href="index.php?route=${route}"`), `${route}: navegación propia`);
  assert.ok(view.includes('aria-label="Navegación principal"'), `${route}: menú accesible`);
  assert.ok(view.includes('id="btnLogout"'), `${route}: cierre de sesión compartido`);
  assert.ok(view.includes('<main hidden'), `${route}: contenido oculto hasta verificar sesión`);
  assert.ok(view.includes('assets/css/style.css'), `${route}: estilos compartidos`);
  auth.redirectToRole(role);
  assert.equal(destination, `index.php?route=${route}`);
  for (const targetRole of Object.keys(routes)) {
    // Un usuario local falsificado no sustituye al rol real de la sesión PHP.
    auth.saveUser({ id_rol: Number(targetRole) });
    globalThis.fetch = async () => ({ status: 200, ok: true, json: async () => ({ success: true, user: { id_rol: Number(role) } }) });
    destination = null;
    const user = await auth.requireViewRole(Number(targetRole));
    if (role === targetRole) {
      assert.equal(user.id_rol, Number(role));
      assert.equal(destination, null);
    } else {
      assert.equal(user, null);
      assert.equal(destination, `index.php?route=${route}`);
    }
  }
}
globalThis.fetch = async () => ({ status: 401, ok: false });
assert.equal(await auth.requireViewRole(3), null);
assert.equal(destination, 'index.php?route=login');
assert.equal(auth.getCurrentUser(), null);

for (const role of [null, undefined, 0, 99, 'admin', '']) {
  auth.redirectToRole(role);
  assert.equal(destination, 'index.php?route=login');
  auth.saveUser({ id_rol: 1 });
  globalThis.fetch = async () => ({ status: 200, ok: true, json: async () => ({ success: true, user: { id_rol: role } }) });
  assert.equal(await auth.requireViewRole(role), null);
  assert.equal(auth.getCurrentUser(), null);
  assert.equal(destination, 'index.php?route=login');
}

auth.saveUser({ id_rol: 1 });
globalThis.fetch = async () => ({ status: 403, ok: false });
assert.equal(await auth.requireViewRole(1), null);
assert.equal(destination, 'index.php?route=login');
assert.equal(auth.getCurrentUser(), null);

globalThis.fetch = async () => ({ status: 503, ok: false, json: async () => ({ message: 'Unavailable' }) });
await assert.rejects(auth.requireViewRole(3), /Unavailable/);

globalThis.fetch = async () => { throw new TypeError('Network error'); };
await assert.rejects(auth.requireViewRole(3), /Network error/);

globalThis.fetch = async () => ({ status: 200, ok: true });
auth.saveUser({ id_rol: 3 });
await auth.logout();
assert.equal(auth.getCurrentUser(), null);
assert.equal(destination, 'index.php?route=login');
console.log('PASS: cinco plantillas, rutas, 25 combinaciones de roles, roles inválidos, sesión expirada, acceso denegado, errores de servidor/red y logout.');
