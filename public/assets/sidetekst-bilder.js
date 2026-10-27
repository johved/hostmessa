// Laster opp et bilde til en sidetekst og setter inn bildelinjen der markøren står.
(() => {
  document.querySelectorAll('form.page-image').forEach((form) => {
    if (!window.fetch || !window.FormData) return;
    const textarea = document.getElementById(form.dataset.target);
    const status = form.querySelector('.page-image-status');
    const button = form.querySelector('button[type=submit]');
    let cursor = null;
    textarea.addEventListener('blur', () => { cursor = textarea.selectionStart; });

    form.addEventListener('submit', async (e) => {
      const input = form.querySelector('input[type=file]');
      if (!input.files.length) return;
      e.preventDefault();
      button.disabled = true;
      status.textContent = 'Laster opp …';
      const data = new FormData(form);
      if (window.shrinkImage) data.set('image', await window.shrinkImage(input.files[0]));
      try {
        const res = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const body = await res.text();
        let json;
        try {
          json = JSON.parse(body);
        } catch (err) {
          // Serveren svarte ikke med JSON, for eksempel en feilside. Vis starten av svaret.
          const text = body.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200);
          throw new Error(`Serveren svarte med feil ${res.status}${text ? ': ' + text : ''}`);
        }
        if (json.error) throw new Error(json.error);
        if (!json.markdown) throw new Error('Serveren kjenner ikke igjen opplastingen. Er alle filene i src/ og public/ lastet opp?');
        const at = cursor ?? textarea.value.length;
        const before = textarea.value.slice(0, at).replace(/\s*$/, '');
        const after = textarea.value.slice(at).replace(/^\s*/, '');
        textarea.value = (before ? before + '\n\n' : '') + json.markdown + (after ? '\n\n' + after : '\n');
        cursor = (before ? before.length + 2 : 0) + json.markdown.length;
        textarea.focus();
        textarea.setSelectionRange(cursor, cursor);
        form.reset();
        status.textContent = 'Bildet er satt inn. Husk å lagre teksten.';
      } catch (err) {
        status.textContent = err.message.startsWith('Serveren') || !(err instanceof TypeError) ? err.message : 'Bildet kunne ikke lastes opp. Sjekk nettforbindelsen og prøv igjen.';
      }
      button.disabled = false;
    });
  });
})();
