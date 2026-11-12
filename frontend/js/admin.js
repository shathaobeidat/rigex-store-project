/* Minimal progressive enhancement; core navigation and forms work without JavaScript. */
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('nav-toggle');
  if (!toggle) return;
  const periodPicker = document.querySelector('.period-picker');
  const workspaceMenu = document.querySelector('.workspace-menu');
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (workspaceMenu?.open) { workspaceMenu.open = false; workspaceMenu.querySelector('summary')?.focus(); }
      if (toggle.checked) toggle.checked = false;
      if (periodPicker?.open) { periodPicker.open = false; periodPicker.querySelector('summary')?.focus(); }
    }
  });





  document.addEventListener('click', (event) => {
    if (workspaceMenu?.open && !workspaceMenu.contains(event.target)) workspaceMenu.open = false;
    if (periodPicker?.open && !periodPicker.contains(event.target)) periodPicker.open = false;
  });


  
  document.querySelectorAll('.sidebar a').forEach(link => {
    link.addEventListener('click', () => { toggle.checked = false; });
  });
});
