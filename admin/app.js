(() => {
  const LANGS = [
    { id: 'de', label: 'DE' },
    { id: 'en', label: 'EN' },
    { id: 'ru', label: 'RU' },
    { id: 'ar', label: 'AR' },
  ];
  const CATS = [
    { id: 'tag', label: 'Tag' },
    { id: 'abend', label: 'Abend' },
    { id: 'nacht', label: 'Nacht' },
    { id: 'gaeste', label: 'Gäste' },
  ];

  const initial = window.__INITIAL__;
  if (!initial) return;

  const csrf = initial.csrf;
  const fieldMeta = initial.fieldMeta || { sections: [], fields: [], images: [] };
  const state = {
    text: initial.content.text || { de: {}, en: {}, ru: {}, ar: {} },
    images: initial.content.images || {},
    gallery: Array.isArray(initial.content.gallery) ? initial.content.gallery : [],
  };

  let currentLang = 'de';
  let dirty = false;

  const el = (sel, root = document) => root.querySelector(sel);
  const els = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const assetUrl = (p) => (!p ? '' : (p.startsWith('/') || p.startsWith('http') ? p : '/' + p));

  function markDirty() {
    dirty = true;
    el('#save-status').textContent = 'Ungespeicherte Änderungen';
  }

  function setStatus(text, ok) {
    const s = el('#save-status');
    s.textContent = text;
    s.style.color = ok === undefined ? '' : ok ? 'oklch(78% .14 145)' : 'oklch(75% .16 25)';
  }

  // ---------- language tabs ----------
  function renderLangTabs() {
    const wrap = el('#lang-tabs');
    wrap.innerHTML = '';
    LANGS.forEach((l) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'lang-tab';
      btn.dataset.active = String(l.id === currentLang);
      btn.textContent = l.label;
      btn.addEventListener('click', () => {
        currentLang = l.id;
        renderLangTabs();
        renderTextSections();
      });
      wrap.appendChild(btn);
    });
  }

  // ---------- side nav ----------
  function renderSideNav() {
    const nav = el('#side-nav');
    const items = [
      { id: 'media-images', label: 'Bilder' },
      { id: 'media-gallery', label: 'Galerie' },
      ...fieldMeta.sections.map((s) => ({ id: 'section-' + s.id, label: s.label })),
    ];
    nav.innerHTML = items
      .map((i) => `<a href="#${i.id}" class="side-link">${i.label}</a>`)
      .join('');
  }

  // ---------- text fields ----------
  function fieldRow(field) {
    const value = (state.text[currentLang] && state.text[currentLang][field.key]) || '';
    const tag = field.multiline ? 'textarea' : 'input';
    const attrs = field.multiline ? '' : 'type="text"';
    const htmlBadge = field.html
      ? '<span class="ml-2 text-[10px] px-1.5 py-0.5 rounded border border-white/20 text-white/50 align-middle">HTML</span>'
      : '';
    return `
      <div class="field-row mb-4" data-search="${(field.label + ' ' + field.key + ' ' + value).toLowerCase().replace(/"/g, '&quot;')}">
        <label class="block text-xs text-white/60 mb-1.5">${field.label}${htmlBadge}
          <span class="text-white/25 font-mono text-[10px] ml-1">${field.key}</span>
        </label>
        <${tag} ${attrs} class="${field.multiline ? 'field-textarea' : 'field-input'}" data-key="${field.key}">${field.multiline ? escapeHtml(value) : ''}</${tag}>
      </div>`;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
  }

  function renderTextSections() {
    const main = el('#sections');
    // preserve the media sections' DOM (they don't depend on language); only rebuild text sections
    els('.text-section', main).forEach((n) => n.remove());

    fieldMeta.sections.forEach((sec) => {
      const fields = fieldMeta.fields.filter((f) => f.section === sec.id);
      if (!fields.length) return;
      const section = document.createElement('section');
      section.id = 'section-' + sec.id;
      section.className = 'text-section surface p-6';
      section.innerHTML = `<h2 class="text-lg font-semibold mb-5">${sec.label}</h2><div></div>`;
      const body = section.querySelector('div');
      body.innerHTML = fields.map(fieldRow).join('');
      main.appendChild(section);
    });

    // bind inputs (non-textarea value must be set via property, not innerHTML)
    els('#sections .text-section [data-key]').forEach((elm) => {
      if (elm.tagName === 'INPUT') {
        elm.value = (state.text[currentLang] && state.text[currentLang][elm.dataset.key]) || '';
      }
      elm.addEventListener('input', () => {
        if (!state.text[currentLang]) state.text[currentLang] = {};
        state.text[currentLang][elm.dataset.key] = elm.value;
        markDirty();
      });
    });

    applySearchFilter();
  }

  // ---------- block images ----------
  function renderImagesSection() {
    const wrap = document.createElement('section');
    wrap.id = 'section-media-images';
    wrap.className = 'surface p-6';
    wrap.innerHTML = `
      <h2 class="text-lg font-semibold mb-5">Bilder</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" id="images-grid"></div>`;
    const grid = wrap.querySelector('#images-grid');

    (fieldMeta.images || []).forEach((img) => {
      const card = document.createElement('div');
      card.className = 'surface-2 p-3';
      const src = assetUrl(state.images[img.key]);
      card.innerHTML = `
        <img src="${src}" alt="" class="w-full h-36 object-cover rounded-lg mb-3 bg-black/30">
        <p class="text-xs text-white/70 mb-2">${img.label}</p>
        <label class="btn btn-outline w-full text-xs cursor-pointer">
          Foto ersetzen
          <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden">
        </label>
        <p class="text-[11px] text-white/40 mt-2 hidden upload-status"></p>`;
      const fileInput = card.querySelector('input[type=file]');
      const statusEl = card.querySelector('.upload-status');
      const imgEl = card.querySelector('img');
      fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];
        if (!file) return;
        statusEl.textContent = 'Wird hochgeladen …';
        statusEl.classList.remove('hidden');
        try {
          const url = await uploadFile(file, img.key);
          state.images[img.key] = url;
          imgEl.src = assetUrl(url);
          statusEl.textContent = 'Hochgeladen — bitte „Speichern“ klicken.';
          markDirty();
        } catch (e) {
          statusEl.textContent = 'Fehler beim Hochladen: ' + e.message;
        }
      });
      grid.appendChild(card);
    });

    return wrap;
  }

  // ---------- gallery ----------
  function galleryCard(item, index) {
    const card = document.createElement('div');
    card.className = 'surface-2 p-3 relative';
    card.innerHTML = `
      <button type="button" class="gallery-delete absolute top-2 right-2 z-10 w-7 h-7 rounded-full flex items-center justify-center text-sm"
        style="background: oklch(14% .012 240 / .8); color: oklch(90% .05 25);">×</button>
      <img src="${assetUrl(item.src)}" alt="" class="w-full h-32 object-cover rounded-lg mb-2 bg-black/30">
      <input type="text" class="field-input text-xs mb-2 gallery-alt" placeholder="Bildbeschreibung (Alt-Text)" value="${escapeHtml(item.alt || '')}">
      <div class="flex flex-wrap gap-2">
        ${CATS.map(
          (c) => `<label class="text-[11px] flex items-center gap-1 text-white/60">
            <input type="checkbox" class="gallery-cat" value="${c.id}" ${item.cat && item.cat.includes(c.id) ? 'checked' : ''}> ${c.label}
          </label>`
        ).join('')}
      </div>`;

    card.querySelector('.gallery-delete').addEventListener('click', () => {
      state.gallery.splice(index, 1);
      markDirty();
      renderGallerySection();
    });
    card.querySelector('.gallery-alt').addEventListener('input', (e) => {
      item.alt = e.target.value;
      markDirty();
    });
    els('.gallery-cat', card).forEach((cb) => {
      cb.addEventListener('change', () => {
        item.cat = els('.gallery-cat', card).filter((x) => x.checked).map((x) => x.value);
        markDirty();
      });
    });
    return card;
  }

  function renderGallerySection() {
    let wrap = el('#section-media-gallery');
    if (!wrap) {
      wrap = document.createElement('section');
      wrap.id = 'section-media-gallery';
      wrap.className = 'surface p-6';
    }
    wrap.innerHTML = `
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-semibold">Galerie <span class="text-white/40 text-sm font-normal">(${state.gallery.length} Fotos)</span></h2>
        <label class="btn btn-gold text-xs cursor-pointer">
          + Foto hinzufügen
          <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" id="gallery-add-input">
        </label>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4" id="gallery-grid-admin"></div>`;

    const grid = wrap.querySelector('#gallery-grid-admin');
    state.gallery.forEach((item, i) => grid.appendChild(galleryCard(item, i)));

    wrap.querySelector('#gallery-add-input').addEventListener('change', async (e) => {
      const files = Array.from(e.target.files || []);
      e.target.value = '';
      for (const file of files) {
        try {
          const url = await uploadFile(file, 'gallery');
          const dims = await readImageDimensions(assetUrl(url));
          state.gallery.push({ src: url, w: dims.w, h: dims.h, cat: ['tag'], alt: '' });
          markDirty();
          renderGallerySection();
        } catch (err) {
          alert('Fehler beim Hochladen von ' + file.name + ': ' + err.message);
        }
      }
    });

    return wrap;
  }

  function readImageDimensions(url) {
    return new Promise((resolve) => {
      const im = new Image();
      im.onload = () => resolve({ w: im.naturalWidth || 1200, h: im.naturalHeight || 900 });
      im.onerror = () => resolve({ w: 1200, h: 900 });
      im.src = url;
    });
  }

  function uploadFile(file, slot) {
    return new Promise((resolve, reject) => {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('slot', slot);
      fetch('api.php?action=upload', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf },
        body: fd,
      })
        .then(async (res) => {
          const data = await res.json().catch(() => ({}));
          if (!res.ok || !data.ok) return reject(new Error(data.error || 'upload_failed'));
          resolve(data.url);
        })
        .catch(reject);
    });
  }

  // ---------- search ----------
  function applySearchFilter() {
    const q = (el('#search-input')?.value || '').trim().toLowerCase();
    els('#sections .text-section').forEach((section) => {
      let visibleCount = 0;
      els('.field-row', section).forEach((row) => {
        const match = !q || row.dataset.search.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
      });
      section.style.display = visibleCount ? '' : 'none';
    });
  }

  // ---------- save ----------
  async function saveAll() {
    const btn = el('#save-btn');
    btn.disabled = true;
    setStatus('Speichere …');
    try {
      const res = await fetch('api.php?action=save_content', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(state),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || 'save_failed');
      dirty = false;
      setStatus('Gespeichert ✓', true);
      setTimeout(() => { if (!dirty) setStatus(''); }, 2500);
    } catch (e) {
      setStatus('Fehler: ' + e.message, false);
    } finally {
      btn.disabled = false;
    }
  }

  // ---------- init ----------
  function init() {
    renderLangTabs();
    renderSideNav();

    const main = el('#sections');
    main.innerHTML = '';
    const searchWrap = document.createElement('div');
    searchWrap.innerHTML = `<input id="search-input" type="text" placeholder="Text durchsuchen …" class="field-input mb-2">`;
    main.appendChild(searchWrap.firstElementChild);
    el('#search-input').addEventListener('input', applySearchFilter);

    main.appendChild(renderImagesSection());
    main.appendChild(renderGallerySection());
    renderTextSections();

    el('#save-btn').addEventListener('click', saveAll);

    el('#logout-btn').addEventListener('click', async () => {
      if (dirty && !confirm('Es gibt ungespeicherte Änderungen. Trotzdem abmelden?')) return;
      await fetch('logout.php', { method: 'POST' });
      location.reload();
    });

    const dlg = el('#password-dialog');
    el('#password-btn').addEventListener('click', () => {
      el('#pw-error').classList.add('hidden');
      el('#password-form').reset();
      dlg.showModal();
    });
    el('#pw-cancel').addEventListener('click', () => dlg.close());
    el('#password-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const current = el('#pw-current').value;
      const next = el('#pw-new').value;
      const errEl = el('#pw-error');
      errEl.classList.add('hidden');
      const res = await fetch('api.php?action=change_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ current, new: next }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        errEl.textContent = data.error === 'wrong_current_password' ? 'Aktuelles Passwort ist falsch.' : 'Fehler: ' + (data.error || '');
        errEl.classList.remove('hidden');
        return;
      }
      dlg.close();
      alert('Passwort geändert.');
    });

    window.addEventListener('beforeunload', (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
    });
  }

  init();
})();
