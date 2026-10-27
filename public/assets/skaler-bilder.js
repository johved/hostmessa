// Skjemaer med data-shrink: bildene skaleres ned i nettleseren før de sendes,
// så flere store mobilbilder kan lastes opp på en gang uten å treffe størrelsesgrensen.
(() => {
  const MAX_SIDE = 1600;

  function shrink(file) {
    return new Promise((resolve) => {
      if (!file.type.startsWith('image/')) return resolve(file);
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
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  window.shrinkImage = shrink;

  // De nedskalerte bildene legges tilbake i skjemaet, og skjemaet sendes som vanlig.
  // Da viser nettleseren svaret fra serveren direkte, også om noe går galt.
  function canReplaceFiles() {
    try { return typeof DataTransfer === 'function' && !!new DataTransfer().items; } catch (err) { return false; }
  }

  document.querySelectorAll('form[data-shrink]').forEach((form) => {
    if (!canReplaceFiles()) return;
    let busy = false;
    form.addEventListener('submit', async (e) => {
      const inputs = Array.from(form.querySelectorAll('input[type=file]')).filter((i) => i.files.length);
      if (!inputs.length) return;
      e.preventDefault();
      if (busy) return;
      busy = true;
      const button = form.querySelector('button[type=submit]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Gjør klar bildene …';
      }
      let total = 0;
      for (const input of inputs) {
        const dt = new DataTransfer();
        for (const file of Array.from(input.files)) {
          const small = await shrink(file);
          total += small.size;
          dt.items.add(small);
        }
        input.files = dt.files;
      }
      if (total > 19 * 1024 * 1024) {
        alert('Bildene er for store til å sendes på en gang. Lagre med færre bilder, og legg til resten etterpå.');
        busy = false;
        if (button) {
          button.disabled = false;
          button.textContent = 'Prøv igjen';
        }
        return;
      }
      if (button) button.textContent = 'Lagrer …';
      form.submit();
    });
  });
})();
