'use strict';
{
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];

  // Mobile menu.
  const toggle = $('.nav-toggle');
  const nav = document.getElementById('nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  // Folding menus on small screens.
  for (const b of $$('.menu-toggle')) {
    const body = document.getElementById(b.getAttribute('aria-controls'));
    if (!body) continue;
    b.addEventListener('click', () => {
      const open = body.classList.toggle('open');
      b.setAttribute('aria-expanded', String(open));
    });
  }

  // Label every table cell with its column heading, so narrow screens can show
  // each row as a stacked card instead of scrolling sideways.
  for (const table of $$('.table-wrap table')) {
    const heads = $$('thead th', table).map((th) => (th.innerText || th.textContent).trim().split('\n')[0]);
    for (const row of $$('tbody tr, tfoot tr', table)) {
      let col = 0;
      for (const cell of row.children) {
        if (cell.tagName === 'TD' && !cell.hasAttribute('data-label')) cell.setAttribute('data-label', cell.colSpan > 1 ? '' : (heads[col] || ''));
        col += cell.colSpan || 1;
      }
    }
  }

  // In-app confirmation for consequential actions.
  let dialog = null;
  const sure = (question) => new Promise((resolve) => {
    if (typeof HTMLDialogElement !== 'function') { resolve(window.confirm(question)); return; } // very old browsers only
    if (!dialog) {
      dialog = document.createElement('dialog');
      dialog.setAttribute('role', 'alertdialog');
      dialog.className = 'sure';
      const p = document.createElement('p');
      const row = document.createElement('div');
      row.className = 'actions';
      const no = document.createElement('button');
      no.type = 'button'; no.className = 'btn ghost small'; no.textContent = 'Cancel'; no.value = 'no';
      const yes = document.createElement('button');
      yes.type = 'button'; yes.className = 'btn small'; yes.textContent = 'Continue'; yes.value = 'yes';
      row.append(no, yes);
      dialog.append(p, row);
      document.body.append(dialog);
      for (const b of [no, yes]) b.addEventListener('click', () => dialog.close(b.value));
    }
    dialog.querySelector('p').textContent = question;
    dialog.onclose = () => resolve(dialog.returnValue === 'yes');
    dialog.returnValue = '';
    dialog.showModal();
    dialog.querySelector('button[value="no"]').focus();
  });
  for (const form of $$('form[data-confirm]')) {
    form.addEventListener('submit', async (e) => {
      if (form.dataset.confirmed === '1') return;
      e.preventDefault();
      const submitter = e.submitter;
      if (await sure(form.dataset.confirm)) {
        form.dataset.confirmed = '1';
        if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        else form.submit(); // Safari before 16
      }
    });
  }

  // Prevent double submits.
  for (const form of $$('form[method="post"]')) {
    form.addEventListener('submit', (e) => {
      if (e.defaultPrevented) return;
      const btn = form.querySelector('button[type="submit"]:not([name])');
      if (btn) setTimeout(() => { btn.disabled = true; }, 0);
    });
  }

  // QR code for two-step sign-in, drawn as SVG from the vendored generator.
  for (const el of $$('[data-qr]')) {
    if (typeof window.qrcode !== 'function') continue;
    const qr = window.qrcode(0, 'M');
    qr.addData(el.dataset.qr);
    qr.make();
    const n = qr.getModuleCount();
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', `-4 -4 ${n + 8} ${n + 8}`);
    svg.setAttribute('shape-rendering', 'crispEdges');
    const bg = document.createElementNS(ns, 'rect');
    for (const [k, v] of [['x', -4], ['y', -4], ['width', n + 8], ['height', n + 8], ['fill', '#fff']]) bg.setAttribute(k, String(v));
    let d = '';
    for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (qr.isDark(r, c)) d += `M${c} ${r}h1v1h-1z`;
    const path = document.createElementNS(ns, 'path');
    path.setAttribute('d', d);
    path.setAttribute('fill', '#000');
    svg.append(bg, path);
    el.append(svg);
  }

  // Copy text (recovery codes).
  for (const b of $$('[data-copy]')) {
    b.addEventListener('click', async () => {
      const src = $(b.dataset.copy);
      if (!src) return;
      try { await navigator.clipboard.writeText(src.innerText); b.textContent = 'Copied'; } catch { b.textContent = 'Select and copy them'; }
    });
  }

  // Select every checkbox in a group.
  for (const all of $$('[data-check-all]')) {
    all.addEventListener('change', () => {
      for (const c of $$(`input[data-group="${CSS.escape(all.dataset.checkAll)}"]`)) c.checked = all.checked;
    });
  }

  // Filter rows as you type.
  for (const input of $$('[data-filter-rows]')) {
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      for (const row of $$(input.dataset.filterRows)) row.hidden = q !== '' && !row.dataset.perm.includes(q);
    });
  }

  // Roles matrix: show only rows where the roles differ.
  for (const sw of $$('[data-only-diff]')) {
    sw.addEventListener('change', () => {
      for (const row of $$(`${sw.dataset.onlyDiff} tbody tr[data-perm]`)) {
        const vals = $$('input[type=checkbox]', row).map((c) => c.checked);
        row.classList.toggle('same', sw.checked && vals.every((v) => v === vals[0]));
      }
    });
  }

  // Sortable tables: click a header to sort both ways; data-v overrides the cell text.
  for (const table of $$('table.sortable')) {
    $$('thead th', table).forEach((th, i) => {
      if (th.querySelector('input')) return;
      th.tabIndex = 0;
      th.classList.add('sorts');
      const sort = () => {
        const dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
        $$('thead th', table).forEach((h) => delete h.dataset.dir);
        th.dataset.dir = dir;
        const rows = $$('tbody tr', table);
        const key = (r) => (r.children[i]?.dataset.v ?? r.children[i]?.innerText ?? '').toLowerCase();
        rows.sort((a, b) => key(a).localeCompare(key(b), undefined, { numeric: true }) * (dir === 'asc' ? 1 : -1));
        $('tbody', table).append(...rows);
      };
      th.addEventListener('click', sort);
      th.addEventListener('keydown', (e) => { if (e.key === 'Enter') sort(); });
    });
  }
}
