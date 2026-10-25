// Viser hovedbildet med en gang det er valgt, og lar komiteen velge utsnittet som vises
// på kortet i katalogen (4:3). Utsnittet sendes som «x,y,b,h» i andeler av bildet.
(() => {
  const box = document.getElementById('cropper');
  if (!box) return;
  const input = document.getElementById('f-image');
  const img = document.getElementById('crop-img');
  const stage = document.getElementById('crop-stage');
  const frame = document.getElementById('crop-frame');
  const zoom = document.getElementById('crop-zoom');
  const reset = document.getElementById('crop-reset');
  const preview = document.getElementById('crop-preview');
  const out = document.getElementById('f-crop');
  const label = document.getElementById('f-image-label');
  const remove = document.getElementById('f-remove');
  const RATIO = 4 / 3;
  let nw = 0, nh = 0, x = 0, y = 0, w = 0;
  let useSaved = out.value !== '';
  let objectUrl = null;

  const maxW = () => Math.min(nw, nh * RATIO);

  function draw() {
    const h = w / RATIO;
    x = Math.max(0, Math.min(nw - w, x));
    y = Math.max(0, Math.min(nh - h, y));
    frame.style.left = (x / nw * 100) + '%';
    frame.style.top = (y / nh * 100) + '%';
    frame.style.width = (w / nw * 100) + '%';
    frame.style.height = (h / nh * 100) + '%';
    const fw = w / nw, fh = h / nh;
    preview.style.backgroundImage = `url("${img.src}")`;
    preview.style.backgroundSize = `${100 / fw}% ${100 / fh}%`;
    preview.style.backgroundPosition = `${fw >= 0.9999 ? 0 : (x / nw) / (1 - fw) * 100}% ${fh >= 0.9999 ? 0 : (y / nh) / (1 - fh) * 100}%`;
    out.value = [x / nw, y / nh, fw, fh].map((v) => v.toFixed(4)).join(',');
  }

  function centre() {
    w = maxW();
    x = (nw - w) / 2;
    y = (nh - w / RATIO) / 2;
  }

  function setup() {
    nw = img.naturalWidth;
    nh = img.naturalHeight;
    if (!nw || !nh) return;
    const saved = out.value.split(',').map(Number);
    if (useSaved && saved.length === 4 && saved.every((v) => v >= 0)) {
      x = saved[0] * nw;
      y = saved[1] * nh;
      w = Math.min(maxW(), saved[2] * nw);
    } else {
      centre();
    }
    useSaved = false;
    zoom.value = Math.round(w / maxW() * 100);
    frame.hidden = false;
    box.hidden = false;
    draw();
  }

  img.addEventListener('load', setup);
  if (img.getAttribute('src') && img.complete) setup();

  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) return;
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(file);
    out.value = '';
    useSaved = false;
    frame.hidden = true;
    img.src = objectUrl;
    box.hidden = false;
    if (label) label.textContent = 'Velg et annet bilde';
    if (remove) remove.checked = false;
  });
  img.addEventListener('error', () => {
    if (!objectUrl) return;
    box.hidden = true;
    out.value = '';
    alert('Nettleseren kan ikke vise dette bildet. Bruk JPG, PNG eller WebP.');
  });

  if (remove) {
    remove.addEventListener('change', () => { box.hidden = remove.checked; });
  }

  zoom.addEventListener('input', () => {
    const cx = x + w / 2, cy = y + w / RATIO / 2;
    w = maxW() * Number(zoom.value) / 100;
    x = cx - w / 2;
    y = cy - w / RATIO / 2;
    draw();
  });

  reset.addEventListener('click', () => {
    centre();
    zoom.value = 100;
    draw();
  });

  // Dra rammen, eller trykk et annet sted i bildet for å flytte den dit.
  let drag = null;
  const scale = () => nw / img.getBoundingClientRect().width;
  stage.addEventListener('pointerdown', (e) => {
    if (!nw) return;
    e.preventDefault();
    if (e.target !== frame) {
      const r = img.getBoundingClientRect();
      x = (e.clientX - r.left) * scale() - w / 2;
      y = (e.clientY - r.top) * scale() - w / RATIO / 2;
      draw();
    }
    drag = { px: e.clientX, py: e.clientY, x, y };
    stage.setPointerCapture(e.pointerId);
  });
  stage.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const s = scale();
    x = drag.x + (e.clientX - drag.px) * s;
    y = drag.y + (e.clientY - drag.py) * s;
    draw();
  });
  const stop = () => { drag = null; };
  stage.addEventListener('pointerup', stop);
  stage.addEventListener('pointercancel', stop);

  // Piltastene flytter rammen når den har fokus.
  frame.tabIndex = 0;
  frame.setAttribute('role', 'slider');
  frame.setAttribute('aria-label', 'Utsnitt. Bruk piltastene for å flytte.');
  frame.addEventListener('keydown', (e) => {
    const step = nw / 50;
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    if (!moves[e.key]) return;
    e.preventDefault();
    x += moves[e.key][0];
    y += moves[e.key][1];
    draw();
  });
})();
