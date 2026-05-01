// ===========================================================================
// ADMIN.JS – CMS Admin Interface Logic
// ===========================================================================

// Global Toast Function
// ---------------------------------------------------------------------------
window.toast = function(icon, title, msg) {
  const stack = document.getElementById('toastStack');
  if (!stack) return;

  const toast = document.createElement('div');
  toast.className = 'toast';
  
  const iconMap = {
    success: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
    error: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
    info: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    warning: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>'
  };

  toast.innerHTML = `
    <div class="toast-icon" style="color: var(--${icon === 'error' ? 'danger' : icon === 'warning' ? 'warning' : icon === 'info' ? 'info' : 'success'})">
      ${iconMap[icon] || iconMap.info}
    </div>
    <div class="toast-content">
      <strong>${title}</strong>
      ${msg ? `<p>${msg}</p>` : ''}
    </div>
    <button class="toast-dismiss" onclick="this.parentElement.remove()">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  `;

  stack.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
};

// Custom Confirm Dialog
// ---------------------------------------------------------------------------
window.cmsConfirm = function(options) {
  const config = typeof options === 'string' ? { message: options } : (options || {});
  const title = config.title || 'Aktion bestaetigen';
  const message = config.message || 'Diese Aktion wirklich ausfuehren?';
  const confirmText = config.confirmText || 'Bestaetigen';
  const cancelText = config.cancelText || 'Abbrechen';
  const danger = config.danger !== false;

  document.querySelector('.cms-confirm-overlay')?.remove();

  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'cms-confirm-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.innerHTML = `
      <div class="cms-confirm-dialog">
        <div class="cms-confirm-icon" aria-hidden="true">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          </svg>
        </div>
        <div class="cms-confirm-content">
          <h3>${escapeHtml(title)}</h3>
          <p>${escapeHtml(message)}</p>
        </div>
        <div class="cms-confirm-actions">
          <button type="button" class="btn btn-secondary" data-confirm-cancel>${escapeHtml(cancelText)}</button>
          <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-confirm-ok>${escapeHtml(confirmText)}</button>
        </div>
      </div>`;

    const close = (result) => {
      document.removeEventListener('keydown', onKeydown);
      overlay.remove();
      resolve(result);
    };
    const onKeydown = (event) => {
      if (event.key === 'Escape') close(false);
      if (event.key === 'Enter') close(true);
    };

    overlay.addEventListener('click', (event) => {
      if (event.target === overlay || event.target.closest('[data-confirm-cancel]')) close(false);
      if (event.target.closest('[data-confirm-ok]')) close(true);
    });
    document.addEventListener('keydown', onKeydown);
    document.body.appendChild(overlay);
    overlay.querySelector('[data-confirm-cancel]')?.focus();
  });
};

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

document.addEventListener('submit', async (event) => {
  const form = event.target.closest('form[data-confirm]');
  if (!form || form.dataset.confirmed === 'true') return;

  event.preventDefault();
  const ok = await window.cmsConfirm({
    title: form.dataset.confirmTitle || 'Loeschen bestaetigen',
    message: form.dataset.confirm,
    confirmText: form.dataset.confirmOk || 'Loeschen',
    cancelText: form.dataset.confirmCancel || 'Abbrechen',
    danger: form.dataset.confirmDanger !== 'false'
  });

  if (ok) {
    form.dataset.confirmed = 'true';
    if (event.submitter) {
      form.requestSubmit(event.submitter);
    } else {
      form.requestSubmit();
    }
  }
}, true);

document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-confirm-remove]');
  if (!button) return;

  event.preventDefault();
  const target = button.closest(button.dataset.removeTarget || '.repeat-item');
  if (!target) return;
  const minSiblings = parseInt(button.dataset.minSiblings || '0', 10);
  if (minSiblings > 0) {
    const siblings = target.parentElement ? Array.from(target.parentElement.children).filter(el => el.matches(button.dataset.removeTarget || '.repeat-item')) : [];
    if (siblings.length <= minSiblings) return;
  }

  const ok = await window.cmsConfirm({
    title: button.dataset.confirmTitle || 'Element entfernen',
    message: button.dataset.confirmRemove || 'Element wirklich entfernen?',
    confirmText: button.dataset.confirmOk || 'Entfernen',
    cancelText: button.dataset.confirmCancel || 'Abbrechen',
    danger: true
  });

  if (ok) target.remove();
});


// Mobile Sidebar Toggle
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.sidebar-toggle');
  const sidebar = document.querySelector('.cms-sidebar');
  
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('open') && 
          !sidebar.contains(e.target) && 
          !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }
});


// Pages Table Initialization
// ---------------------------------------------------------------------------
window.initPagesTable = function({ csrf, moveUrl, statusUrl }) {
  const table = document.querySelector('.pages-table');
  if (!table) return;

  // Compatibility helpers: support both data-id/data-mode and data-page-id/data-move.
  const rowId = (row) => row?.dataset?.id || row?.dataset?.pageId || '';
  const rowById = (id) => table.querySelector(`tr[data-id="${id}"], tr[data-page-id="${id}"]`);
  const btnMode = (btn) => btn?.dataset?.mode || btn?.dataset?.move || '';
  const normalizeMoveMode = (mode) => mode === 'indent' ? 'right' : (mode === 'outdent' ? 'left' : mode);

  const treeState = JSON.parse(localStorage.getItem('webcms-tree-open') || '{}');

  // Helper: Get all descendant rows
  function getDescendants(row) {
    const descendants = [];
    const level = parseInt(row.dataset.level);
    let next = row.nextElementSibling;

    while (next && parseInt(next.dataset.level) > level) {
      descendants.push(next);
      next = next.nextElementSibling;
    }

    return descendants;
  }

  // Helper: Update visibility based on tree state
  function updateTreeVisibility() {
    table.querySelectorAll('tbody tr').forEach(row => {
      const parentId = row.dataset.parentId;
      if (parentId && parentId !== '0') {
        const parentRow = rowById(parentId);
        if (parentRow) {
          const isOpen = treeState[parentId] === true;
          row.classList.toggle('page-tree-hidden', !isOpen);
        }
      }
    });
  }

  // Tree Toggle Buttons
  table.querySelectorAll('.tree-toggle').forEach(btn => {
    const row = btn.closest('tr');
    const id = rowId(row);
    const descendants = getDescendants(row);

    if (descendants.length === 0) {
      btn.style.visibility = 'hidden';
    } else {
      btn.classList.toggle('open', treeState[id] === true);
      btn.setAttribute('aria-expanded', String(treeState[id] === true));
    }

    btn.addEventListener('click', () => {
      const isOpen = btn.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(isOpen));
      treeState[id] = isOpen;
      localStorage.setItem('webcms-tree-open', JSON.stringify(treeState));
      updateTreeVisibility();
    });
  });

  updateTreeVisibility();

  // Expand All / Collapse All
  window.expandAll = function() {
    table.querySelectorAll('.tree-toggle').forEach(btn => {
      const row = btn.closest('tr');
      const id = rowId(row);
      btn.classList.add('open');
      btn.setAttribute('aria-expanded', 'true');
      treeState[id] = true;
    });
    localStorage.setItem('webcms-tree-open', JSON.stringify(treeState));
    updateTreeVisibility();
  };

  window.collapseAll = function() {
    table.querySelectorAll('.tree-toggle').forEach(btn => {
      const row = btn.closest('tr');
      const id = rowId(row);
      btn.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
      treeState[id] = false;
    });
    localStorage.setItem('webcms-tree-open', JSON.stringify(treeState));
    updateTreeVisibility();
  };

  // Filter Pages
  window.filterPages = function(value) {
    const search = value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      const title = row.querySelector('.page-title-text')?.textContent.toLowerCase() || '';
      const slug = row.querySelector('.page-slug-text')?.textContent.toLowerCase() || '';
      const matches = title.includes(search) || slug.includes(search);
      row.style.display = matches ? '' : 'none';
    });
  };

  // Status Badge Click
  table.querySelectorAll('.badge[data-status]').forEach(badge => {
    badge.style.cursor = 'pointer';
    badge.addEventListener('click', async () => {
      const row = badge.closest('tr');
      const id = rowId(row);
      const currentStatus = badge.dataset.status;
      const newStatus = currentStatus === 'published' ? 'draft' : 'published';

      try {
        const res = await fetch(statusUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `_csrf=${csrf}&id=${id}&status=${newStatus}`
        });

        const data = await res.json();
        if (data.ok) {
          badge.dataset.status = newStatus;
          badge.textContent = newStatus === 'published' ? 'Veröffentlicht' : 'Entwurf';
          badge.className = newStatus === 'published' ? 'badge page-status-toggle badge-success' : 'badge page-status-toggle badge-warning';
          toast('success', 'Status geändert', `Seite ist jetzt ${newStatus === 'published' ? 'veröffentlicht' : 'Entwurf'}`);
        } else {
          toast('error', 'Fehler', data.message || 'Status konnte nicht geändert werden');
        }
      } catch (err) {
        toast('error', 'Fehler', 'Netzwerkfehler');
      }
    });
  });

  // Move Buttons
  table.querySelectorAll('.move-controls button').forEach(btn => {
    btn.addEventListener('click', async () => {
      let mode = normalizeMoveMode(btnMode(btn));
      const row = btn.closest('tr');
      const id = rowId(row);
      let targetId = null;

      if (mode === 'up') {
        let prev = row.previousElementSibling;
        while (prev && prev.classList.contains('page-tree-hidden')) {
          prev = prev.previousElementSibling;
        }
        if (prev) targetId = rowId(prev);
      } else if (mode === 'down') {
        let next = row.nextElementSibling;
        while (next && next.classList.contains('page-tree-hidden')) {
          next = next.nextElementSibling;
        }
        if (next) targetId = rowId(next);
      } else if (mode === 'left') {
        const parentId = row.dataset.parentId;
        if (parentId && parentId !== '0') {
          const parentRow = rowById(parentId);
          if (parentRow) targetId = rowId(parentRow);
        }
      } else if (mode === 'right') {
        let prev = row.previousElementSibling;
        while (prev && prev.classList.contains('page-tree-hidden')) {
          prev = prev.previousElementSibling;
        }
        if (prev) targetId = rowId(prev);
      }

      if (!mode || !id || !targetId) return;

      try {
        const moveMode = mode === 'up' ? 'before' : mode === 'down' ? 'after' : mode === 'right' ? 'inside' : 'before';
        const res = await fetch(moveUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `_csrf=${encodeURIComponent(csrf)}&moved_id=${parseInt(id)}&target_id=${parseInt(targetId)}&mode=${moveMode}`
        });

        const data = await res.json();
        if (data.ok) {
          location.reload();
        } else {
          toast('error', 'Fehler', data.message || 'Seite konnte nicht verschoben werden');
        }
      } catch (err) {
        toast('error', 'Fehler', 'Netzwerkfehler');
      }
    });
  });

  // Drag and Drop
  let draggedRow = null;
  let dragPreview = document.getElementById('dragPreview');
  
  if (!dragPreview) {
    dragPreview = document.createElement('div');
    dragPreview.id = 'dragPreview';
    document.body.appendChild(dragPreview);
  }

  table.querySelectorAll('.drag-handle').forEach(handle => {
    handle.addEventListener('pointerdown', (e) => {
      e.preventDefault();
      draggedRow = handle.closest('tr');
      const title = draggedRow.querySelector('.page-title-text').textContent;

      dragPreview.textContent = title;
      dragPreview.style.display = 'block';
      dragPreview.style.left = e.clientX + 10 + 'px';
      dragPreview.style.top = e.clientY + 10 + 'px';

      draggedRow.classList.add('drag-origin');

      document.addEventListener('pointermove', onPointerMove);
      document.addEventListener('pointerup', onPointerUp);
      document.addEventListener('pointercancel', onPointerUp);
    });
  });

  function onPointerMove(e) {
    if (!draggedRow) return;

    dragPreview.style.left = e.clientX + 10 + 'px';
    dragPreview.style.top = e.clientY + 10 + 'px';

    dragPreview.style.pointerEvents = 'none';
    const elem = document.elementFromPoint(e.clientX, e.clientY);
    dragPreview.style.pointerEvents = '';

    table.querySelectorAll('tbody tr').forEach(r => {
      r.classList.remove('drop-before', 'drop-after', 'drop-inside');
    });

    if (!elem) return;

    const targetRow = elem.closest('tbody tr');
    if (!targetRow || targetRow === draggedRow) return;

    const rect = targetRow.getBoundingClientRect();
    const ratio = (e.clientY - rect.top) / rect.height;

    if (ratio < 0.28) {
      targetRow.classList.add('drop-before');
    } else if (ratio > 0.72) {
      targetRow.classList.add('drop-after');
    } else {
      targetRow.classList.add('drop-inside');
    }
  }

  async function onPointerUp(e) {
    if (!draggedRow) return;

    document.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerup', onPointerUp);
    document.removeEventListener('pointercancel', onPointerUp);

    dragPreview.style.display = 'none';
    draggedRow.classList.remove('drag-origin');

    const dropTarget = table.querySelector('.drop-before, .drop-after, .drop-inside');
    
    if (dropTarget && dropTarget !== draggedRow) {
      const mode = dropTarget.classList.contains('drop-before') ? 'before' :
                   dropTarget.classList.contains('drop-after') ? 'after' : 'inside';

      const movedId = parseInt(rowId(draggedRow));
      const targetId = parseInt(rowId(dropTarget));

      try {
        const res = await fetch(moveUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `_csrf=${encodeURIComponent(csrf)}&moved_id=${movedId}&target_id=${targetId}&mode=${mode}`
        });

        const data = await res.json();
        if (data.ok) {
          location.reload();
        } else {
          toast('error', 'Fehler', data.message || 'Seite konnte nicht verschoben werden');
        }
      } catch (err) {
        toast('error', 'Fehler', 'Netzwerkfehler');
      }
    }

    table.querySelectorAll('tbody tr').forEach(r => {
      r.classList.remove('drop-before', 'drop-after', 'drop-inside');
    });

    draggedRow = null;
  }
};


// Media Upload Drop Zone
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  const uploadZone = document.querySelector('.upload-zone');
  const fileInput = document.getElementById('mediaFile');

  if (uploadZone && fileInput) {
    uploadZone.addEventListener('click', () => fileInput.click());

    uploadZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      uploadZone.classList.add('drag-over');
    });

    uploadZone.addEventListener('dragleave', () => {
      uploadZone.classList.remove('drag-over');
    });

    uploadZone.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadZone.classList.remove('drag-over');
      
      if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        fileInput.closest('form').submit();
      }
    });

    fileInput.addEventListener('change', () => {
      if (fileInput.files.length > 0) {
        fileInput.closest('form').submit();
      }
    });
  }
});
