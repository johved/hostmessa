// Laster opp mange galleribilder: skalerer dem ned i nettleseren og sender dem i små porsjoner,
// så store mobilbilder går raskt og ingen grenser for opplastingsstørrelse treffes.
(() => {
  const form = document.getElementById('gallery-form');
  if (!form || !window.fetch || !window.FormData) return;
  const input = document.getElementById('g-images');
  const status = document.getElementById('g-status');
  const submit = document.getElementById('g-submit');
  const csrf = form.querySelector('input[name="csrf"]').value;
  const MAX_SIDE = 1600;
  const BATCH = 6;

  input.addEventListener('change', () => {
    const n = input.files.length;
    status.textContent = n ? `${n} ${n === 1 ? 'bilde' : 'bilder'} valgt` : 'Du kan velge mange på en gang. JPG, PNG eller WebP.';
  });

  function shrink(file) {
    return new Promise((resolve) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        const scale = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        canvas.toBlob((blob) => resolve(blob ? new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.85);
      };
      // Bilder nettleseren ikke kan lese, sendes som de er, så sier serveren fra.
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  function finish(saved, errors) {
    const done = document.createElement('form');
    done.method = 'post';
    done.action = form.action;
    const fields = [['csrf', csrf], ['action', 'gallery_done'], ['saved', String(saved)], ...errors.map((e) => ['errors[]', e])];
    for (const [name, value] of fields) {
      const el = document.createElement('input');
      el.type = 'hidden';
      el.name = name;
      el.value = value;
      done.appendChild(el);
    }
    document.body.appendChild(done);
    done.submit();
  }

  form.addEventListener('submit', async (e) => {
    const files = Array.from(input.files);
    if (!files.length) return;
    e.preventDefault();
    submit.disabled = true;
    let saved = 0;
    const errors = [];
    for (let i = 0; i < files.length; i += BATCH) {
      const batch = files.slice(i, i + BATCH);
      status.textContent = `Laster opp ${Math.min(i + batch.length, files.length)} av ${files.length} …`;
      const data = new FormData();
      data.append('csrf', csrf);
      data.append('action', 'upload_gallery');
      for (const file of batch) data.append('images[]', await shrink(file));
      try {
        const res = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const json = await res.json();
        saved += json.saved;
        errors.push(...json.errors);
      } catch (err) {
        errors.push(`${batch.length} bilder kunne ikke lastes opp. Sjekk nettforbindelsen og prøv igjen.`);
      }
    }
    finish(saved, errors);
  });
})();
