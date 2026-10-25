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

  document.querySelectorAll('form[data-shrink]').forEach((form) => {
    if (!window.fetch || !window.FormData) return;
    form.addEventListener('submit', async (e) => {
      const inputs = Array.from(form.querySelectorAll('input[type=file]')).filter((i) => i.files.length);
      if (!inputs.length) return;
      e.preventDefault();
      const button = form.querySelector('button[type=submit]');
      button.disabled = true;
      button.textContent = 'Lagrer …';
      const data = new FormData(form);
      for (const input of inputs) {
        data.delete(input.name);
        for (const file of input.files) data.append(input.name, await shrink(file));
      }
      try {
        const res = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        window.location.href = (await res.json()).redirect;
      } catch (err) {
        button.disabled = false;
        button.textContent = 'Prøv igjen';
        alert('Kunne ikke lagre. Sjekk nettforbindelsen og prøv igjen.');
      }
    });
  });
})();
