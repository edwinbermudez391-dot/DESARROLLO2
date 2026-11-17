import { mountRolePage } from './role-page.js';

mountRolePage(3, (user) => {
  const name = [user.nombres, user.apellidos].filter(Boolean).join(' ') || user.username;
  document.getElementById('welcomeMessage').textContent = `Bienvenido/a, ${name}. Este es tu espacio de trabajo de campo.`;
  document.getElementById('fechaHoy').textContent = new Intl.DateTimeFormat('es-CO', { dateStyle: 'long' }).format(new Date());
  document.getElementById('dashboard').hidden = false;
});
