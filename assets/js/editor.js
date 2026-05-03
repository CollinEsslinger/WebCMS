// ===========================================================================
// EDITOR.JS – Visual Block Editor Logic
// ===========================================================================

(function() {
  'use strict';

  let blocks = [];
  let selectedBlockIndex = null;
  let BLOCK_TYPES = {};
  let inlineDrag = null;

  const canvas = document.getElementById('visualEditor');
  const blocksJsonInput = document.getElementById('blocksJson');
  const pageForm = document.getElementById('pageForm');
  const mediaUploadForm = document.getElementById('mediaUploadForm');
  const mediaFileInput = document.getElementById('mediaFile');
  const editorMediaGrid = document.getElementById('editorMediaGrid');
  let mediaLibrary = Array.isArray(window.CMS_MEDIA_LIBRARY) ? window.CMS_MEDIA_LIBRARY.slice() : [];

  if (!canvas || !blocksJsonInput || !pageForm) return;

  const initialData = canvas.dataset.initial;
  const blockDefaults = canvas.dataset.blockDefaults;

  if (initialData) {
    try { blocks = JSON.parse(initialData); } catch (e) { blocks = []; }
  }
  if (blockDefaults) {
    try { BLOCK_TYPES = JSON.parse(blockDefaults); } catch (e) {}
  }

  // Nested value writer using dot-path ("items.0.title")
  function setFieldValue(settings, path, value) {
    const parts = path.split('.');
    let obj = settings;
    for (let i = 0; i < parts.length - 1; i++) {
      const key = isNaN(parts[i]) ? parts[i] : parseInt(parts[i]);
      if (obj[key] == null || typeof obj[key] !== 'object') obj[key] = {};
      obj = obj[key];
    }
    const last = parts[parts.length - 1];
    obj[isNaN(last) ? last : parseInt(last)] = value;
  }

  function getFieldValue(settings, path) {
    return path.split('.').reduce((obj, part) => {
      if (obj == null) return undefined;
      const key = isNaN(part) ? part : parseInt(part);
      return obj[key];
    }, settings);
  }

  function flushActiveField() {
    const active = document.activeElement;
    if (!active || !active.dataset.field) return;
    const blockEl = active.closest('.cms-block');
    if (!blockEl) return;
    const idx = parseInt(blockEl.dataset.index);
    const val = editableFieldValue(active);
    setFieldValue(blocks[idx].settings, active.dataset.field, val);
  }

  function editableFieldValue(el) {
    const text = el.innerText.replace(/\r\n?/g, '\n');
    if (el.dataset.multiline) return text.replace(/\n$/g, '');
    return text.trim().replace(/\n/g, ' ');
  }

  function cloneData(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function mediaKind(item) {
    if (!item || !item.mime) return 'file';
    if (item.mime.startsWith('image/')) return 'image';
    if (item.mime.startsWith('video/')) return 'video';
    return 'file';
  }

  function mediaMatches(item, types) {
    if (!types || types === 'any') return true;
    const allowed = String(types).split(',').map(t => t.trim()).filter(Boolean);
    return allowed.includes(mediaKind(item));
  }

  function addMediaItem(item) {
    if (!item || !item.url) return;
    mediaLibrary = [item].concat(mediaLibrary.filter(existing => existing.id !== item.id));
    renderEditorMediaGrid();
  }

  function renderEditorMediaGrid() {
    if (!editorMediaGrid) return;
    editorMediaGrid.innerHTML = mediaLibrary.map(item => {
      const kind = mediaKind(item);
      const thumb = kind === 'image'
        ? `<img src="${ce(item.url)}" alt="${ce(item.name || '')}">`
        : `<span>${kind === 'video' ? 'Video' : 'Datei'}</span>`;
      return `<button type="button" class="media-thumb" data-media-url="${ce(item.url)}" data-media-mime="${ce(item.mime || '')}" title="${ce(item.name || '')}">
        ${thumb}<span class="media-thumb-name">${ce(item.name || '')}</span>
      </button>`;
    }).join('');
  }

  function mediaPickButton(types, targetPath, label, itemIndex = '') {
    const idx = itemIndex === '' ? '' : ` data-item-index="${itemIndex}"`;
    return `<button type="button" class="inline-mini-btn media-pick-btn" data-inline-action="select-media" data-media-types="${types}" data-target-path="${targetPath}"${idx}>${ce(label || 'Auswaehlen')}</button>`;
  }

  function ensureArray(settings, key, fallback) {
    const parts = key.split('.');
    let obj = settings;
    for (let i = 0; i < parts.length - 1; i++) {
      const part = isNaN(parts[i]) ? parts[i] : parseInt(parts[i]);
      if (obj[part] == null || typeof obj[part] !== 'object') obj[part] = {};
      obj = obj[part];
    }
    const last = parts[parts.length - 1];
    const lastKey = isNaN(last) ? last : parseInt(last);
    if (!Array.isArray(obj[lastKey])) obj[lastKey] = cloneData(fallback);
    return obj[lastKey];
  }

  function statColorValue(st) {
    if (['red', 'yellow', 'green'].includes(st.color)) return st.color;
    return st.up === false ? 'red' : 'green';
  }

  function statArrowValue(st) {
    if (['up', 'right', 'down'].includes(st.arrow)) return st.arrow;
    if (typeof st.delta === 'string') {
      if (st.delta.includes('↓')) return 'down';
      if (st.delta.includes('→')) return 'right';
    }
    return st.up === false ? 'down' : 'up';
  }

  function statDeltaText(st) {
    return String(st.delta ?? '').replace(/^[↑↓→]\s*/, '').trim();
  }

  function statColorCss(color) {
    return { red: '#ef4444', yellow: '#f59e0b', green: '#22c55e' }[color] || '#22c55e';
  }

  function statArrowSymbol(arrow) {
    return { up: '↑', right: '→', down: '↓' }[arrow] || '↑';
  }

  function inlineAddButton(kind, path, label) {
    return `<button type="button" class="inline-add-row" data-inline-action="add-item" data-list-kind="${kind}" data-list-path="${path}">+ ${ce(label || 'Hinzufügen')}</button>`;
  }

  function inlineItemControls(path, index, canRemove) {
    const disabled = canRemove ? '' : ' disabled aria-disabled="true"';
    return `<div class="inline-list-controls" contenteditable="false">
      <button type="button" class="inline-mini-btn inline-drag-handle" draggable="true" data-list-path="${path}" data-item-index="${index}" title="Verschieben">↕</button>
      <button type="button" class="inline-mini-btn inline-mini-danger" data-inline-action="remove-item" data-list-path="${path}" data-item-index="${index}" title="Entfernen"${disabled}>−</button>
    </div>`;
  }

  function inlineCompactItemControls(path, index, canRemove) {
    const disabled = canRemove ? '' : ' aria-disabled="true"';
    return `<span class="inline-list-controls inline-list-controls-compact" contenteditable="false">
      <span class="inline-mini-btn inline-drag-handle" draggable="true" data-list-path="${path}" data-item-index="${index}" title="Verschieben">↕</span>
      <span class="inline-mini-btn inline-mini-danger" data-inline-action="remove-item" data-list-path="${path}" data-item-index="${index}" title="Entfernen"${disabled}>−</span>
    </span>`;
  }

  function renderStatControls(st, i) {
    const color = statColorValue(st);
    const arrow = statArrowValue(st);
    return `<div class="inline-stat-controls" contenteditable="false">
      <div class="inline-segment" aria-label="Farbe">
        ${['red','yellow','green'].map(c => `<button type="button" class="stat-color-dot ${color===c?'active':''}" data-inline-action="set-stat-color" data-item-index="${i}" data-value="${c}" title="${c}"></button>`).join('')}
      </div>
      <div class="inline-segment" aria-label="Pfeil">
        ${['up','right','down'].map(a => `<button type="button" class="stat-arrow-btn ${arrow===a?'active':''}" data-inline-action="set-stat-arrow" data-item-index="${i}" data-value="${a}" title="${a}">${statArrowSymbol(a)}</button>`).join('')}
      </div>
    </div>`;
  }

  function renderStars(value, i) {
    const stars = Math.max(0, Math.min(5, parseInt(value ?? 5) || 0));
    return `<div class="inline-stars" contenteditable="false" data-star-index="${i}" data-stars="${stars}" aria-label="${stars} von 5 Sterne">
      <button type="button" class="star-zero ${stars===0?'active':''}" data-inline-action="set-stars" data-item-index="${i}" data-value="0" title="0 Sterne">0</button>
      ${[1,2,3,4,5].map(n => `<button type="button" class="star-btn ${n <= stars ? 'active' : ''}" data-inline-action="set-stars" data-item-index="${i}" data-value="${n}" title="${n} Sterne">★</button>`).join('')}
    </div>`;
  }

  function featureText(feature) {
    return typeof feature === 'string' ? feature : String(feature?.text ?? '');
  }

  function featureIcon(feature) {
    if (typeof feature === 'string') return 'check';
    return feature?.icon === 'x' ? 'x' : 'check';
  }

  function featureIconSymbol(icon) {
    return icon === 'x' ? '×' : '✓';
  }

  function newInlineItem(kind) {
    const factories = {
      card: () => ({ icon: '✨', title: 'Neue Karte', text: 'Beschreibung...', url: '#', urlLabel: 'Mehr' }),
      stat: () => ({ value: '0', label: 'Label', delta: '', color: 'green', arrow: 'up', up: true }),
      team: () => ({ initials: 'NN', name: 'Neues Mitglied', role: 'Position', color: '#6366f1' }),
      plan: () => ({ name: 'Neuer Plan', price: '0€', period: '/Monat', features: [{ text: 'Neues Feature', icon: 'check' }], featured: false, badgeLabel: 'Beliebteste Wahl', buttonLabel: 'Wählen', buttonUrl: '#' }),
      'pricing-feature': () => ({ text: 'Neues Feature', icon: 'check' }),
      timeline: () => ({ date: '2026', title: 'Neuer Punkt', body: 'Beschreibung...' }),
      testimonial: () => ({ stars: 5, text: 'Neues Zitat...', author: 'Autor', initials: '', color: '#6366f1' }),
      accordion: () => ({ title: 'Neue Frage?', body: 'Antwort...' }),
      tab: () => ({ title: 'Neuer Tab', content: 'Tab-Inhalt...' }),
      'form-field': () => ({ type: 'text', label: 'Neues Feld', placeholder: '', required: false }),
      'gallery-image': () => ({ url: '', alt: 'Neues Bild' })
    };
    return factories[kind] ? factories[kind]() : {};
  }

  // Dirty-state tracking
  let isDirty = false;
  function markDirty() { isDirty = true; }

  window.addEventListener('beforeunload', (e) => {
    if (isDirty) { e.preventDefault(); e.returnValue = ''; }
  });

  function showUnsavedDialog(destination) {
    const overlay = document.createElement('div');
    overlay.className = 'unsaved-overlay';
    overlay.innerHTML = `
      <div class="unsaved-dialog">
        <h3>Ungespeicherte Änderungen</h3>
        <p>Es gibt ungespeicherte Änderungen auf dieser Seite. Möchtest du sie vor dem Verlassen speichern?</p>
        <div class="unsaved-dialog-actions">
          <button class="btn primary" id="unsavedSave">Speichern</button>
          <button class="btn secondary" id="unsavedDiscard">Nicht speichern</button>
          <button class="btn btn-ghost" id="unsavedCancel">Abbrechen</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    overlay.querySelector('#unsavedSave').addEventListener('click', async () => {
      const active = document.activeElement;
      if (active && active.dataset.field) {
        const blockEl = active.closest('.cms-block');
        if (blockEl) {
          const idx = parseInt(blockEl.dataset.index);
          const val = editableFieldValue(active);
          const previousValue = getFieldValue(blocks[idx].settings, active.dataset.field);
          setFieldValue(blocks[idx].settings, active.dataset.field, val);
          syncInitialsForField(idx, active.dataset.field, val, blockEl, previousValue);
        }
      }
      blocksJsonInput.value = JSON.stringify(blocks);
      try { await fetch(pageForm.action, { method: 'POST', body: new FormData(pageForm) }); } catch (_) {}
      isDirty = false;
      window.location.href = destination;
    });

    overlay.querySelector('#unsavedDiscard').addEventListener('click', () => {
      isDirty = false;
      window.location.href = destination;
    });

    overlay.querySelector('#unsavedCancel').addEventListener('click', () => overlay.remove());
  }

  document.addEventListener('click', (e) => {
    if (!isDirty) return;
    const link = e.target.closest('a[href]');
    if (!link) return;
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
    e.preventDefault();
    showUnsavedDialog(href);
  }, true);

  // Inline button/link renderer for preview canvas
  function renderInlineBtn(label, labelField, url, urlField, btnClass, extraStyle) {
    const styleAttr = extraStyle ? ` style="${ce(extraStyle)}"` : '';
    const cls = btnClass ? ` ${btnClass}` : '';
    return `<div class="inline-btn-wrap">
      <button class="btn${cls}"${styleAttr} onclick="return false">
        <span contenteditable="true" data-field="${labelField}">${ce(label)}</span>
      </button>
      <div class="inline-btn-url">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        <span contenteditable="true" data-field="${urlField}" class="inline-url-val">${ce(url || '#')}</span>
      </div>
    </div>`;
  }

  // ---------------------------------------------------------------------------
  // Render Canvas
  // ---------------------------------------------------------------------------
  function renderCanvas() {
    if (blocks.length === 0) {
      canvas.innerHTML = `
        <div class="canvas-empty">
          <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
          </svg>
          <p>Keine Blöcke vorhanden. Füge Blöcke über die Seitenleiste hinzu.</p>
        </div>`;
      return;
    }
    canvas.innerHTML = blocks.map((block, i) => renderBlock(block, i)).join('');
    attachBlockHandlers();
  }

  // ---------------------------------------------------------------------------
  // Render Single Block  (field names match core/functions.php + render.php)
  // ---------------------------------------------------------------------------
  function ce(str) { // cheap HTML escape for preview strings
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function initialsFromName(name) {
    const parts = String(name ?? '')
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    if (parts.length === 0) return '';
    return parts.map(part => part[0]).join('').toLocaleUpperCase('de-DE');
  }

  function legacyInitialsFromName(name) {
    const parts = String(name ?? '')
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    if (parts.length === 0) return '';
    const initials = parts.length === 1
      ? parts[0].slice(0, 2)
      : parts[0][0] + parts[parts.length - 1][0];
    return initials.toLocaleUpperCase('de-DE');
  }

  function shouldAutoUpdateInitials(person, previousName) {
    if (!person) return false;
    if (person.initialsManual !== true && person.initialsManual !== 'true') return true;
    const current = String(person.initials ?? '').trim().toLocaleUpperCase('de-DE');
    return current === initialsFromName(previousName) || current === legacyInitialsFromName(previousName);
  }

  function displayInitials(person) {
    return person.initials || initialsFromName(person.name) || '?';
  }

  function syncInitialsForField(index, field, value, blockEl, previousValue = null) {
    const block = blocks[index];
    if (!block) return;

    const teamName = field.match(/^members\.(\d+)\.name$/);
    if (block.type === 'team' && teamName) {
      const memberIndex = parseInt(teamName[1]);
      const member = block.settings.members?.[memberIndex];
      if (shouldAutoUpdateInitials(member, previousValue ?? member?.name ?? value)) {
        member.initials = initialsFromName(value);
        member.initialsManual = false;
        const card = blockEl?.querySelectorAll('.team-person-card')[memberIndex];
        const avatar = card?.querySelector('.team-avatar');
        if (avatar) avatar.textContent = displayInitials(member);
      }
    }

    const teamInitials = field.match(/^members\.(\d+)\.initials$/);
    if (block.type === 'team' && teamInitials) {
      const member = block.settings.members?.[parseInt(teamInitials[1])];
      if (member) member.initialsManual = true;
    }

    const testimonialAuthor = field.match(/^items\.(\d+)\.author$/);
    if (block.type === 'testimonials' && testimonialAuthor) {
      const itemIndex = parseInt(testimonialAuthor[1]);
      const item = block.settings.items?.[itemIndex];
      if (shouldAutoUpdateInitials(item, previousValue ?? item?.author ?? value)) {
        item.initials = initialsFromName(value);
        item.initialsManual = false;
        const card = blockEl?.querySelectorAll('.testimonial-card')[itemIndex];
        const avatar = card?.querySelector('.avatar[data-field]');
        if (avatar) avatar.textContent = item.initials || '?';
      }
    }

    const testimonialInitials = field.match(/^items\.(\d+)\.initials$/);
    if (block.type === 'testimonials' && testimonialInitials) {
      const item = block.settings.items?.[parseInt(testimonialInitials[1])];
      if (item) item.initialsManual = true;
    }
  }

  function renderSectionLabel(s) {
    const value = s.label ?? '';
    return `<div class="inline-section-label" contenteditable="true" data-field="label" data-empty-placeholder="Label">${ce(value)}</div>`;
  }

  function openMediaPicker({ types = 'image', title = 'Medium auswaehlen', onSelect }) {
    document.querySelector('.media-picker-overlay')?.remove();
    const items = mediaLibrary.filter(item => mediaMatches(item, types));
    const overlay = document.createElement('div');
    overlay.className = 'block-settings-overlay media-picker-overlay';
    overlay.innerHTML = `<div class="block-settings-modal media-picker-modal">
      <div class="modal-head">
        <h3>${ce(title)}</h3>
        <button type="button" class="modal-close" data-media-close aria-label="Schliessen">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="media-picker-grid">
          ${items.length ? items.map(item => {
            const kind = mediaKind(item);
            const thumb = kind === 'image'
              ? `<img src="${ce(item.url)}" alt="${ce(item.name || '')}">`
              : `<span>${kind === 'video' ? 'Video' : 'Datei'}</span>`;
            return `<button type="button" class="media-picker-item" data-url="${ce(item.url)}" data-name="${ce(item.name || '')}" data-mime="${ce(item.mime || '')}">
              <span class="media-picker-thumb">${thumb}</span>
              <span class="media-picker-name">${ce(item.name || '')}</span>
            </button>`;
          }).join('') : '<p class="media-picker-empty">Keine passende Datei vorhanden.</p>'}
        </div>
      </div>
    </div>`;
    const close = () => overlay.remove();
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay || event.target.closest('[data-media-close]')) close();
      const itemButton = event.target.closest('.media-picker-item');
      if (!itemButton) return;
      onSelect?.({
        url: itemButton.dataset.url || '',
        name: itemButton.dataset.name || '',
        mime: itemButton.dataset.mime || ''
      });
      close();
    });
    document.body.appendChild(overlay);
  }

  function renderBlock(block, index) {
    const type = block.type;
    const s = block.settings || {};
    let preview = '';

    switch (type) {

      // ── hero ──────────────────────────────────────────────────────────────
      case 'hero':
        preview = `<div class="preview-hero">
          ${s.eyebrow ? `<div class="hero-tag" contenteditable="true" data-field="eyebrow">${ce(s.eyebrow)}</div>` : ''}
          <h1 contenteditable="true" data-field="heading" style="font-size:2.5rem;font-weight:700;margin-bottom:1rem">${ce(s.heading || 'Hero Titel')}</h1>
          <p contenteditable="true" data-field="text" data-multiline="true" style="font-size:1.125rem;margin-bottom:2rem;opacity:.9">${ce(s.text || 'Untertitel')}</p>
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;align-items:flex-start">
            ${s.primaryLabel ? renderInlineBtn(s.primaryLabel, 'primaryLabel', s.primaryUrl, 'primaryUrl', 'btn-primary btn-lg') : ''}
            ${s.secondaryLabel ? renderInlineBtn(s.secondaryLabel, 'secondaryLabel', s.secondaryUrl, 'secondaryUrl', 'btn-secondary btn-lg') : ''}
          </div>
        </div>`;
        break;

      // ── text ──────────────────────────────────────────────────────────────
      case 'text':
        preview = `<div class="preview-text">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="font-size:1.875rem;font-weight:700;margin-bottom:1rem">${ce(s.heading)}</h2>` : ''}
          <div contenteditable="true" data-field="text" data-multiline="true" style="color:var(--text-secondary);line-height:1.7">${ce(s.text || 'Text-Inhalt...')}</div>
        </div>`;
        break;

      // ── cards ─────────────────────────────────────────────────────────────
      case 'cards': {
        const cards = ensureArray(s, 'items', [{icon:'✨',title:'Karte 1',text:'Beschreibung...',url:'#',urlLabel:'Mehr'}]);
        preview = `<div class="preview-cards">
          ${renderSectionLabel(s)}
          <h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading || 'Karten')}</h2>
          ${inlineAddButton('card', 'items', 'Karte hinzufügen')}
          <div class="cards-row">
            ${cards.map((c, i) => `<div class="mini-card inline-edit-item">
              ${inlineItemControls('items', i, cards.length > 1)}
              <div contenteditable="true" data-field="items.${i}.icon" class="inline-card-icon" data-empty-placeholder="Emoji">${ce(c.icon || '')}</div>
              <h3 contenteditable="true" data-field="items.${i}.title" style="font-size:1.125rem;font-weight:600;margin-bottom:.5rem">${ce(c.title || 'Titel')}</h3>
              <p contenteditable="true" data-field="items.${i}.text" data-multiline="true" style="font-size:.875rem;color:var(--text-secondary)">${ce(c.text || 'Beschreibung...')}</p>
              ${c.urlLabel ? `<div class="card-footer">${renderInlineBtn(c.urlLabel, `items.${i}.urlLabel`, c.url || '#', `items.${i}.url`, 'btn-ghost btn-sm')}</div>` : ''}
            </div>`).join('')}
          </div>
        </div>`;
        break;
      }

      // ── stats ─────────────────────────────────────────────────────────────
      case 'stats': {
        const stats = ensureArray(s, 'items', [{value:'1000+',label:'Kunden',delta:'24%',color:'green',arrow:'up'}]);
        preview = `<div class="preview-stats">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="font-size:1.875rem;font-weight:700;margin-bottom:1.5rem">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('stat', 'items', 'Statistik hinzufügen')}
          <div class="stats-row">
            ${stats.map((st, i) => `<div class="stat-item inline-edit-item">
              ${inlineItemControls('items', i, stats.length > 1)}
              <div contenteditable="true" data-field="items.${i}.value" class="num">${ce(st.value || '0')}</div>
              <div contenteditable="true" data-field="items.${i}.label" class="lbl">${ce(st.label || 'Label')}</div>
              <div class="inline-stat-delta" style="color:${statColorCss(statColorValue(st))}">
                <span aria-hidden="true">${statArrowSymbol(statArrowValue(st))}</span>
                <span contenteditable="true" data-field="items.${i}.delta" data-empty-placeholder="Wert">${ce(statDeltaText(st))}</span>
              </div>
              ${renderStatControls(st, i)}
            </div>`).join('')}
          </div>
        </div>`;
        break;
      }

      // ── team ──────────────────────────────────────────────────────────────
      case 'team': {
        const members = ensureArray(s, 'members', [{initials:'MM',name:'Max Mustermann',role:'CEO',color:'#6366f1'}]);
        preview = `<div class="preview-team">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('team', 'members', 'Mitglied hinzufügen')}
          <div class="team-row">
            ${members.map((m, i) => `<div class="contact-card team-person-card inline-edit-item">
              ${inlineItemControls('members', i, members.length > 1)}
              <div class="avatar avatar-lg team-avatar" contenteditable="true" data-field="members.${i}.initials" style="background:${ce(m.color||'var(--accent)')}">${ce(displayInitials(m))}</div>
              <div class="contact-info">
                <div class="contact-name" contenteditable="true" data-field="members.${i}.name">${ce(m.name || 'Name')}</div>
                <div class="contact-role" contenteditable="true" data-field="members.${i}.role">${ce(m.role || 'Position')}</div>
              </div>
            </div>`).join('')}
          </div>
        </div>`;
        break;
      }

      // ── pricing ───────────────────────────────────────────────────────────
      case 'pricing': {
        const plans = ensureArray(s, 'plans', [newInlineItem('plan')]);
        preview = `<div class="preview-pricing">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;text-align:center;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('plan', 'plans', 'Preis hinzufügen')}
          <div class="pricing-row">
            ${plans.map((p, i) => {
              const features = ensureArray(p, 'features', []);
              return `<div class="pricing-mini inline-edit-item ${p.featured?'featured':''}">
              ${inlineItemControls('plans', i, plans.length > 1)}
              <div class="inline-pricing-controls" contenteditable="false">
                <button type="button" class="required-toggle ${p.featured?'active':''}" data-inline-action="toggle-plan-featured" data-item-index="${i}">${p.featured?'Markiert':'Nicht markiert'}</button>
              </div>
              ${p.featured ? `<div class="pricing-badge inline-pricing-badge" contenteditable="true" data-field="plans.${i}.badgeLabel">${ce(p.badgeLabel || 'Beliebteste Wahl')}</div>` : ''}
              <h3 contenteditable="true" data-field="plans.${i}.name" style="font-size:1.25rem;font-weight:600;margin-bottom:.5rem">${ce(p.name || 'Plan')}</h3>
              <div class="pricing-price" style="font-size:2rem"><span contenteditable="true" data-field="plans.${i}.price">${ce(p.price || '0€')}</span> <span contenteditable="true" data-field="plans.${i}.period">${ce(p.period || '')}</span></div>
              ${inlineAddButton('pricing-feature', `plans.${i}.features`, 'Feature hinzufügen')}
              <ul class="pricing-features editor-pricing-features">
                ${features.map((feature, featureIndex) => `<li class="inline-edit-item pricing-feature-edit ${featureIcon(feature)==='x'?'feature-x':'feature-check'}">
                  ${inlineItemControls(`plans.${i}.features`, featureIndex, true)}
                  <span class="feature-icon-choice" contenteditable="false">
                    <button type="button" class="feature-icon-btn active" data-inline-action="toggle-feature-icon" data-list-path="plans.${i}.features" data-item-index="${featureIndex}" data-value="${featureIcon(feature)}" aria-label="Symbol umschalten" title="Symbol umschalten">${featureIconSymbol(featureIcon(feature))}</button>
                  </span>
                  <span contenteditable="true" data-field="plans.${i}.features.${featureIndex}.text">${ce(featureText(feature) || 'Feature')}</span>
                </li>`).join('')}
              </ul>
              ${renderInlineBtn(p.buttonLabel || 'Wählen', `plans.${i}.buttonLabel`, p.buttonUrl || '#', `plans.${i}.buttonUrl`, p.featured ? 'btn-primary btn-sm' : 'btn-secondary btn-sm', 'width:100%')}
            </div>`;
            }).join('')}
          </div>
        </div>`;
        break;
      }

      // ── testimonials ──────────────────────────────────────────────────────
      case 'testimonials': {
        const testimonials = ensureArray(s, 'items', [{text:'Großartiger Service!',author:'Max M.',stars:5}]);
        preview = `<div class="preview-testimonials">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('testimonial', 'items', 'Bewertung hinzufügen')}
          <div class="testimonial-row">
            ${testimonials.map((t, i) => `<div class="testimonial-card inline-edit-item">
              ${inlineItemControls('items', i, testimonials.length > 1)}
              ${renderStars(t.stars, i)}
              <p contenteditable="true" data-field="items.${i}.text" data-multiline="true" style="margin-bottom:1rem;font-style:italic">"${ce(t.text || 'Zitat...')}"</p>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="avatar" contenteditable="true" data-field="items.${i}.initials" style="background:${ce(t.color || 'var(--accent)')}">${ce(t.initials || initialsFromName(t.author) || '?')}</div>
                <strong contenteditable="true" data-field="items.${i}.author" style="font-size:.875rem">${ce(t.author || 'Autor')}</strong>
              </div>
            </div>`).join('')}
          </div>
        </div>`;
        break;
      }

      // ── accordion ─────────────────────────────────────────────────────────
      case 'accordion': {
        const accItems = ensureArray(s, 'items', [{title:'Frage 1?',body:'Antwort 1'}]);
        preview = `<div class="preview-accordion">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('accordion', 'items', 'Eintrag hinzufügen')}
          ${accItems.map((item, i) => `<div class="acc-item inline-edit-item">
            ${inlineItemControls('items', i, accItems.length > 1)}
            <strong contenteditable="true" data-field="items.${i}.title">${ce(item.title || 'Frage?')}</strong>
            <p contenteditable="true" data-field="items.${i}.body" data-multiline="true" style="margin-top:.5rem;font-size:.875rem;color:var(--text-secondary)">${ce(item.body || 'Antwort...')}</p>
          </div>`).join('')}
        </div>`;
        break;
      }

      // ── tabs ──────────────────────────────────────────────────────────────
      case 'tabs': {
        const tabs = ensureArray(s, 'items', [{title:'Tab 1',content:'Inhalt 1'}]);
        preview = `<div class="preview-tabs">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('tab', 'items', 'Tab hinzufügen')}
          <div class="tabs-wrap editor-tabs-wrap" data-editor-tabs>
            <div class="tabs" role="tablist">
              ${tabs.map((t, i) => `<button class="tab-btn inline-edit-item ${i===0?'active':''}" type="button" role="tab" aria-selected="${i===0?'true':'false'}" data-editor-tab="${i}">${inlineCompactItemControls('items', i, tabs.length > 1)}<span contenteditable="true" data-field="items.${i}.title">${ce(t.title || 'Tab')}</span></button>`).join('')}
            </div>
            ${tabs.map((t, i) => `<div class="tab-panel inline-edit-item ${i===0?'active':''}" role="tabpanel" data-editor-tab-panel="${i}">
              <p contenteditable="true" data-field="items.${i}.content" data-multiline="true">${ce(t.content || 'Tab-Inhalt...')}</p>
            </div>`).join('')}
          </div>
        </div>`;
        break;
      }

      // ── timeline ──────────────────────────────────────────────────────────
      case 'timeline': {
        const tlItems = ensureArray(s, 'items', [{date:'2024',title:'Start',body:'Die Reise beginnt.'}]);
        preview = `<div class="preview-timeline">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('timeline', 'items', 'Punkt hinzufügen')}
          <div class="tl-list">
            ${tlItems.map((item, i) => `<div class="tl-item inline-edit-item">
              ${inlineItemControls('items', i, tlItems.length > 1)}
              <div class="tl-dot"></div>
              <strong contenteditable="true" data-field="items.${i}.date" style="color:var(--accent)">${ce(item.date || '2024')}</strong>
              <h3 contenteditable="true" data-field="items.${i}.title" style="font-weight:600;margin:.25rem 0">${ce(item.title || 'Titel')}</h3>
              <p contenteditable="true" data-field="items.${i}.body" data-multiline="true" style="font-size:.875rem;color:var(--text-secondary)">${ce(item.body || 'Beschreibung...')}</p>
            </div>`).join('')}
          </div>
        </div>`;
        break;
      }

      // ── cta ───────────────────────────────────────────────────────────────
      case 'cta':
        preview = `<div class="preview-cta">
          <h2 contenteditable="true" data-field="heading" style="font-size:2rem;font-weight:700;margin-bottom:1rem;color:white">${ce(s.heading || 'Bereit loszulegen?')}</h2>
          <p contenteditable="true" data-field="text" data-multiline="true" style="font-size:1.125rem;margin-bottom:2rem;color:rgba(255,255,255,.9)">${ce(s.text || 'Kontaktiere uns noch heute')}</p>
          ${renderInlineBtn(s.buttonLabel || 'Jetzt starten', 'buttonLabel', s.buttonUrl, 'buttonUrl', 'btn-lg', 'background:white;color:var(--accent);border-color:white')}
        </div>`;
        break;

      // ── image ─────────────────────────────────────────────────────────────
      case 'image':
        preview = `<div class="preview-image">
          <div class="inline-media-actions" contenteditable="false">
            ${mediaPickButton('image', 'url', s.url ? 'Bild aendern' : 'Bild auswaehlen')}
          </div>
          ${s.url
            ? `<div class="img-card" style="max-width:${s.width === 'narrow' ? '560px' : (s.width === 'medium' ? '840px' : '100%')};margin:0 auto"><img src="${ce(s.url)}" alt="${ce(s.alt||'')}" style="width:100%;border-radius:8px"></div>`
            : `<div class="preview-image-placeholder"><svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:64px;height:64px"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg><p>Kein Bild ausgewählt</p></div>`}
          ${s.captionTitle||s.captionText ? `<div class="img-caption" style="text-align:center"><strong contenteditable="true" data-field="captionTitle">${ce(s.captionTitle||'')}</strong><span contenteditable="true" data-field="captionText">${ce(s.captionText||'')}</span></div>` : ''}
        </div>`;
        break;

      // ── gallery ───────────────────────────────────────────────────────────
      case 'gallery': {
        const galleryItems = ensureArray(s, 'items', []);
        preview = `<div class="preview-image">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('gallery-image', 'items', 'Bild hinzufuegen')}
          ${galleryItems.length > 0
            ? `<div class="editor-gallery-grid">${galleryItems.map((g, i)=>`<div class="editor-gallery-item inline-edit-item">
                ${inlineItemControls('items', i, true)}
                ${g.url ? `<img src="${ce(g.url)}" alt="${ce(g.alt||'')}">` : '<div class="preview-image-placeholder"><p>Kein Bild</p></div>'}
                <div class="inline-media-actions" contenteditable="false">${mediaPickButton('image', `items.${i}.url`, g.url ? 'Aendern' : 'Auswaehlen', i)}</div>
                <div contenteditable="true" data-field="items.${i}.alt" class="inline-alt-edit">${ce(g.alt || '')}</div>
              </div>`).join('')}</div>`
            : '<div class="preview-image-placeholder"><p>Keine Bilder ausgewählt</p></div>'}
        </div>`;
        break;
      }

      // ── video ─────────────────────────────────────────────────────────────
      case 'carousel': {
        const carouselItems = ensureArray(s, 'items', []);
        const speed = parseInt(s.speed || 35) || 35;
        preview = `<div class="preview-image">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('gallery-image', 'items', 'Bild hinzufuegen')}
          <div class="editor-carousel-strip" style="--carousel-speed:${speed}s">
            ${carouselItems.length ? carouselItems.map((g, i)=>`<div class="editor-carousel-item inline-edit-item">
              ${inlineItemControls('items', i, true)}
              ${g.url ? `<img src="${ce(g.url)}" alt="${ce(g.alt||'')}">` : '<div class="preview-image-placeholder"><p>Kein Bild</p></div>'}
              <div class="inline-media-actions" contenteditable="false">${mediaPickButton('image', `items.${i}.url`, g.url ? 'Aendern' : 'Auswaehlen', i)}</div>
              <div contenteditable="true" data-field="items.${i}.alt" class="inline-alt-edit">${ce(g.alt || '')}</div>
            </div>`).join('') : '<div class="preview-image-placeholder"><p>Keine Bilder ausgewaehlt</p></div>'}
          </div>
        </div>`;
        break;
      }

      case 'video':
        preview = `<div class="preview-video">
          <div class="inline-media-actions" contenteditable="false">
            ${mediaPickButton('video', 'url', s.url ? 'Video aendern' : 'Video auswaehlen')}
            ${mediaPickButton('image', 'poster', s.poster ? 'Poster aendern' : 'Poster auswaehlen')}
          </div>
          <div class="video-thumb" style="${s.poster?`background-image:url('${ce(s.poster)}');background-size:cover`:''}">
            <div class="play-circle"><svg fill="currentColor" viewBox="0 0 24 24" style="width:24px;height:24px"><path d="M8 5v14l11-7z"/></svg></div>
          </div>
          ${s.captionTitle||s.captionText ? `<p style="text-align:center;margin-top:12px;font-size:.85rem;color:var(--text-muted)"><strong contenteditable="true" data-field="captionTitle">${ce(s.captionTitle||'')}</strong> <span contenteditable="true" data-field="captionText">${ce(s.captionText||'')}</span></p>` : ''}
        </div>`;
        break;

      // ── form ──────────────────────────────────────────────────────────────
      case 'form': {
        const formFields = ensureArray(s, 'fields', [{label:'Name',type:'text',required:true},{label:'E-Mail',type:'email',required:true},{label:'Nachricht',type:'textarea',required:false}]);
        preview = `<div class="preview-form">
          ${renderSectionLabel(s)}
          ${s.heading ? `<h2 contenteditable="true" data-field="heading" style="margin-bottom:2rem;font-size:1.875rem;font-weight:700">${ce(s.heading)}</h2>` : ''}
          ${inlineAddButton('form-field', 'fields', 'Feld hinzufügen')}
          <form style="max-width:520px;margin-top:24px;display:flex;flex-direction:column;gap:14px" onsubmit="return false">
          ${formFields.map((f, i) => `<div class="form-group inline-edit-item form-field-edit-item">
            ${inlineItemControls('fields', i, true)}
            <label class="form-label"><span contenteditable="true" data-field="fields.${i}.label">${ce(f.label || 'Feld')}</span><button type="button" class="required-toggle ${f.required?'active':''}" data-inline-action="toggle-required" data-item-index="${i}" title="Pflichtfeld">${f.required?'Pflicht':'Optional'}</button></label>
            ${f.type==='textarea'
              ? `<textarea class="textarea" rows="4" placeholder="${ce(f.placeholder||'')}"></textarea>`
              : `<input type="${ce(f.type||'text')}" class="input" placeholder="${ce(f.placeholder||'')}">`}
            ${f.placeholder ? `<div class="inline-placeholder-edit"><span>Platzhalter</span><span contenteditable="true" data-field="fields.${i}.placeholder">${ce(f.placeholder)}</span></div>` : ''}
          </div>`).join('')}
          <button class="btn btn-primary" type="button"><span contenteditable="true" data-field="buttonLabel">${ce(s.buttonLabel || 'Absenden')}</span></button>
          </form>
        </div>`;
        break;
      }

      // ── code ──────────────────────────────────────────────────────────────
      case 'code':
        preview = `<div class="preview-code">
          <div contenteditable="true" data-field="code" data-multiline="true" class="code-block">${ce(s.code || '// Code hier einfügen...')}</div>
        </div>`;
        break;

      // ── quote ─────────────────────────────────────────────────────────────
      case 'quote':
        preview = `<div style="padding:2rem;border-left:4px solid var(--accent);background:var(--bg-secondary);border-radius:8px;margin:2rem 0">
          <p contenteditable="true" data-field="text" data-multiline="true" style="font-size:1.25rem;font-style:italic;margin-bottom:1rem">"${ce(s.text || 'Zitat...')}"</p>
          <strong style="color:var(--text-secondary)">— <span contenteditable="true" data-field="author">${ce(s.author || 'Autor')}</span></strong>
        </div>`;
        break;

      // ── divider ───────────────────────────────────────────────────────────
      case 'divider':
        preview = `<div class="preview-divider">
          <hr style="border-color:var(--${s.style==='thick'?'accent':'border-primary'});border-width:${s.style==='thick'?'3px':'1px'}">
          ${s.text ? `<span contenteditable="true" data-field="text" style="font-size:.85rem;color:var(--text-secondary)">${ce(s.text)}</span>` : ''}
        </div>`;
        break;

      // ── spacer ────────────────────────────────────────────────────────────
      case 'spacer': {
        const h = {small:'2rem',medium:'4rem',large:'6rem'}[s.size] || '4rem';
        preview = `<div style="height:${h};background:repeating-linear-gradient(90deg,var(--border-primary),var(--border-primary) 10px,transparent 10px,transparent 20px);opacity:.3"></div>`;
        break;
      }

      // ── html ──────────────────────────────────────────────────────────────
      case 'html':
        preview = `<div style="padding:2rem;background:var(--bg-secondary);border:1px solid var(--border-primary);border-radius:8px">
          <p style="font-family:var(--font-mono);font-size:.875rem;color:var(--text-secondary)">&lt;Benutzerdefiniertes HTML&gt;</p>
        </div>`;
        break;

      default:
        preview = `<div style="padding:2rem;text-align:center;color:var(--text-tertiary)">Unbekannter Block-Typ: ${ce(type)}</div>`;
    }

    return `<div class="cms-block ${selectedBlockIndex === index ? 'selected' : ''}" data-index="${index}">
      <div class="block-type-tag">${ce(type)}</div>
      <div class="block-toolbar">
        ${index > 0 ? '<button class="block-tool" data-action="up"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg></button>' : ''}
        ${index < blocks.length - 1 ? '<button class="block-tool" data-action="down"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></button>' : ''}
        <button class="block-tool" data-action="edit"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg></button>
        <button class="block-tool delete" data-action="delete"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg></button>
      </div>
      ${preview}
    </div>`;
  }

  // ---------------------------------------------------------------------------
  // Attach Block Handlers
  // ---------------------------------------------------------------------------
  function attachBlockHandlers() {
    canvas.querySelectorAll('.cms-block').forEach(block => {
      block.addEventListener('click', (e) => {
        if (e.target.closest('.block-toolbar')) return;
        const index = parseInt(block.dataset.index);
        if (e.target.closest('[data-field]')) {
          if (selectedBlockIndex !== index) {
            selectedBlockIndex = index;
            canvas.querySelectorAll('.cms-block').forEach(b =>
              b.classList.toggle('selected', parseInt(b.dataset.index) === index)
            );
          }
          return;
        }
        selectedBlockIndex = index;
        renderCanvas();
      });
    });

    canvas.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const action = btn.dataset.action;
        const index = parseInt(btn.closest('.cms-block').dataset.index);
        if (action === 'up' && index > 0) {
          [blocks[index], blocks[index-1]] = [blocks[index-1], blocks[index]];
          selectedBlockIndex = index - 1;
          markDirty();
          renderCanvas();
        } else if (action === 'down' && index < blocks.length - 1) {
          [blocks[index], blocks[index+1]] = [blocks[index+1], blocks[index]];
          selectedBlockIndex = index + 1;
          markDirty();
          renderCanvas();
        } else if (action === 'edit') {
          openSettingsModal(index);
        } else if (action === 'delete') {
          if (await cmsEditorConfirm({
            title: 'Block löschen',
            message: 'Block wirklich löschen?',
            confirmText: 'Löschen'
          })) {
            blocks.splice(index, 1);
            selectedBlockIndex = null;
            markDirty();
            renderCanvas();
          }
        }
      });
    });

    canvas.querySelectorAll('.inline-drag-handle').forEach(handle => {
      handle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
      });

      handle.addEventListener('dragstart', (e) => {
        e.stopPropagation();
        flushActiveField();
        const blockEl = handle.closest('.cms-block');
        if (!blockEl) return;
        inlineDrag = {
          blockIndex: parseInt(blockEl.dataset.index),
          path: handle.dataset.listPath,
          from: parseInt(handle.dataset.itemIndex)
        };
        handle.closest('.inline-edit-item')?.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', JSON.stringify(inlineDrag));
      });

      handle.addEventListener('dragend', () => {
        inlineDrag = null;
        canvas.querySelectorAll('.inline-edit-item.dragging, .inline-edit-item.drag-over').forEach(el => {
          el.classList.remove('dragging', 'drag-over');
        });
      });
    });

    canvas.querySelectorAll('.inline-edit-item').forEach(item => {
      item.addEventListener('dragover', (e) => {
        const targetHandle = item.querySelector('.inline-drag-handle');
        const blockEl = item.closest('.cms-block');
        if (!inlineDrag || !targetHandle || !blockEl) return;
        if (inlineDrag.blockIndex !== parseInt(blockEl.dataset.index) || inlineDrag.path !== targetHandle.dataset.listPath) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        item.classList.add('drag-over');
      });

      item.addEventListener('dragleave', () => {
        item.classList.remove('drag-over');
      });

      item.addEventListener('drop', (e) => {
        const targetHandle = item.querySelector('.inline-drag-handle');
        const blockEl = item.closest('.cms-block');
        if (!inlineDrag || !targetHandle || !blockEl) return;
        const blockIndex = parseInt(blockEl.dataset.index);
        const to = parseInt(targetHandle.dataset.itemIndex);
        if (inlineDrag.blockIndex !== blockIndex || inlineDrag.path !== targetHandle.dataset.listPath) return;
        e.preventDefault();
        e.stopPropagation();
        const list = ensureArray(blocks[blockIndex].settings, inlineDrag.path, []);
        if (inlineDrag.from === to || !list[inlineDrag.from] || !list[to]) return;
        const [moved] = list.splice(inlineDrag.from, 1);
        list.splice(to, 0, moved);
        selectedBlockIndex = blockIndex;
        inlineDrag = null;
        markDirty();
        renderCanvas();
      });
    });

    canvas.querySelectorAll('[data-inline-action]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (btn.disabled) return;
        flushActiveField();

        const blockEl = btn.closest('.cms-block');
        if (!blockEl) return;
        const index = parseInt(blockEl.dataset.index);
        const block = blocks[index];
        const settings = block.settings || {};
        const action = btn.dataset.inlineAction;

        if (action === 'add-item') {
          const path = btn.dataset.listPath;
          const kind = btn.dataset.listKind;
          const list = ensureArray(settings, path, []);
          const newItem = newInlineItem(kind);
          list.push(newItem);
          if (kind === 'gallery-image') {
            openMediaPicker({
              types: 'image',
              title: 'Bild auswaehlen',
              onSelect: (media) => {
                newItem.url = media.url;
                if (!newItem.alt) newItem.alt = media.name || '';
                block.settings = settings;
                selectedBlockIndex = index;
                markDirty();
                renderCanvas();
              }
            });
          }
        } else if (action === 'remove-item') {
          const path = btn.dataset.listPath;
          const list = ensureArray(settings, path, []);
          const itemIndex = parseInt(btn.dataset.itemIndex);
          if (path === 'fields' || path.endsWith('.features') || list.length > 1) list.splice(itemIndex, 1);
        } else if (action === 'toggle-plan-featured') {
          const item = ensureArray(settings, 'plans', [])[parseInt(btn.dataset.itemIndex)];
          if (item) item.featured = !item.featured;
        } else if (action === 'toggle-feature-icon') {
          const list = ensureArray(settings, btn.dataset.listPath, []);
          const itemIndex = parseInt(btn.dataset.itemIndex);
          const item = list[itemIndex];
          if (item != null) {
            const nextIcon = featureIcon(item) === 'x' ? 'check' : 'x';
            if (typeof item === 'string') {
              list[itemIndex] = { text: item, icon: nextIcon };
            } else {
              item.icon = nextIcon;
            }
          }
        } else if (action === 'set-stat-color') {
          const item = ensureArray(settings, 'items', [newInlineItem('stat')])[parseInt(btn.dataset.itemIndex)];
          if (item) {
            item.color = btn.dataset.value;
            item.up = btn.dataset.value !== 'red';
          }
        } else if (action === 'set-stat-arrow') {
          const item = ensureArray(settings, 'items', [newInlineItem('stat')])[parseInt(btn.dataset.itemIndex)];
          if (item) {
            item.arrow = btn.dataset.value;
            item.up = btn.dataset.value !== 'down';
          }
        } else if (action === 'set-stars') {
          const item = ensureArray(settings, 'items', [])[parseInt(btn.dataset.itemIndex)];
          if (item) item.stars = Math.max(0, Math.min(5, parseInt(btn.dataset.value) || 0));
        } else if (action === 'toggle-required') {
          const item = ensureArray(settings, 'fields', [])[parseInt(btn.dataset.itemIndex)];
          if (item) item.required = !item.required;
        } else if (action === 'select-media') {
          openMediaPicker({
            types: btn.dataset.mediaTypes || 'image',
            title: 'Medium auswaehlen',
            onSelect: (media) => {
              setFieldValue(settings, btn.dataset.targetPath, media.url);
              if (/^items\.\d+\.url$/.test(btn.dataset.targetPath || '')) {
                const item = getFieldValue(settings, btn.dataset.targetPath.replace(/\.url$/, ''));
                if (item && !item.alt) item.alt = media.name || '';
              }
              block.settings = settings;
              selectedBlockIndex = index;
              markDirty();
              renderCanvas();
            }
          });
          return;
        }

        block.settings = settings;
        selectedBlockIndex = index;
        markDirty();
        renderCanvas();
      });
    });

    canvas.querySelectorAll('.inline-stars').forEach(starsWrap => {
      const paint = (value) => {
        const v = Math.max(0, Math.min(5, parseInt(value) || 0));
        starsWrap.querySelectorAll('.star-btn').forEach(star => {
          const starValue = parseInt(star.dataset.value);
          star.classList.toggle('preview', starValue <= v);
          star.classList.toggle('preview-off', star.classList.contains('active') && starValue > v);
        });
        starsWrap.querySelector('.star-zero')?.classList.toggle('preview', v === 0);
      };
      starsWrap.querySelectorAll('[data-inline-action="set-stars"]').forEach(star => {
        star.addEventListener('mouseenter', () => paint(star.dataset.value));
      });
      starsWrap.addEventListener('mouseleave', () => paint(starsWrap.dataset.stars));
    });

    canvas.querySelectorAll('[data-editor-tab]').forEach(btn => {
      btn.addEventListener('click', () => {
        const wrap = btn.closest('[data-editor-tabs]');
        if (!wrap) return;
        const tabIndex = btn.dataset.editorTab;
        wrap.querySelectorAll('[data-editor-tab]').forEach(tab => {
          const active = tab.dataset.editorTab === tabIndex;
          tab.classList.toggle('active', active);
          tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        wrap.querySelectorAll('[data-editor-tab-panel]').forEach(panel => {
          panel.classList.toggle('active', panel.dataset.editorTabPanel === tabIndex);
        });
      });
    });

    // Inline text editing
    canvas.querySelectorAll('[data-field]').forEach(el => {
      el.addEventListener('input', () => {
        const blockEl = el.closest('.cms-block');
        if (!blockEl) return;
        const index = parseInt(blockEl.dataset.index);
        const field = el.dataset.field;
        if (!/^members\.\d+\.name$/.test(field) && !/^items\.\d+\.author$/.test(field)) return;

        const value = editableFieldValue(el);
        const previousValue = getFieldValue(blocks[index].settings, field);
        setFieldValue(blocks[index].settings, field, value);
        syncInitialsForField(index, field, value, blockEl, previousValue);
        markDirty();
      });

      el.addEventListener('blur', () => {
        const blockEl = el.closest('.cms-block');
        if (!blockEl) return;
        const index = parseInt(blockEl.dataset.index);
        const value = editableFieldValue(el);
        const previousValue = getFieldValue(blocks[index].settings, el.dataset.field);
        setFieldValue(blocks[index].settings, el.dataset.field, value);
        syncInitialsForField(index, el.dataset.field, value, blockEl, previousValue);
        const teamName = el.dataset.field.match(/^members\.(\d+)\.name$/);
        if (blocks[index].type === 'team' && teamName) {
          const member = blocks[index].settings.members?.[parseInt(teamName[1])];
          if (member && member.initialsManual !== true && member.initialsManual !== 'true') {
            member.initials = initialsFromName(value);
            const card = blockEl.querySelectorAll('.team-person-card')[parseInt(teamName[1])];
            const avatar = card?.querySelector('.team-avatar');
            if (avatar) avatar.textContent = displayInitials(member);
          }
        }
        const teamInitials = el.dataset.field.match(/^members\.(\d+)\.initials$/);
        if (blocks[index].type === 'team' && teamInitials) {
          const member = blocks[index].settings.members?.[parseInt(teamInitials[1])];
          if (member) member.initialsManual = true;
        }
        const testimonialAuthor = el.dataset.field.match(/^items\.(\d+)\.author$/);
        if (blocks[index].type === 'testimonials' && testimonialAuthor) {
          const item = blocks[index].settings.items?.[parseInt(testimonialAuthor[1])];
          if (item && item.initialsManual !== true && item.initialsManual !== 'true') {
            item.initials = initialsFromName(value);
            const card = blockEl.querySelectorAll('.testimonial-card')[parseInt(testimonialAuthor[1])];
            const avatar = card?.querySelector('.avatar[data-field]');
            if (avatar) avatar.textContent = item.initials || '?';
          }
        }
        const testimonialInitials = el.dataset.field.match(/^items\.(\d+)\.initials$/);
        if (blocks[index].type === 'testimonials' && testimonialInitials) {
          const item = blocks[index].settings.items?.[parseInt(testimonialInitials[1])];
          if (item) item.initialsManual = true;
        }
        markDirty();
      });
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !el.dataset.multiline) { e.preventDefault(); el.blur(); }
        if (e.key === 'Escape') { e.stopPropagation(); el.blur(); }
      });
    });
  }

  // ---------------------------------------------------------------------------
  // Add Block Buttons
  // ---------------------------------------------------------------------------
  document.querySelectorAll('[data-add-block]').forEach(btn => {
    btn.addEventListener('click', () => {
      const type = btn.dataset.addBlock;
      const defaults = BLOCK_TYPES[type] || {};
      blocks.push({ type, settings: JSON.parse(JSON.stringify(defaults)) });
      selectedBlockIndex = blocks.length - 1;
      markDirty();
      renderCanvas();
      canvas.scrollTop = canvas.scrollHeight;
    });
  });

  // ---------------------------------------------------------------------------
  // Settings Modal
  // ---------------------------------------------------------------------------
  function openSettingsModal(index) {
    const block = blocks[index];
    const type = block.type;
    const s = block.settings || {};

    document.querySelector('.block-settings-overlay')?.remove();

    const overlay = document.createElement('div');
    overlay.className = 'block-settings-overlay';
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });

    let formFields = '';

    if (type === 'hero') {
      formFields = `
        <label><span>Eyebrow-Text (optional)</span><input type="text" name="eyebrow" value="${esc(s.eyebrow||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <label><span>Text</span><textarea name="text" class="w-full" rows="4">${esc(s.text||'')}</textarea></label>
        <label><span>Primär-Button Text</span><input type="text" name="primaryLabel" value="${esc(s.primaryLabel||'')}" class="w-full"></label>
        <label><span>Primär-Button Link</span><input type="text" name="primaryUrl" value="${esc(s.primaryUrl||'')}" class="w-full"></label>
        <label><span>Sekundär-Button Text (optional)</span><input type="text" name="secondaryLabel" value="${esc(s.secondaryLabel||'')}" class="w-full"></label>
        <label><span>Sekundär-Button Link</span><input type="text" name="secondaryUrl" value="${esc(s.secondaryUrl||'')}" class="w-full"></label>`;
    } else if (type === 'text') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <label><span>Text</span><textarea name="text" class="w-full" rows="8">${esc(s.text||'')}</textarea></label>
        <label><span>Ausrichtung</span><select name="align" class="w-full">
          <option value="left" ${(s.align||'left')==='left'?'selected':''}>Links</option>
          <option value="center" ${s.align==='center'?'selected':''}>Zentriert</option>
        </select></label>`;
    } else if (type === 'cards') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="cardsContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addCardItem()">+ Karte hinzufügen</button>`;
    } else if (type === 'stats') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="statsContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addStatItem()">+ Statistik hinzufügen</button>`;
    } else if (type === 'team') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="teamContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addTeamMember()">+ Mitglied hinzufügen</button>`;
    } else if (type === 'pricing') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="pricingContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addPricingPlan()">+ Plan hinzufügen</button>`;
    } else if (type === 'testimonials') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="testimonialsContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addTestimonial()">+ Bewertung hinzufügen</button>`;
    } else if (type === 'accordion') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="accordionContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addAccordionItem()">+ Eintrag hinzufügen</button>`;
    } else if (type === 'tabs') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="tabsContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addTabItem()">+ Tab hinzufügen</button>`;
    } else if (type === 'timeline') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="timelineContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addTimelineItem()">+ Eintrag hinzufügen</button>`;
    } else if (type === 'cta') {
      formFields = `
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <label><span>Text</span><textarea name="text" class="w-full" rows="3">${esc(s.text||'')}</textarea></label>
        <label><span>Button-Text</span><input type="text" name="buttonLabel" value="${esc(s.buttonLabel||'')}" class="w-full"></label>
        <label><span>Button-Link</span><input type="text" name="buttonUrl" value="${esc(s.buttonUrl||'')}" class="w-full"></label>`;
    } else if (type === 'image') {
      formFields = `
        <label><span>Bild-URL</span><div class="media-input-row"><input type="text" name="url" value="${esc(s.url||'')}" class="w-full" id="imageUrlInput"><button type="button" class="btn secondary sm" data-modal-media="image" data-modal-target="url">Auswaehlen</button></div></label>
        <label><span>Alt-Text</span><input type="text" name="alt" value="${esc(s.alt||'')}" class="w-full"></label>
        <label><span>Bildbreite</span><select name="width" class="w-full">
          <option value="full" ${(s.width||'full')==='full'?'selected':''}>Voll</option>
          <option value="medium" ${s.width==='medium'?'selected':''}>Mittel</option>
          <option value="narrow" ${s.width==='narrow'?'selected':''}>Schmal</option>
        </select></label>
        <label><span>Bildunterschrift Titel</span><input type="text" name="captionTitle" value="${esc(s.captionTitle||'')}" class="w-full"></label>
        <label><span>Bildunterschrift Text</span><input type="text" name="captionText" value="${esc(s.captionText||'')}" class="w-full"></label>
        <p style="font-size:.875rem;color:var(--text-secondary);margin-top:.5rem">Tipp: Klicke in der Mediathek auf ein Bild um es einzufügen.</p>`;
    } else if (type === 'gallery') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <div id="galleryContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addGalleryImage()">+ Bild hinzufügen</button>`;
    } else if (type === 'carousel') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Ãœberschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <label><span>Geschwindigkeit</span><select name="speed" class="w-full">
          <option value="20" ${(s.speed||'35')==='20'?'selected':''}>Schnell</option>
          <option value="35" ${(!s.speed||s.speed==='35')?'selected':''}>Normal</option>
          <option value="55" ${s.speed==='55'?'selected':''}>Langsam</option>
        </select></label>
        <div id="galleryContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addGalleryImage()">+ Bild hinzufÃ¼gen</button>`;
    } else if (type === 'video') {
      formFields = `
        <label><span>Video-URL</span><div class="media-input-row"><input type="text" name="url" value="${esc(s.url||'')}" class="w-full"><button type="button" class="btn secondary sm" data-modal-media="video" data-modal-target="url">Auswaehlen</button></div></label>
        <label><span>Poster/Thumbnail-URL</span><div class="media-input-row"><input type="text" name="poster" value="${esc(s.poster||'')}" class="w-full"><button type="button" class="btn secondary sm" data-modal-media="image" data-modal-target="poster">Auswaehlen</button></div></label>
        <label><span>Untertitel Titel</span><input type="text" name="captionTitle" value="${esc(s.captionTitle||'')}" class="w-full"></label>
        <label><span>Untertitel Text</span><input type="text" name="captionText" value="${esc(s.captionText||'')}" class="w-full"></label>`;
    } else if (type === 'form') {
      formFields = `
        <label><span>Label (optional)</span><input type="text" name="label" value="${esc(s.label||'')}" class="w-full"></label>
        <label><span>Überschrift</span><input type="text" name="heading" value="${esc(s.heading||'')}" class="w-full"></label>
        <label><span>Button-Text</span><input type="text" name="buttonLabel" value="${esc(s.buttonLabel||'Absenden')}" class="w-full"></label>
        <label><span>Empfänger E-Mail</span><input type="text" name="recipient" value="${esc(s.recipient||'')}" class="w-full"></label>
        <div id="formFieldsContainer"></div>
        <button type="button" class="btn secondary sm" onclick="addFormField()">+ Feld hinzufügen</button>`;
    } else if (type === 'code') {
      formFields = `
        <label><span>Sprache</span><select name="language" class="w-full">
          <option value="css" ${(s.language||'css')==='css'?'selected':''}>CSS</option>
          <option value="javascript" ${s.language==='javascript'?'selected':''}>JavaScript</option>
          <option value="html" ${s.language==='html'?'selected':''}>HTML</option>
          <option value="php" ${s.language==='php'?'selected':''}>PHP</option>
          <option value="plaintext" ${s.language==='plaintext'?'selected':''}>Plaintext</option>
        </select></label>
        <label><span>Code</span><textarea name="code" class="w-full font-mono" rows="12" style="font-size:.85rem">${esc(s.code||'')}</textarea></label>`;
    } else if (type === 'quote') {
      formFields = `
        <label><span>Zitat</span><textarea name="text" class="w-full" rows="4">${esc(s.text||'')}</textarea></label>
        <label><span>Autor (optional)</span><input type="text" name="author" value="${esc(s.author||'')}" class="w-full"></label>`;
    } else if (type === 'divider') {
      formFields = `
        <label><span>Text (optional)</span><input type="text" name="text" value="${esc(s.text||'')}" class="w-full"></label>
        <label><span>Stil</span><select name="style" class="w-full">
          <option value="line" ${(s.style||'line')==='line'?'selected':''}>Dünn</option>
          <option value="thick" ${s.style==='thick'?'selected':''}>Dick</option>
        </select></label>`;
    } else if (type === 'spacer') {
      formFields = `
        <label><span>Größe</span><select name="size" class="w-full">
          <option value="small" ${s.size==='small'?'selected':''}>Klein (2rem)</option>
          <option value="medium" ${(!s.size||s.size==='medium')?'selected':''}>Mittel (4rem)</option>
          <option value="large" ${s.size==='large'?'selected':''}>Groß (6rem)</option>
        </select></label>`;
    } else if (type === 'html') {
      formFields = `
        <label><span>HTML-Code</span><textarea name="code" class="w-full font-mono" rows="15" style="font-size:.85rem">${esc(s.code||'')}</textarea></label>`;
    }

    overlay.innerHTML = `<div class="block-settings-modal">
      <div class="modal-head">
        <h3>${ce(type.charAt(0).toUpperCase()+type.slice(1))} bearbeiten</h3>
        <button type="button" class="modal-close" onclick="this.closest('.block-settings-overlay').remove()">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <form id="blockSettingsForm">${formFields}</form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn secondary" onclick="this.closest('.block-settings-overlay').remove()">Abbrechen</button>
        <button type="button" class="btn primary" onclick="applyBlockSettings(${index})">Übernehmen</button>
      </div>
    </div>`;

    document.body.appendChild(overlay);

    // Populate repeaters with existing data
    if (type === 'cards' && s.items) s.items.forEach(item => addCardItem(item));
    if (type === 'stats' && s.items) s.items.forEach(item => addStatItem(item));
    if (type === 'team' && s.members) s.members.forEach(item => addTeamMember(item));
    if (type === 'pricing' && s.plans) s.plans.forEach(item => addPricingPlan(item));
    if (type === 'testimonials' && s.items) s.items.forEach(item => addTestimonial(item));
    if (type === 'accordion' && s.items) s.items.forEach(item => addAccordionItem(item));
    if (type === 'tabs' && s.items) s.items.forEach(item => addTabItem(item));
    if (type === 'timeline' && s.items) s.items.forEach(item => addTimelineItem(item));
    if ((type === 'gallery' || type === 'carousel') && s.items) s.items.forEach(item => addGalleryImage(item));
    if (type === 'form' && s.fields) s.fields.forEach(item => addFormField(item));

    overlay.querySelectorAll('[data-modal-media]').forEach(button => {
      button.addEventListener('click', () => {
        const target = overlay.querySelector(`[name="${button.dataset.modalTarget}"]`);
        openMediaPicker({
          types: button.dataset.modalMedia || 'image',
          title: 'Medium auswaehlen',
          onSelect: (media) => {
            if (target) target.value = media.url;
          }
        });
      });
    });
  }

  function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function cmsEditorConfirm(options) {
    if (typeof window.cmsConfirm === 'function') return window.cmsConfirm(options);
    return Promise.resolve(window.confirm(options?.message || 'Aktion wirklich ausführen?'));
  }

  // ---------------------------------------------------------------------------
  // Repeater Helpers  (field names match core/functions.php block_default_settings)
  // ---------------------------------------------------------------------------
  window.addCardItem = function(data = {}) {
    const c = document.getElementById('cardsContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Karte ${i+1} <button type="button" class="remove" data-confirm-remove="Karte wirklich entfernen?" data-remove-target=".repeat-item" data-min-siblings="1">Entfernen</button></h5>
      <label><span>Icon (Emoji)</span><input type="text" name="items[${i}][icon]" value="${esc(data.icon||'')}" class="w-full"></label>
      <label><span>Titel</span><input type="text" name="items[${i}][title]" value="${esc(data.title||'')}" class="w-full"></label>
      <label><span>Text</span><textarea name="items[${i}][text]" class="w-full" rows="2">${esc(data.text||'')}</textarea></label>
      <label><span>Link-URL</span><input type="text" name="items[${i}][url]" value="${esc(data.url||'')}" class="w-full"></label>
      <label><span>Link-Label</span><input type="text" name="items[${i}][urlLabel]" value="${esc(data.urlLabel||'')}" class="w-full"></label>`;
    c.appendChild(d);
  };

  window.addStatItem = function(data = {}) {
    const c = document.getElementById('statsContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    const color = statColorValue(data);
    const arrow = statArrowValue(data);
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Statistik ${i+1} <button type="button" class="remove" data-confirm-remove="Statistik wirklich entfernen?" data-remove-target=".repeat-item" data-min-siblings="1">Entfernen</button></h5>
      <label><span>Wert</span><input type="text" name="items[${i}][value]" value="${esc(data.value||'')}" class="w-full"></label>
      <label><span>Label</span><input type="text" name="items[${i}][label]" value="${esc(data.label||'')}" class="w-full"></label>
      <label><span>Veränderung</span><input type="text" name="items[${i}][delta]" value="${esc(statDeltaText(data))}" class="w-full"></label>
      <label><span>Farbe</span><select name="items[${i}][color]" class="w-full">
        <option value="green" ${color==='green'?'selected':''}>Grün</option>
        <option value="yellow" ${color==='yellow'?'selected':''}>Gelb</option>
        <option value="red" ${color==='red'?'selected':''}>Rot</option>
      </select></label>
      <label><span>Pfeil</span><select name="items[${i}][arrow]" class="w-full">
        <option value="up" ${arrow==='up'?'selected':''}>↑ Hoch</option>
        <option value="right" ${arrow==='right'?'selected':''}>→ Gleich</option>
        <option value="down" ${arrow==='down'?'selected':''}>↓ Runter</option>
      </select></label>
      <label style="display:flex;align-items:center;gap:.5rem">
        <input type="checkbox" name="items[${i}][up]" ${color !== 'red'?'checked':''}> <span>Positiv (grün)</span>
      </label>`;
    c.appendChild(d);
  };

  window.addTeamMember = function(data = {}) {
    const c = document.getElementById('teamContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    const generatedInitials = initialsFromName(data.name);
    const legacyInitials = legacyInitialsFromName(data.name);
    const initials = data.initials || generatedInitials;
    const initialsManual = (data.initialsManual === true || data.initialsManual === 'true' || Boolean(data.initials))
      && initials !== generatedInitials
      && initials !== legacyInitials;
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Mitglied ${i+1} <button type="button" class="remove" data-confirm-remove="Mitglied wirklich entfernen?" data-remove-target=".repeat-item" data-min-siblings="1">Entfernen</button></h5>
      <input type="hidden" name="members[${i}][initialsManual]" value="${initialsManual ? 'true' : 'false'}" data-initials-manual="${i}">
      <label><span>Name</span><input type="text" name="members[${i}][name]" value="${esc(data.name||'')}" class="w-full" data-initials-name="${i}"></label>
      <label><span>Initialen</span><input type="text" name="members[${i}][initials]" value="${esc(initials)}" class="w-full" data-initials-for="${i}"></label>
      <label><span>Position</span><input type="text" name="members[${i}][role]" value="${esc(data.role||'')}" class="w-full"></label>
      <label><span>Farbe (Hex)</span><input type="color" name="members[${i}][color]" value="${esc(data.color||'#6366f1')}" class="w-full"></label>`;
    c.appendChild(d);
    const nameInput = d.querySelector(`[data-initials-name="${i}"]`);
    const initialsInput = d.querySelector(`[data-initials-for="${i}"]`);
    const manualInput = d.querySelector(`[data-initials-manual="${i}"]`);
    nameInput.addEventListener('input', () => {
      if (manualInput.value !== 'true') {
        initialsInput.value = initialsFromName(nameInput.value);
      }
    });
    initialsInput.addEventListener('input', () => {
      manualInput.value = 'true';
    });
  };

  window.addPricingPlan = function(data = {}) {
    const c = document.getElementById('pricingContainer');
    const i = c.children.length;
    const features = Array.isArray(data.features) ? data.features.map(featureText).join('\n') : (data.features || '');
    const d = document.createElement('div');
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Plan ${i+1} <button type="button" class="remove" data-confirm-remove="Plan wirklich entfernen?" data-remove-target=".repeat-item" data-min-siblings="1">Entfernen</button></h5>
      <label><span>Name</span><input type="text" name="plans[${i}][name]" value="${esc(data.name||'')}" class="w-full"></label>
      <label><span>Preis</span><input type="text" name="plans[${i}][price]" value="${esc(data.price||'')}" class="w-full"></label>
      <label><span>Zeitraum (z.B. /Monat)</span><input type="text" name="plans[${i}][period]" value="${esc(data.period||'')}" class="w-full"></label>
      <label><span>Badge-Text</span><input type="text" name="plans[${i}][badgeLabel]" value="${esc(data.badgeLabel||'Beliebteste Wahl')}" class="w-full"></label>
      <label><span>Features (eine pro Zeile)</span><textarea name="plans[${i}][features]" class="w-full" rows="4" data-type="lines-array">${esc(features)}</textarea></label>
      <label><span>Button-Text</span><input type="text" name="plans[${i}][buttonLabel]" value="${esc(data.buttonLabel||'Wählen')}" class="w-full"></label>
      <label><span>Button-Link</span><input type="text" name="plans[${i}][buttonUrl]" value="${esc(data.buttonUrl||'#')}" class="w-full"></label>
      <label style="display:flex;align-items:center;gap:.5rem">
        <input type="checkbox" name="plans[${i}][featured]" ${data.featured?'checked':''}> <span>Hervorgehoben</span>
      </label>`;
    c.appendChild(d);
  };

  window.addTestimonial = function(data = {}) {
    const c = document.getElementById('testimonialsContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    const generatedInitials = initialsFromName(data.author);
    const legacyInitials = legacyInitialsFromName(data.author);
    const initials = data.initials || generatedInitials;
    const initialsManual = (data.initialsManual === true || data.initialsManual === 'true' || Boolean(data.initials))
      && initials !== generatedInitials
      && initials !== legacyInitials;
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Bewertung ${i+1} <button type="button" class="remove" data-confirm-remove="Bewertung wirklich entfernen?" data-remove-target=".repeat-item" data-min-siblings="1">Entfernen</button></h5>
      <input type="hidden" name="items[${i}][initialsManual]" value="${initialsManual ? 'true' : 'false'}" data-testimonial-initials-manual="${i}">
      <label><span>Sterne (0-5)</span><input type="number" name="items[${i}][stars]" value="${esc(data.stars ?? 5)}" min="0" max="5" class="w-full"></label>
      <label><span>Text</span><textarea name="items[${i}][text]" class="w-full" rows="3">${esc(data.text||'')}</textarea></label>
      <label><span>Autor</span><input type="text" name="items[${i}][author]" value="${esc(data.author||'')}" class="w-full" data-testimonial-initials-name="${i}"></label>
      <label><span>Initialen</span><input type="text" name="items[${i}][initials]" value="${esc(initials)}" class="w-full" data-testimonial-initials-for="${i}"></label>
      <label><span>Farbe (Hex)</span><input type="color" name="items[${i}][color]" value="${esc(data.color||'#6366f1')}" class="w-full"></label>`;
    c.appendChild(d);
    const authorInput = d.querySelector(`[data-testimonial-initials-name="${i}"]`);
    const initialsInput = d.querySelector(`[data-testimonial-initials-for="${i}"]`);
    const manualInput = d.querySelector(`[data-testimonial-initials-manual="${i}"]`);
    authorInput.addEventListener('input', () => {
      if (manualInput.value !== 'true') {
        initialsInput.value = initialsFromName(authorInput.value);
      }
    });
    initialsInput.addEventListener('input', () => {
      manualInput.value = 'true';
    });
  };

  window.addAccordionItem = function(data = {}) {
    const c = document.getElementById('accordionContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Eintrag ${i+1} <button type="button" class="remove" data-confirm-remove="Eintrag wirklich entfernen?" data-remove-target=".repeat-item" data-min-siblings="1">Entfernen</button></h5>
      <label><span>Frage / Titel</span><input type="text" name="items[${i}][title]" value="${esc(data.title||'')}" class="w-full"></label>
      <label><span>Antwort / Text</span><textarea name="items[${i}][body]" class="w-full" rows="3">${esc(data.body||'')}</textarea></label>`;
    c.appendChild(d);
  };

  window.addTabItem = function(data = {}) {
    const c = document.getElementById('tabsContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Tab ${i+1} <button type="button" class="remove" data-confirm-remove="Tab wirklich entfernen?" data-remove-target=".repeat-item" data-min-siblings="1">Entfernen</button></h5>
      <label><span>Titel</span><input type="text" name="items[${i}][title]" value="${esc(data.title||'')}" class="w-full"></label>
      <label><span>Inhalt</span><textarea name="items[${i}][content]" class="w-full" rows="4">${esc(data.content||'')}</textarea></label>`;
    c.appendChild(d);
  };

  window.addTimelineItem = function(data = {}) {
    const c = document.getElementById('timelineContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Eintrag ${i+1} <button type="button" class="remove" data-confirm-remove="Eintrag wirklich entfernen?" data-remove-target=".repeat-item" data-min-siblings="1">Entfernen</button></h5>
      <label><span>Datum / Jahr</span><input type="text" name="items[${i}][date]" value="${esc(data.date||'')}" class="w-full"></label>
      <label><span>Titel</span><input type="text" name="items[${i}][title]" value="${esc(data.title||'')}" class="w-full"></label>
      <label><span>Text</span><textarea name="items[${i}][body]" class="w-full" rows="2">${esc(data.body||'')}</textarea></label>`;
    c.appendChild(d);
  };

  window.addGalleryImage = function(data = {}) {
    const c = document.getElementById('galleryContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Bild ${i+1} <button type="button" class="remove" data-confirm-remove="Bild wirklich entfernen?" data-remove-target=".repeat-item">Entfernen</button></h5>
      <label><span>URL</span><div class="media-input-row"><input type="text" name="items[${i}][url]" value="${esc(data.url||'')}" class="w-full"><button type="button" class="btn secondary sm" data-gallery-media="${i}">Auswaehlen</button></div></label>
      <label><span>Alt-Text</span><input type="text" name="items[${i}][alt]" value="${esc(data.alt||'')}" class="w-full"></label>`;
    c.appendChild(d);
    d.querySelector('[data-gallery-media]')?.addEventListener('click', () => {
      openMediaPicker({
        types: 'image',
        title: 'Bild auswaehlen',
        onSelect: (media) => {
          const urlInput = d.querySelector(`[name="items[${i}][url]"]`);
          const altInput = d.querySelector(`[name="items[${i}][alt]"]`);
          if (urlInput) urlInput.value = media.url;
          if (altInput && !altInput.value) altInput.value = media.name || '';
        }
      });
    });
  };

  window.addFormField = function(data = {}) {
    const c = document.getElementById('formFieldsContainer');
    const i = c.children.length;
    const d = document.createElement('div');
    d.className = 'repeat-item';
    d.innerHTML = `<h5>Feld ${i+1} <button type="button" class="remove" data-confirm-remove="Feld wirklich entfernen?" data-remove-target=".repeat-item">Entfernen</button></h5>
      <label><span>Label</span><input type="text" name="fields[${i}][label]" value="${esc(data.label||'')}" class="w-full"></label>
      <label><span>Platzhalter</span><input type="text" name="fields[${i}][placeholder]" value="${esc(data.placeholder||'')}" class="w-full"></label>
      <label><span>Typ</span><select name="fields[${i}][type]" class="w-full">
        <option value="text" ${(data.type||'text')==='text'?'selected':''}>Text</option>
        <option value="email" ${data.type==='email'?'selected':''}>E-Mail</option>
        <option value="tel" ${data.type==='tel'?'selected':''}>Telefon</option>
        <option value="textarea" ${data.type==='textarea'?'selected':''}>Textarea</option>
      </select></label>
      <label style="display:flex;align-items:center;gap:.5rem">
        <input type="checkbox" name="fields[${i}][required]" ${data.required?'checked':''}> <span>Pflichtfeld</span>
      </label>`;
    c.appendChild(d);
  };

  // ---------------------------------------------------------------------------
  // Apply Block Settings
  // ---------------------------------------------------------------------------
  window.applyBlockSettings = function(index) {
    const form = document.getElementById('blockSettingsForm');
    const formData = new FormData(form);
    const settings = {};

    // Simple (non-bracket) fields
    for (const [key, value] of formData.entries()) {
      if (!key.includes('[')) settings[key] = value;
    }

    // Repeater fields: field[index][prop]
    const repeaterPattern = /^(\w+)\[(\d+)\]\[(\w+)\]$/;
    const repeaters = {};
    for (const [key, value] of formData.entries()) {
      const m = key.match(repeaterPattern);
      if (m) {
        const [, field, idx, prop] = m;
        if (!repeaters[field]) repeaters[field] = [];
        if (!repeaters[field][idx]) repeaters[field][idx] = {};
        // Convert lines-array textareas to real arrays
        const el = form.querySelector(`[name="${key}"]`);
        if (el && el.dataset.type === 'lines-array') {
          repeaters[field][idx][prop] = value.split('\n').map(l => l.trim()).filter(l => l);
        } else {
          repeaters[field][idx][prop] = value;
        }
      }
    }

    // Checkboxes in repeaters (unchecked ones are absent from FormData)
    form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      const m = cb.name.match(repeaterPattern);
      if (m) {
        const [, field, idx, prop] = m;
        if (!repeaters[field]) repeaters[field] = [];
        if (!repeaters[field][idx]) repeaters[field][idx] = {};
        repeaters[field][idx][prop] = cb.checked;
      }
    });

    // Convert sparse arrays to dense arrays
    for (const key in repeaters) {
      repeaters[key] = Object.values(repeaters[key]);
    }

    Object.assign(settings, repeaters);

    blocks[index].settings = settings;
    markDirty();
    document.querySelector('.block-settings-overlay')?.remove();
    renderCanvas();
  };

  // ---------------------------------------------------------------------------
  // Media Thumbnail Click
  // ---------------------------------------------------------------------------
  document.querySelectorAll('.media-thumb').forEach(thumb => {
    thumb.addEventListener('click', () => {
      const url = thumb.dataset.mediaUrl;
      if (!url) return;

      // Fill open modal image URL input if present
      const imageUrlInput = document.getElementById('imageUrlInput');
      if (imageUrlInput) imageUrlInput.value = url;

      // Set image block url directly when selected
      if (selectedBlockIndex !== null) {
        const block = blocks[selectedBlockIndex];
        if (block.type === 'image' && !imageUrlInput) {
          block.settings.url = url;
          renderCanvas();
          toast('success', 'Bild gesetzt', 'Das Bild wurde zum Block hinzugefügt.');
        }
      }
    });
  });

  // ---------------------------------------------------------------------------
  // Slug Preview  (IDs match editor.php: slug_part, parent_id, slugFullPreview)
  // ---------------------------------------------------------------------------
  async function uploadEditorMedia(file) {
    if (!mediaUploadForm || !file) return;
    const data = new FormData(mediaUploadForm);
    data.set('file', file);
    data.set('ajax', '1');
    try {
      const res = await fetch(mediaUploadForm.action, { method: 'POST', body: data });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Upload fehlgeschlagen');
      addMediaItem({ id: json.id, url: json.url, mime: json.mime, name: json.name, size: json.size || '' });
      toast('success', 'Datei hochgeladen', json.name || 'Die Datei ist in der Mediathek.');
    } catch (error) {
      toast('error', 'Upload fehlgeschlagen', error.message || 'Datei konnte nicht hochgeladen werden.');
    } finally {
      if (mediaFileInput) mediaFileInput.value = '';
    }
  }

  if (mediaFileInput) {
    mediaFileInput.addEventListener('change', (event) => {
      event.preventDefault();
      event.stopImmediatePropagation();
      const file = mediaFileInput.files?.[0];
      if (file) uploadEditorMedia(file);
    }, true);
  }

  if (mediaUploadForm) {
    mediaUploadForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const file = mediaFileInput?.files?.[0];
      if (file) uploadEditorMedia(file);
    });
  }

  document.addEventListener('click', (event) => {
    const thumb = event.target.closest('#editorMediaGrid .media-thumb');
    if (!thumb) return;
    const url = thumb.dataset.mediaUrl;
    const mime = thumb.dataset.mediaMime || '';
    if (!url || selectedBlockIndex === null) return;
    const block = blocks[selectedBlockIndex];
    if (!block) return;
    if (block.type === 'image' && mime.startsWith('image/')) {
      block.settings.url = url;
    } else if (block.type === 'video' && mime.startsWith('video/')) {
      block.settings.url = url;
    } else {
      return;
    }
    markDirty();
    renderCanvas();
    toast('success', 'Medium gesetzt', 'Die Auswahl wurde in den Block uebernommen.');
  });

  const titleInput    = document.getElementById('title');
  const slugPartInput = document.getElementById('slug_part');
  const parentSelect  = document.getElementById('parent_id');
  const slugPreview   = document.getElementById('slugFullPreview');

  function updateSlugPreview() {
    if (!slugPreview || !slugPartInput) return;
    let parentSlug = '';
    if (parentSelect && parentSelect.value) {
      const opt = parentSelect.options[parentSelect.selectedIndex];
      parentSlug = opt.dataset.slug || '';
    }
    const part = slugPartInput.value.trim();
    slugPreview.textContent = (parentSlug ? parentSlug + '/' : '') + part || '/';
  }

  if (titleInput && slugPartInput) {
    titleInput.addEventListener('input', (e) => {
      if (!slugPartInput.dataset.manuallyEdited) {
        slugPartInput.value = e.target.value
          .toLowerCase()
          .replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss')
          .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
      }
      updateSlugPreview();
    });
    slugPartInput.addEventListener('input', () => {
      slugPartInput.dataset.manuallyEdited = 'true';
      updateSlugPreview();
    });
  }
  if (parentSelect) parentSelect.addEventListener('change', updateSlugPreview);
  updateSlugPreview();

  // ---------------------------------------------------------------------------
  // Form Submit: flush active contenteditable, then serialise blocks
  // ---------------------------------------------------------------------------
  pageForm.addEventListener('submit', () => {
    isDirty = false;
    const active = document.activeElement;
    if (active && active.dataset.field) {
      const blockEl = active.closest('.cms-block');
      if (blockEl) {
        const idx = parseInt(blockEl.dataset.index);
        const value = editableFieldValue(active);
        const previousValue = getFieldValue(blocks[idx].settings, active.dataset.field);
        setFieldValue(blocks[idx].settings, active.dataset.field, value);
        syncInitialsForField(idx, active.dataset.field, value, blockEl, previousValue);
      }
    }
    blocksJsonInput.value = JSON.stringify(blocks);
  });

  // ---------------------------------------------------------------------------
  // Escape closes modal
  // ---------------------------------------------------------------------------
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (document.querySelector('.cms-confirm-overlay')) return;
      document.querySelector('.block-settings-overlay')?.remove();
    }
  });

  // ---------------------------------------------------------------------------
  // Initial Render
  // ---------------------------------------------------------------------------
  renderCanvas();

})();
