import { escapeHtml as esc, showToast } from './ui.js';
const base='index.php?route=api/operacion/';
async function api(action, data) {
  const response=await fetch(base+action,{credentials:'same-origin',...(data?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}:{})});
  const body=await response.json();
  if (!response.ok) throw new Error(body.message||body.errors?.join(', ')||'No se pudo completar la operación.');
  return body;
}
const option=(id,label)=>`<option value="${Number(id)}">${esc(label)}</option>`;
const table=(head,rows)=>`<div class="op-scroll"><table><thead><tr>${head.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.join('')||`<tr><td colspan="${head.length}">No hay registros.</td></tr>`}</tbody></table></div>`;
const cell=value=>`<td>${esc(String(value??''))}</td>`;
const pdfLink=id=>`<a class="op-link" href="${base}ruta-pdf&id=${Number(id)}">Descargar PDF</a>`;
export async function initOperations(role) {
  const root=document.getElementById('operations');if (!root) return;
  root.className='op-root';
  root.innerHTML='<p role="status">Cargando operación...</p>';
  try {
    const catalog=await api('catalogos');
    const stateFilter=document.getElementById('filtroEstado');
    if(stateFilter) stateFilter.innerHTML='<option value="">Todos los estados</option>'+catalog.estados.map(s=>option(s.id_estado,s.nombre_estado)).join('');
    root.replaceChildren();
    if(role===1) sites(root,catalog);
    if([1,4,5].includes(role)) await dashboard(root,role);
    if([1,5].includes(role)) await planner(root,catalog);
    if([1,2,3,5].includes(role)) await routes(root,catalog,role);
  } catch(error) {
    root.innerHTML=`<div class="op-panel" role="alert"><p>${esc(error.message)}</p><button type="button">Reintentar</button></div>`;
    root.querySelector('button').onclick=()=>initOperations(role);
  }
}
async function dashboard(root,role) {
  const {data}=await api('dashboard');const panel=document.createElement('section');panel.className='op-panel';
  const cards=[['Total de solicitudes',data.totales.total],['Radicadas hoy',data.totales.hoy],['Vencidas sin cierre',data.totales.vencidas],['Vencen en 3 días',data.totales.por_vencer]];
  panel.innerHTML=`<div class="op-heading"><h2>Seguimiento de solicitudes</h2>${role===1?`<a class="op-button" href="${base}reporte">Descargar informe PDF</a>`:''}</div><div class="op-cards">${cards.map(([label,value])=>`<article><p>${esc(label)}</p><strong>${Number(value)}</strong></article>`).join('')}</div><div class="op-columns"><div><h3>Distribución por estado</h3>${table(['Estado','Solicitudes'],data.estados.map(r=>`<tr>${cell(r.nombre_estado)}${cell(r.total)}</tr>`))}</div><div><h3>Radicaciones por sede</h3>${table(['Sede','Solicitudes'],data.sedes.map(r=>`<tr>${cell(r.nombre_sede)}${cell(r.total)}</tr>`))}</div></div><p>Rutas creadas: ${Number(data.rutas.total)} · Entregadas: ${Number(data.rutas.entregadas)}. Plazo de referencia: 15 días calendario desde la radicación.</p>`;
  root.append(panel);
}
async function planner(root,catalog) {
  const panel=document.createElement('section');panel.className='op-panel';root.append(panel);
  const {data}=await api('pendientes');
  const tomorrow=new Date();tomorrow.setDate(tomorrow.getDate()+1);const min=new Intl.DateTimeFormat('en-CA',{timeZone:'America/Bogota',year:'numeric',month:'2-digit',day:'2-digit'}).format(tomorrow);
  panel.innerHTML=`<h2>Crear hoja de ruta</h2><p>Selecciona 20 solicitudes georreferenciadas. La lista prioriza las más antiguas; el orden marcado en la tabla será el de la hoja. La carga puede requerir varias jornadas según la zona.</p><form id="routeForm"><div class="op-fields"><label>Encuestador<select name="id_encuestador" required><option value="">Seleccionar...</option>${catalog.encuestadores.map(u=>option(u.id_usuario,u.nombres+' '+u.apellidos)).join('')}</select></label><label>Punto de entrega<select name="id_sede" required><option value="">Seleccionar...</option>${catalog.sedes.map(s=>option(s.id_sede,s.nombre_sede)).join('')}</select></label><label>Fecha de inicio<input name="fecha_visita" type="date" min="${min}" value="${min}" required></label></div><label class="op-select-all"><input type="checkbox" id="firstTwenty"> Seleccionar las primeras 20 disponibles</label><p id="selectedCount" aria-live="polite">0 de 20 seleccionadas</p>${table(['Elegir','Solicitud','Ciudadano','Sector','Plazo restante'],data.map(s=>`<tr><td><input type="checkbox" name="request" value="${Number(s.id_solicitud)}" aria-label="Seleccionar ${esc(s.numero_solicitud)}"></td>${cell(s.numero_solicitud)}${cell(s.nombres+' '+s.apellidos)}${cell(s.nombre_comuna+' / '+s.barrio)}${cell(Number(s.dias_restantes)+' días')}</tr>`))}<button type="submit" ${data.length<20?'disabled':''}>Crear ruta con 20 solicitudes</button><p class="op-feedback" role="status"></p></form>`;
  const form=panel.querySelector('form');const boxes=[...form.querySelectorAll('[name=request]')];const count=()=>boxes.filter(b=>b.checked).length;
  const update=()=>{panel.querySelector('#selectedCount').textContent=`${count()} de 20 seleccionadas`;};
  boxes.forEach(b=>b.onchange=()=>{if(count()>20) b.checked=false;update();});
  panel.querySelector('#firstTwenty').onchange=e=>{boxes.forEach((b,i)=>b.checked=e.target.checked&&i<20);update();};
  form.onsubmit=async e=>{
    e.preventDefault();const status=form.querySelector('.op-feedback');const button=form.querySelector('[type=submit]');
    if(count()!==20){status.textContent='Selecciona exactamente 20 solicitudes.';return;}
    button.disabled=true;
    try {
      const payload=Object.fromEntries(new FormData(form));payload.solicitudes=boxes.filter(b=>b.checked).map(b=>Number(b.value));delete payload.request;
      const result=await api('crear-ruta',payload);showToast('Ruta creada',`Hoja #${result.id_ruta} guardada.`,'success');await initOperations(Number(document.body.dataset.role));
    } catch(error) {status.textContent=error.message;button.disabled=false;}
  };
}
async function routes(root,catalog,role) {
  const {data}=await api('rutas');const panel=document.createElement('section');panel.className='op-panel';root.append(panel);
  panel.innerHTML=`<h2>${role===3?'Mis hojas de ruta':'Hojas de ruta y entrega'}</h2>${[1,2].includes(role)?`<label>Punto desde el que se entrega<select id="deliverySite"><option value="">Seleccionar sede...</option>${catalog.sedes.map(s=>option(s.id_sede,s.nombre_sede)).join('')}</select></label><p>La entrega se registra una sola vez, en la mañana de la fecha programada y desde la sede de la ruta.</p>`:''}${table(['Ruta','Fecha de inicio','Encuestador','Punto de entrega','Carga','Entrega','Acciones'],data.map(r=>`<tr>${cell('#'+r.id_ruta)}${cell(r.fecha_visita)}${cell(r.encuestador)}${cell(r.nombre_sede)}${cell(r.total)}${cell(r.fecha_entrega||'Pendiente')}<td><button type="button" data-detail="${Number(r.id_ruta)}">Ver detalle</button> ${pdfLink(r.id_ruta)} ${[1,2].includes(role)&&!r.fecha_entrega?`<button type="button" data-deliver="${Number(r.id_ruta)}">Registrar entrega</button>`:''}</td></tr>`))}<p class="op-feedback" role="status"></p><div id="routeDetail"></div>`;
  panel.querySelectorAll('[data-detail]').forEach(b=>b.onclick=async()=>{
    const output=panel.querySelector('#routeDetail');output.textContent='Cargando detalle...';
    try {const {data:r}=await api('rutas&id='+b.dataset.detail);output.innerHTML=`<h3>Ruta #${Number(r.id_ruta)}</h3>${table(['Orden','Solicitud','Ciudadano','Celular','Dirección','Sector','Coordenadas'],r.solicitudes.map(s=>`<tr>${cell(s.orden)}${cell(s.numero_solicitud)}${cell(s.nombres+' '+s.apellidos)}${cell(s.telefono)}${cell(s.direccion)}${cell(s.barrio+' / '+s.nombre_comuna)}${cell(s.latitud+', '+s.longitud)}</tr>`))}`;}catch(error){output.textContent=error.message;}
  });
  panel.querySelectorAll('[data-deliver]').forEach(b=>b.onclick=async()=>{
    const status=panel.querySelector('.op-feedback');const site=panel.querySelector('#deliverySite').value;
    if(!site){status.textContent='Selecciona el punto de atención desde el que entregas.';return;}
    b.disabled=true;
    try{await api('entregar-ruta',{id_ruta:Number(b.dataset.deliver),id_sede:Number(site)});await initOperations(role);showToast('Entrega registrada','La hoja de ruta quedó entregada.','success');}catch(error){status.textContent=error.message;b.disabled=false;}
  });
}

