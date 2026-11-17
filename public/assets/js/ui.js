/**
 * Utilidades de interfaz de usuario
 * Notificaciones toast, formato de fechas/coordenadas y badges de estado.
 */

export const ESTADO_CONFIG = {
  'Pendiente': { color: 'bg-red-100 text-red-700', icon: 'fas fa-clock' },
  'Georreferenciada': { color: 'bg-amber-100 text-amber-700', icon: 'fas fa-map-marker-alt' },
  'Asignada': { color: 'bg-blue-100 text-blue-700', icon: 'fas fa-user-tie' },
  'En visita': { color: 'bg-purple-100 text-purple-700', icon: 'fas fa-walking' },
  'Completada': { color: 'bg-green-100 text-green-700', icon: 'fas fa-check-circle' },
  'Cancelada': { color: 'bg-slate-100 text-slate-700', icon: 'fas fa-ban' },
};

/**
 * Muestra una notificación toast flotante.
 * @param {string} title Título principal
 * @param {string} body Texto secundario
 * @param {'success'|'error'|'info'|'warn'} type Tipo de toast
 */
export function showToast(title, body, type = 'info') {
  // Remover toast existente si hay
  const existing = document.getElementById('toastContainer');
  if (existing) existing.remove();

  const container = document.createElement('div');
  container.id = 'toastContainer';
  container.className = 'fixed top-5 right-5 z-[60] flex flex-col gap-2 pointer-events-none';

  const toast = document.createElement('div');
  const icons = {
    success: 'fas fa-check-circle text-green-500',
    error: 'fas fa-exclamation-circle text-red-500',
    info: 'fas fa-info-circle text-blue-500',
    warn: 'fas fa-exclamation-triangle text-amber-500',
  };
  const bg = {
    success: 'bg-green-500',
    error: 'bg-red-500',
    info: 'bg-blue-500',
    warn: 'bg-amber-500',
  };

  toast.className = `${bg[type]} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 min-w-[260px] fade-in`;
  toast.innerHTML = `
    <i class="${icons[type]}"></i>
    <div>
      <div class="font-semibold text-sm">${escapeHtml(title)}</div>
      <div class="text-xs opacity-90 mt-0.5">${escapeHtml(body)}</div>
    </div>
    <button onclick="this.closest('.${bg[type]}').remove()" class="absolute right-2 top-2 text-white/70 hover:text-white">
      <i class="fas fa-times"></i>
    </button>
  `;

  container.appendChild(toast);
  document.body.appendChild(container);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-8px)';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

/**
 * Escapa HTML para evitar XSS al renderizar datos dinámicos.
 */
export function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = String(text);
  return div.innerHTML;
}

/**
 * Formatea una fecha ISO / datetime de MySQL a formato legible es-CO.
 */
export function formatDateTime(dt) {
  if (!dt) return '—';
  try {
    return new Date(dt).toLocaleString('es-CO', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (_) {
    return dt;
  }
}

/**
 * Formatea coordenadas a string con 5 decimales.
 */
export function formatCoord(lat, lng) {
  return lat !== null && lng !== null
    ? `${lat.toFixed(5)}, ${lng.toFixed(5)}`
    : 'Sin coordenadas';
}

/**
 * Retorna clases CSS de badge para un estado dado.
 */
export function createBadge(estadoNombre) {
  const cfg = ESTADO_CONFIG[estadoNombre] || ESTADO_CONFIG['Pendiente'];
  return `<span class="badge ${cfg.color}"><i class="${cfg.icon}"></i> ${escapeHtml(estadoNombre)}</span>`;
}
