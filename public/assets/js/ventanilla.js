import { saveRequest, getTerritory } from './api.js';
import { showToast } from './ui.js';
import { CONFIG } from './config.js';

export async function initVentanilla({ user }) {
  document.getElementById('userName').textContent = `${user.nombres} ${user.apellidos}`;
  document.getElementById('userRole').textContent = user.nombre_rol;
  const [territory, response] = await Promise.all([
    getTerritory(), fetch('index.php?route=api/operacion/catalogos', { credentials: 'same-origin' }),
  ]);
  if (!response.ok) throw new Error('No se pudieron cargar las sedes.');
  const { sedes } = await response.json();
  const map = L.map('map', { zoomControl: true }).setView(CONFIG.MAP_CENTER, CONFIG.MAP_ZOOM);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors', maxZoom: 19,
  }).addTo(map);
  const marker = L.marker(CONFIG.MAP_CENTER, { draggable: true }).addTo(map);
  const form = document.getElementById('requestForm');
  const sectorSelect = document.getElementById('id_comuna');
  const barrioSelect = document.getElementById('barrio');
  const ruralInput = document.getElementById('barrioRural');
  const siteSelect = document.getElementById('id_sede');
  const status = document.getElementById('territoryStatus');
  let current = [...CONFIG.MAP_CENTER];
  function clearCoordinates() {
    for (const id of ['latInput', 'lngInput', 'latHidden', 'lngHidden']) document.getElementById(id).value = '';
  }
  function useCoordinates(lat, lng) {
    current = [Number(lat.toFixed(7)), Number(lng.toFixed(7))];
    document.getElementById('latInput').value = current[0].toFixed(7);
    document.getElementById('lngInput').value = current[1].toFixed(7);
    document.getElementById('latHidden').value = current[0];
    document.getElementById('lngHidden').value = current[1];
    marker.setLatLng(current);
    map.panTo(current);
  }
  function referencePoint(lat, lng) {
    current = [Number(lat), Number(lng)];
    marker.setLatLng(current);
    map.setView(current, 15);
    clearCoordinates();
  }
  map.on('click', e => useCoordinates(e.latlng.lat, e.latlng.lng));
  marker.on('dragend', e => { const p = e.target.getLatLng(); useCoordinates(p.lat, p.lng); });
  document.getElementById('btnRecenter').onclick = () => {
    referencePoint(...CONFIG.MAP_CENTER); map.setZoom(CONFIG.MAP_ZOOM);
  };
  document.getElementById('btnUseCoords').onclick = () => {
    useCoordinates(...current);
    showToast('Punto seleccionado', 'Comprueba que corresponde al domicilio del ciudadano.', 'info');
  };
  document.getElementById('useGeolocation').onclick = () => {
    if (!navigator.geolocation) return showToast('Ubicación', 'Este navegador no soporta geolocalización.', 'error');
    navigator.geolocation.getCurrentPosition(
      position => { useCoordinates(position.coords.latitude, position.coords.longitude); map.setZoom(16); },
      () => showToast('Ubicación', 'No se pudo obtener la ubicación del dispositivo.', 'error'),
    );
  };
  sedes.forEach(site => {
    siteSelect.add(new Option(site.nombre_sede, site.id_sede));
    if (site.latitud !== null && site.longitud !== null) {
      const label = document.createElement('div');
      label.textContent = `${site.nombre_sede}. ${Number(site.ubicacion_confirmada) === 1 ? 'Ubicación configurada por administración.' : 'Referencia territorial; dirección de la sede pendiente de configurar.'} ${site.direccion}`;
      L.circleMarker([Number(site.latitud), Number(site.longitud)], {
        radius: 9, color: Number(site.es_oficina_principal) === 1 ? '#15803d' : '#7c3aed', fillOpacity: .8,
      }).addTo(map).bindPopup(label);
    }
  });
  territory.sectores.forEach(s => sectorSelect.add(new Option(
    s.tipo === 'Urbana' ? s.nombre_comuna : `Corregimiento ${s.nombre_comuna}`, s.id_comuna,
  )));
  function updateSector() {
    const sector = territory.sectores.find(s => String(s.id_comuna) === sectorSelect.value);
    const rural = Boolean(sector && sector.tipo !== 'Urbana');
    barrioSelect.replaceChildren(new Option('Seleccionar barrio o sector...', ''));
    territory.barrios.filter(b => String(b.id_comuna) === sectorSelect.value)
      .forEach(b => barrioSelect.add(new Option(`${b.codigo_barrio} · ${b.nombre_barrio}`, b.id_barrio)));
    document.getElementById('urbanBarrio').hidden = rural;
    document.getElementById('ruralBarrio').hidden = !rural;
    barrioSelect.disabled = rural;
    ruralInput.disabled = !rural;
    barrioSelect.required = !rural;
    ruralInput.required = rural;
    ruralInput.value = '';
    document.getElementById('corregimiento').value = rural ? sector.nombre_comuna : '';
    status.textContent = rural
      ? 'Corregimiento validado. Escribe la vereda o sector y ubica el domicilio en el mapa.'
      : 'Barrios del catálogo IDESC 2026. Selecciona el barrio y ajusta el punto al domicilio real.';
    clearCoordinates();
  }
  sectorSelect.addEventListener('change', updateSector);
  barrioSelect.addEventListener('change', () => {
    const barrio = territory.barrios.find(b => String(b.id_barrio) === barrioSelect.value);
    if (!barrio) { clearCoordinates(); return; }
    referencePoint(barrio.latitud_referencia, barrio.longitud_referencia);
    status.textContent = `Referencia de ${barrio.nombre_barrio}. Haz clic o arrastra el marcador al domicilio del ciudadano antes de guardar sus coordenadas.`;
  });
  updateSector();
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const button = form.querySelector('button[type="submit"]');
    const original = button.innerHTML;
    const data = Object.fromEntries(new FormData(form));
    for (const [key, id] of [['latitud', 'latHidden'], ['longitud', 'lngHidden']]) {
      const value = document.getElementById(id).value;
      data[key] = value === '' ? null : Number(value);
    }
    if (!/^[0-9]{10}$/.test(data.telefono)) return showToast('Celular inválido', 'Debe contener 10 dígitos.', 'error');
    button.disabled = true; button.textContent = 'Guardando...';
    try {
      const result = await saveRequest(data);
      showToast('Solicitud guardada', `${result.data.numero_solicitud} · ${result.data.estado}`, 'success');
      form.reset(); updateSector(); referencePoint(...CONFIG.MAP_CENTER); map.setZoom(CONFIG.MAP_ZOOM);
    } catch (error) {
      showToast('No se pudo guardar', error.message, 'error');
    } finally { button.disabled = false; button.innerHTML = original; }
  });
}
