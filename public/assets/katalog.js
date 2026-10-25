(() => {
  const grid = document.getElementById('grid');
  if (!grid) return;
  const search = document.getElementById('search');
  const chips = document.getElementById('chips');
  const empty = document.getElementById('empty');
  const dlg = document.getElementById('dlg');
  let activeCat = '';

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
    const photo = card.querySelector('.photo').cloneNode(true);
    photo.querySelector('.lot')?.remove();
    document.getElementById('dlg-photo').replaceChildren(...photo.childNodes);
    document.getElementById('dlg-cat').textContent = d.cat;
    document.getElementById('dlg-title').textContent = d.title;
    document.getElementById('dlg-desc').textContent = d.desc;
    document.getElementById('dlg-lot').textContent = d.lot;
    document.getElementById('dlg-pris').textContent = d.price;
    document.getElementById('dlg-giver').textContent = d.giver;
    dlg.showModal();
  });
  document.getElementById('dlg-close').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
})();