function sites(root,catalog) {
  const panel=document.createElement('section');panel.className='op-panel';root.append(panel);
  panel.innerHTML=`<h2>Ubicaciones de las sedes</h2><p>Las referencias de los mapas oficiales ubican el sector. Para identificar una oficina real, registra su dirección, coordenadas y la fuente con la que verificaste esos datos.</p>${table(['Sede','Dirección','Estado'],catalog.sedes.map(s=>`<tr>${cell(s.nombre_sede)}${cell(s.direccion)}${cell(Number(s.ubicacion_confirmada)===1?'Configurada por administración':'Referencia territorial')}</tr>`))}<form id="siteForm"><div class="op-fields"><label>Sede<select name="id_sede" required><option value="">Seleccionar...</option>${catalog.sedes.map(s=>option(s.id_sede,s.nombre_sede)).join('')}</select></label><label>Latitud<input name="latitud" type="number" step="any" min="-90" max="90" required></label><label>Longitud<input name="longitud" type="number" step="any" min="-180" max="180" required></label></div><label>Dirección de la oficina<input name="direccion" maxlength="500" required></label><label>Fuente de verificación (documento, enlace o referencia de la entidad)<input name="fuente_ubicacion" maxlength="500" required></label><button type="submit">Guardar ubicación de la sede</button><p class="op-feedback" role="status"></p></form>`;
  const form=panel.querySelector('form');const select=form.elements.id_sede;
  select.onchange=()=>{
    const site=catalog.sedes.find(s=>String(s.id_sede)===select.value);
    for(const key of ['direccion','latitud','longitud','fuente_ubicacion']) form.elements[key].value=site?.[key]??'';
  };
  form.onsubmit=async event=>{
    event.preventDefault();const button=form.querySelector('button');button.disabled=true;
    try {await api('guardar-sede',Object.fromEntries(new FormData(form)));await initOperations(1);showToast('Sede actualizada','La configuración y su auditoría quedaron guardadas.','success');}
    catch(error){form.querySelector('.op-feedback').textContent=error.message;button.disabled=false;}
  };
}
