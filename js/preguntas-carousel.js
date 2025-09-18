(function(){
  const section = document.querySelector('.preguntas');
  if (!section) return;

  const track = section.querySelector('.preguntas-grid');
  if (!track) return;

  // crear controles
  const prev = document.createElement('button');
  prev.className = 'carousel-btn prev';
  prev.setAttribute('aria-label','Anterior');
  prev.innerHTML = '◀';
  const next = document.createElement('button');
  next.className = 'carousel-btn next';
  next.setAttribute('aria-label','Siguiente');
  next.innerHTML = '▶';
  section.appendChild(prev);
  section.appendChild(next);

  // dots
  const cards = Array.from(track.children);
  const dotsWrap = document.createElement('div');
  dotsWrap.className = 'carousel-dots';
  const dots = cards.map((c, i) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.addEventListener('click', ()=> scrollToIndex(i));
    dotsWrap.appendChild(b);
    return b;
  });
  section.appendChild(dotsWrap);

  let current = 0;
  const updateDots = ()=> {
    dots.forEach((d, i)=> d.classList.toggle('active', i === current));
  };

  function scrollToIndex(index) {
    if (index < 0) index = 0;
    if (index >= cards.length) index = cards.length - 1;
    const card = cards[index];
    if (!card) return;
    track.scrollTo({ left: card.offsetLeft - (track.clientWidth - card.clientWidth)/2, behavior: 'smooth' });
    current = index;
    updateDots();
  }

  prev.addEventListener('click', ()=> scrollToIndex(current - 1));
  next.addEventListener('click', ()=> scrollToIndex(current + 1));

  // update current on manual scroll (throttled)
  let to = null;
  track.addEventListener('scroll', ()=> {
    if (to) clearTimeout(to);
    to = setTimeout(()=> {
      // find nearest card to center
      const center = track.scrollLeft + track.clientWidth / 2;
      let nearest = 0, minD = Infinity;
      cards.forEach((c, i)=> {
        const cCenter = c.offsetLeft + c.clientWidth / 2;
        const d = Math.abs(center - cCenter);
        if (d < minD) { minD = d; nearest = i; }
      });
      current = nearest;
      updateDots();
    }, 80);
  });

  // init: scroll to preselected/current
  scrollToIndex(current);

  // keyboard navigation
  section.addEventListener('keydown', (e)=>{
    if (e.key === 'ArrowLeft') prev.click();
    if (e.key === 'ArrowRight') next.click();
  });

})();