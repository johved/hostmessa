(() => {
  const gallery = document.getElementById('gallery');
  if (!gallery) return;
  const items = Array.from(gallery.querySelectorAll('.gallery-item'));
  const box = document.getElementById('lightbox');
  const img = document.getElementById('lb-img');
  const caption = document.getElementById('lb-caption');
  const count = document.getElementById('lb-count');
  let current = 0;

  function show(i) {
    current = (i + items.length) % items.length;
    const d = items[current].dataset;
    img.src = d.full;
    img.alt = d.caption;
    caption.textContent = d.caption;
    count.textContent = `${current + 1} av ${items.length}`;
  }

  gallery.addEventListener('click', (e) => {
    const item = e.target.closest('.gallery-item');
    if (!item) return;
    show(Number(item.dataset.index));
    box.showModal();
  });
  document.getElementById('lb-prev').addEventListener('click', () => show(current - 1));
  document.getElementById('lb-next').addEventListener('click', () => show(current + 1));
  document.getElementById('lb-close').addEventListener('click', () => box.close());
  box.addEventListener('click', (e) => { if (e.target === box) box.close(); });
  box.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });

  // Sveip til siden på mobil.
  let startX = null;
  box.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', (e) => {
    if (startX === null) return;
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
    startX = null;
  });
})();
