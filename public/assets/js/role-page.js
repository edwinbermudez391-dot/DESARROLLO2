/** Inicializa cada pantalla solo después de verificar su rol con PHP. */
import { initOperations } from './operations.js';
import { requireViewRole, logout } from './auth.js';

export function mountRolePage(roleId, initialize) {
  const main = document.querySelector('main');
  const status = document.createElement('div');
  status.className = 'max-w-7xl mx-auto px-4 py-4 text-sm text-slate-600';
  status.setAttribute('role', 'status');
  main.before(status);
  let initialized = false;
  let checking = false;
  window.app = { logout };
  document.getElementById('btnLogout')?.addEventListener('click', logout);

  async function checkSession() {
    if (checking) return;
    checking = true;
    main.hidden = true;
    status.hidden = false;
    status.textContent = 'Verificando sesión...';
    try {
      const user = await requireViewRole(roleId);
      if (!user) return;
      document.getElementById('userName').textContent = [user.nombres, user.apellidos].filter(Boolean).join(' ') || user.username;
      document.getElementById('userRole').textContent = user.nombre_rol;
      main.hidden = false;
      if (!initialized) {
        await initialize(user);
        await initOperations(roleId);
        initialized = true;
      }
      status.hidden = true;
    } catch (error) {
      main.hidden = true;
      console.error('No se pudo cargar la pantalla:', error);
      status.textContent = 'No se pudo cargar la pantalla. Comprueba la conexión e inténtalo nuevamente. ';
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'underline font-medium text-blue-600';
      retry.textContent = 'Reintentar';
      retry.addEventListener('click', checkSession);
      status.append(retry);
    } finally {
      checking = false;
    }
  }

  checkSession();
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) checkSession();
  });
}
