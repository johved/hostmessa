// Laster opp et bilde til en sidetekst så snart det er valgt, og setter inn bildelinjen
// etter avsnittet man sist klikket i (eller nederst).
(() => {
  document.querySelectorAll('form.page-image').forEach((form) => {
    if (!window.fetch || !window.FormData) return;
    // form.action peker på feltet som heter «action», ikke på adressen.
    const url = form.getAttribute('action');
    const textarea = document.getElementById(form.dataset.target);
    const status = form.querySelector('.page-image-status');
    const input = form.querySelector('input[type=file]');
    const pick = form.querySelector('.page-image-pick');
    let cursor = null;
    const remember = () => { cursor = textarea.selectionStart; };
    textarea.addEventListener('click', remember);
    textarea.addEventListener('keyup', remember);
    textarea.addEventListener('blur', remember);
    form.addEventListener('submit', (e) => e.preventDefault());

    input.addEventListener('change', async () => {
      if (!input.files.length) return;
      pick.classList.add('busy');
      status.textContent = 'Laster opp bildet …';
      const data = new FormData(form);
      try {
        if (window.shrinkImage) data.set('image', await window.shrinkImage(input.files[0]));
        const res = await fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
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
        // Bildet settes inn etter avsnittet man klikket i, så avsnittet ikke deles i to.
        let at = cursor ?? textarea.value.length;
        const blank = textarea.value.slice(at).search(/\n\s*\n/);
        at = blank === -1 ? textarea.value.length : at + blank;
        const before = textarea.value.slice(0, at).replace(/\s*$/, '');
        const after = textarea.value.slice(at).replace(/^\s*/, '');
        textarea.value = (before ? before + '\n\n' : '') + json.markdown + (after ? '\n\n' + after : '\n');
        const start = before ? before.length + 2 : 0;
        cursor = start + json.markdown.length;
        textarea.focus();
        textarea.setSelectionRange(start, cursor);
        form.reset();
        status.textContent = 'Bildet er satt inn (markert i teksten). Husk å lagre teksten.';
      } catch (err) {
        status.textContent = err instanceof TypeError ? 'Bildet kunne ikke lastes opp. Sjekk nettforbindelsen og prøv igjen.' : err.message;
        input.value = '';
      }
      pick.classList.remove('busy');
    });
  });
})();
