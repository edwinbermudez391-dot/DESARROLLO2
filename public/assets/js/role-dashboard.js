import { mountRolePage } from './role-page.js';

mountRolePage(Number(document.body.dataset.role), (user) => {
  const name = [user.nombres, user.apellidos].filter(Boolean).join(' ') || user.username;
  document.getElementById('welcomeMessage').textContent = `Bienvenido/a, ${name}.`;
  document.getElementById('fechaHoy').textContent = new Intl.DateTimeFormat('es-CO', { dateStyle: 'long' }).format(new Date());
});
