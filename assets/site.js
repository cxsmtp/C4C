{
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.getElementById('nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  for (const y of document.querySelectorAll('[data-year]')) y.textContent = new Date().getFullYear();

  // Contact form. Set data-endpoint on the <form> (for example a Formspree URL)
  // to deliver submissions. Without one, the visitor gets a ready-made email instead.
  const form = document.getElementById('contact-form');
  if (form) {
    const done = document.getElementById('form-done');
    const err = document.getElementById('form-err');
    const mail = document.getElementById('mail-link');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      err.textContent = '';
      const d = new FormData(form);
      const need = ['name', 'email', 'topic', 'message'];
      const missing = need.filter((k) => !String(d.get(k) || '').trim());
      if (missing.length) { err.textContent = 'Please fill in your name, work email, topic and a short message.'; return; }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(d.get('email')))) { err.textContent = 'Please check the email address.'; return; }
      if (!d.get('consent')) { err.textContent = 'Please agree to the privacy notice so we can reply.'; return; }
      const endpoint = form.dataset.endpoint;
      let sent = false;
      if (endpoint) {
        try {
          const r = await fetch(endpoint, { method: 'POST', body: d, headers: { Accept: 'application/json' } });
          sent = r.ok;
        } catch (_) { sent = false; }
      }
      const body = `Name: ${d.get('name')}\nCompany: ${d.get('company') || ''}\nEmail: ${d.get('email')}\nTopic: ${d.get('topic')}\nTimeline: ${d.get('timeline') || ''}\n\n${d.get('message')}`;
      mail.href = `mailto:${form.dataset.to}?subject=${encodeURIComponent('Enquiry: ' + d.get('topic'))}&body=${encodeURIComponent(body)}`;
      document.getElementById('done-sent').hidden = !sent;
      document.getElementById('done-mail').hidden = sent;
      form.hidden = true;
      done.classList.add('show');
      done.focus();
    });
  }
}
