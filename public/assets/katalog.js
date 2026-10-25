(() => {
  const grid = document.getElementById('grid');
  if (!grid) return;
  const search = document.getElementById('search');
  const chips = document.getElementById('chips');
  const empty = document.getElementById('empty');
  const dlg = document.getElementById('dlg');
  let activeCat = '';
  let images = [];
  let current = 0;

  function showImage(i) {
    current = (i + images.length) % images.length;
    const img = document.createElement('img');
    img.src = images[current];
    img.alt = '';
    document.getElementById('dlg-photo').replaceChildren(img);
    document.querySelectorAll('#dlg-thumbs button').forEach((b, j) => b.setAttribute('aria-current', j === current));
  }

  function filter() {
    const q = search.value.trim().toLowerCase();
    let shown = 0;
    grid.querySelectorAll('.card').forEach((card) => {
      const d = card.dataset;
      const match = (!activeCat || d.cat === activeCat) && (!q || (d.title + ' ' + d.desc + ' ' + d.cat).toLowerCase().includes(q));
      card.hidden = !match;
      if (match) shown++;
    });
    empty.hidden = shown > 0;
  }

  search.addEventListener('input', filter);
  chips.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    activeCat = chip.dataset.cat;
    chips.querySelectorAll('.chip').forEach((c) => c.setAttribute('aria-pressed', c === chip));
    filter();
  });

  grid.addEventListener('click', (e) => {
    const card = e.target.closest('.card');
    if (!card) return;
    const d = card.dataset;
    images = JSON.parse(d.images || '[]');
    const thumbs = document.getElementById('dlg-thumbs');
    thumbs.replaceChildren(...images.map((src, i) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('aria-label', `Bilde ${i + 1}`);
      const im = document.createElement('img');
      im.src = src.replace(/\.jpg$/, '-t.jpg');
      im.onerror = () => { im.onerror = null; im.src = src; };
      im.alt = '';
      b.appendChild(im);
      b.addEventListener('click', () => showImage(i));
      return b;
    }));
    const several = images.length > 1;
    thumbs.hidden = !several;
    document.getElementById('dlg-prev').hidden = !several;
    document.getElementById('dlg-next').hidden = !several;
    if (images.length) {
      showImage(0);
    } else {
      const photo = card.querySelector('.photo').cloneNode(true);
      photo.querySelector('.lot')?.remove();
      document.getElementById('dlg-photo').replaceChildren(...photo.childNodes);
    }
    document.getElementById('dlg-cat').textContent = d.cat;
    document.getElementById('dlg-title').textContent = d.title;
    document.getElementById('dlg-desc').textContent = d.desc;
    document.getElementById('dlg-lot').textContent = d.lot;
    document.getElementById('dlg-pris').textContent = d.price;
    document.getElementById('dlg-giver').textContent = d.giver;
    dlg.showModal();
  });
  document.getElementById('dlg-close').addEventListener('click', () => dlg.close());
  document.getElementById('dlg-prev').addEventListener('click', () => showImage(current - 1));
  document.getElementById('dlg-next').addEventListener('click', () => showImage(current + 1));
  dlg.addEventListener('keydown', (e) => {
    if (images.length < 2) return;
    if (e.key === 'ArrowLeft') showImage(current - 1);
    if (e.key === 'ArrowRight') showImage(current + 1);
  });
  let startX = null;
  dlg.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; }, { passive: true });
  dlg.addEventListener('touchend', (e) => {
    if (startX === null || images.length < 2) return;
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) showImage(current + (dx < 0 ? 1 : -1));
    startX = null;
  });
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
})();
