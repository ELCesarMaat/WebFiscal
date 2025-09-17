let lastScroll = 0;
const header = document.querySelector('header');

window.addEventListener('scroll', () => {
  const currentScroll = window.pageYOffset;
  if (currentScroll > lastScroll && currentScroll > 100) {
    // Scroll hacia abajo
    header.classList.add('header-hide');
  } else {
    // Scroll hacia arriba
    header.classList.remove('header-hide');
  }
  lastScroll = currentScroll;
});
document.querySelectorAll('.servicio-card').forEach(card => {
  card.addEventListener('click', function(e) {
    // si el click viene de un enlace, no lo bloquees
    if (e.target.closest('a')) return;

    e.preventDefault();
    document.querySelectorAll('.servicio-card').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
  });
});
