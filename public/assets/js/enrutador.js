/**
 * Módulo: Enrutador — Bitácora diaria de solicitudes
 * Consume API: getTodayRequests, getComunas
 */

import { getTodayRequests, getComunas } from './api.js';
import { showToast, formatDateTime, createBadge, escapeHtml } from './ui.js';

export function initEnrutador({ user }) {
  const nameEl = document.getElementById('userName');
  const roleEl = document.getElementById('userRole');
  if (nameEl) nameEl.textContent = `${user.nombres} ${user.apellidos}`;
  if (roleEl) {
    roleEl.textContent = user.nombre_rol;
    roleEl.className =
      'bg-indigo-100 text-indigo-700 text-[10px] px-2 py-0.5 rounded-full uppercase font-bold';
  }

  window.app = { logout: () => import('./auth.js').then(({ logout }) => logout()) };

  document.getElementById('fechaHoy').textContent = new Date().toLocaleDateString('es-CO');

  let todasSolicitudes = [];

  const tbody = document.getElementById('tableBody');
  const emptyState = document.getElementById('emptyState');
  const totalBadge = document.getElementById('totalBadge');

  const ESTADO_CONFIG = {
    'Pendiente': { color: 'bg-red-100 text-red-700', icon: 'fas fa-clock' },
    'Georreferenciada': { color: 'bg-amber-100 text-amber-700', icon: 'fas fa-map-marker-alt' },
    'Asignada': { color: 'bg-blue-100 text-blue-700', icon: 'fas fa-user-tie' },
    'En visita': { color: 'bg-purple-100 text-purple-700', icon: 'fas fa-walking' },
    'Completada': { color: 'bg-green-100 text-green-700', icon: 'fas fa-check-circle' },
    'Cancelada': { color: 'bg-slate-100 text-slate-700', icon: 'fas fa-ban' },
  };

  // ---------- Cargar filtros ----------
  async function loadFilters() {
    try {
      const { data: comunas } = await getComunas();
      const sel = document.getElementById('filtroComuna');
      comunas.forEach((c) => {
        const opt = document.createElement('option');
        opt.value = c.id_comuna;
        opt.textContent = `${c.codigo_comuna} - ${c.nombre_comuna}`;
        sel.appendChild(opt);
      });
    } catch (e) {
      console.error(e);
    }
  }
  loadFilters();

  // ---------- Render tabla ----------
  function renderTable(rows) {
    tbody.innerHTML = '';

    if (!rows || rows.length === 0) {
      emptyState.classList.remove('hidden');
      totalBadge.textContent = '0 solicitudes';
      return;
    }
    emptyState.classList.add('hidden');
    totalBadge.textContent = `${rows.length} solicitud${rows.length !== 1 ? 'es' : ''}`;

    rows.forEach((original, i) => {
      const s = { ...original };
      for (const key of ['numero_solicitud','nombres','apellidos','telefono','barrio']) s[key] = escapeHtml(s[key]);
      s.comuna = { codigo: escapeHtml(original.comuna.codigo), nombre: escapeHtml(original.comuna.nombre) };
      s.usuario_creacion = { nombres: escapeHtml(original.usuario_creacion.nombres), apellidos: escapeHtml(original.usuario_creacion.apellidos) };
      const cfg = ESTADO_CONFIG[s.estado.nombre] || ESTADO_CONFIG['Pendiente'];
      const tieneCoords = s.latitud && s.longitud;

      const tr = document.createElement('tr');
      tr.className = 'hover:bg-slate-50 transition fade-in';
      tr.innerHTML = `
        <td class="px-6 py-4 text-sm font-medium text-slate-700">${i + 1}</td>
        <td class="px-6 py-4 text-sm text-slate-900 font-mono">${s.numero_solicitud}</td>
        <td class="px-6 py-4 text-sm text-slate-800">${s.nombres} ${s.apellidos}</td>
        <td class="px-6 py-4 text-sm text-slate-600 hidden md:table-cell">${s.telefono}</td>
        <td class="px-6 py-4 text-sm text-slate-700">
          <div class="font-medium">${s.barrio}</div>
          <div class="text-xs text-slate-500">${s.comuna.codigo} - ${s.comuna.nombre}</div>
        </td>
        <td class="px-6 py-4">
          <span class="badge ${cfg.color}"><i class="${cfg.icon}"></i> ${escapeHtml(s.estado.nombre)}</span>
        </td>
        <td class="px-6 py-4 text-sm">
          ${tieneCoords
            ? `<span class="text-indigo-600 flex items-center gap-1 cursor-pointer" title="Ver en mapa">
                 <i class="fas fa-map-marker-alt"></i> ${s.latitud.toFixed(5)}, ${s.longitud.toFixed(5)}
               </span>`
            : `<span class="text-slate-400 italic">Sin coordenadas</span>`}
        </td>
        <td class="px-6 py-4 text-sm text-slate-600">${formatDateTime(s.fecha_creacion)}</td>
        <td class="px-6 py-4 text-sm text-right text-slate-500">
          <button class="text-indigo-600 hover:text-indigo-800" title="Detalles">
            <i class="fas fa-eye"></i>
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  // ---------- Cargar datos ----------
  async function loadData() {
    const comuna = document.getElementById('filtroComuna').value || null;
    const estado = document.getElementById('filtroEstado').value || null;

    try {
      const { data } = await getTodayRequests(comuna, estado);

      todasSolicitudes = data;
      renderTable(data);
    } catch (err) {
      showToast('Error', 'No se pudo cargar la bitácora del día', 'error');
    }
  }

  document.getElementById('filtroComuna').addEventListener('change', loadData);
  document.getElementById('filtroEstado').addEventListener('change', loadData);
  document.getElementById('btnLimpiarFiltros').addEventListener('click', () => {
    document.getElementById('filtroComuna').value = '';
    document.getElementById('filtroEstado').value = '';
    loadData();
  });
  document.getElementById('btnRefresh').addEventListener('click', loadData);

  // Auto-refresh cada 60 segundos
  setInterval(loadData, 60000);

  loadData();
}
