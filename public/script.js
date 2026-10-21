/* Ananya Tots — site interactions (vanilla JS, no dependencies) */
(function () {
  'use strict';
  var doc = document.documentElement;
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------- mobile navigation ---------------- */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('main-nav');
  if (toggle && nav) {
    var setOpen = function (open) {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    };
    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
    });
    document.addEventListener('click', function (e) {
      if (nav.classList.contains('is-open') && !nav.contains(e.target) && !toggle.contains(e.target)) setOpen(false);
    });
    var mq = window.matchMedia('(min-width: 1024px)');
    var onChange = function () { if (mq.matches) setOpen(false); };
    if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
  }

  /* ---------------- dropdown sub-menus ---------------- */
  var subs = Array.prototype.slice.call(document.querySelectorAll('.main-nav .has-sub'));
  var closeSubs = function (except) {
    subs.forEach(function (li) {
      if (li !== except) { li.classList.remove('open'); li.querySelector('.sub-toggle').setAttribute('aria-expanded', 'false'); }
    });
  };
  subs.forEach(function (li) {
    var b = li.querySelector('.sub-toggle');
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = !li.classList.contains('open');
      closeSubs(li);
      li.classList.toggle('open', open);
      b.setAttribute('aria-expanded', String(open));
    });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.has-sub')) closeSubs(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var o = document.querySelector('.has-sub.open');
      if (o) { closeSubs(); o.querySelector('.sub-toggle').focus(); }
    }
  });

  /* ---------------- header shadow once scrolled ---------------- */
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------------- scroll reveal animations ---------------- */
  // Underline strokes ("draw") are revealed together with the heading they belong to.
  var all = Array.prototype.slice.call(document.querySelectorAll('[data-anim]'));
  var draws = all.filter(function (el) { return el.getAttribute('data-anim') === 'draw'; });
  var items = all.filter(function (el) { return el.getAttribute('data-anim') !== 'draw'; });
  var partnerDraws = function (el) {
    var out = Array.prototype.slice.call(el.querySelectorAll('[data-anim="draw"]'));
    var n = el.nextElementSibling;
    if (n && n.getAttribute('data-anim') === 'draw') out.push(n);
    return out;
  };
  var reveal = function (el) {
    if (el.classList.contains('in')) return;
    el.classList.add('in');
    partnerDraws(el).forEach(function (d) {
      d.style.setProperty('--d', el.style.getPropertyValue('--d') || '0ms');
      d.classList.add('in');
    });
    setTimeout(function () { el.classList.add('done'); }, 1600 + (parseInt(el.style.getPropertyValue('--d'), 10) || 0));
  };
  var revealAll = function () { all.forEach(function (el) { el.classList.add('in', 'done'); }); };
  if (all.length) {
    if (reduceMotion || !('IntersectionObserver' in window)) {
      revealAll();
    } else {
      var io = new IntersectionObserver(function (entries) {
        var batch = entries.filter(function (en) { return en.isIntersecting; }).map(function (en) { return en.target; });
        batch.sort(function (a, b) {
          var ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
          return (ra.top - rb.top) || (ra.left - rb.left);
        });
        batch.forEach(function (el, i) {
          if (!el.style.getPropertyValue('--d')) el.style.setProperty('--d', Math.min(i * 90, 720) + 'ms');
          reveal(el);
          io.unobserve(el);
        });
      }, { rootMargin: '0px 0px -6% 0px', threshold: 0.08 });
      items.forEach(function (el) { io.observe(el); });
      // strokes with no heading partner get their own observer (threshold 0: they start clipped)
      var io2 = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io2.unobserve(en.target); } });
      }, { threshold: 0 });
      draws.forEach(function (d) { io2.observe(d); });
      // at the very bottom of the page, show anything still waiting
      window.addEventListener('scroll', function () {
        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
          items.forEach(function (el) { if (el.getBoundingClientRect().top < window.innerHeight) reveal(el); });
        }
      }, { passive: true });
      window.addEventListener('beforeprint', revealAll);
    }
  }

  /* ---------------- FAQ accordions ---------------- */
  document.querySelectorAll('.faq').forEach(function (faq) {
    var single = faq.hasAttribute('data-single');
    faq.querySelectorAll('.faq__q').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = btn.closest('.faq__item');
        var open = btn.getAttribute('aria-expanded') !== 'true';
        if (single && open) {
          faq.querySelectorAll('.faq__item.is-open').forEach(function (other) {
            if (other !== item) {
              other.classList.remove('is-open');
              other.querySelector('.faq__q').setAttribute('aria-expanded', 'false');
            }
          });
        }
        item.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', String(open));
      });
    });
  });

  /* ---------------- tabs (WAI-ARIA pattern) ---------------- */
  document.querySelectorAll('[role="tablist"]').forEach(function (list) {
    var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
    var select = function (tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        var p = document.getElementById(t.getAttribute('aria-controls'));
        if (p && p !== document.getElementById(tab.getAttribute('aria-controls'))) p.hidden = true;
      });
      var panel = document.getElementById(tab.getAttribute('aria-controls'));
      if (panel) {
        panel.hidden = false;
        panel.setAttribute('aria-labelledby', tab.id);
        // shared panel: swap the programme-specific text and replay its entrance
        var fill = tab.getAttribute('data-fill');
        if (fill) {
          var data = JSON.parse(fill);
          Object.keys(data).forEach(function (k) {
            panel.querySelectorAll('[data-f="' + k + '"]').forEach(function (n) { n.textContent = data[k]; });
          });
          panel.classList.remove('swap'); void panel.offsetWidth; panel.classList.add('swap');
        }
      }
      if (focus) tab.focus();
    };
    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { select(tab, false); });
      tab.addEventListener('keydown', function (e) {
        var k = e.key, n = null;
        if (k === 'ArrowRight') n = tabs[(i + 1) % tabs.length];
        else if (k === 'ArrowLeft') n = tabs[(i - 1 + tabs.length) % tabs.length];
        else if (k === 'Home') n = tabs[0];
        else if (k === 'End') n = tabs[tabs.length - 1];
        if (n) { e.preventDefault(); select(n, true); }
      });
    });
  });

  /* ---------------- enquiry / application forms ---------------- */
  // Client-side checks for instant feedback; the server (Laravel) validates again.
  document.querySelectorAll('form.form').forEach(function (form) {
    form.querySelectorAll('select').forEach(function (s) {
      var upd = function () { s.classList.toggle('has-value', !!s.value); };
      s.addEventListener('change', upd); upd();
    });
    // ?type=visit pre-selects "Arrange a Visit"
    if (/[?&]type=visit/.test(location.search)) {
      var v = form.querySelector('input[value="visit"]'); if (v) v.checked = true;
    }
    var status = form.querySelector('.form-status');
    var clearErrors = function () {
      form.querySelectorAll('.cms-err').forEach(function (e) { e.remove(); });
    };
    var mark = function (f, msg) {
      f.classList.add('is-invalid');
      f.setAttribute('aria-invalid', 'true');
      if (msg) {
        var id = (f.id || f.name) + '-err';
        var span = document.createElement('span');
        span.className = 'cms-err'; span.id = id; span.textContent = msg;
        (f.type === 'checkbox' ? f.closest('label') : f).insertAdjacentElement('afterend', span);
        f.setAttribute('aria-describedby', id);
      }
    };
    form.addEventListener('submit', function (e) {
      var ok = true, first = null;
      clearErrors();
      form.querySelectorAll('[required]').forEach(function (f) {
        var bad = f.type === 'checkbox' ? !f.checked : (f.type === 'file' ? !f.files.length : !f.value.trim());
        if (f.type === 'tel' && f.value.trim() && !/^[+\d][\d\s()-]{6,}$/.test(f.value.trim())) bad = true;
        if (f.type === 'email' && f.value.trim() && !/^\S+@\S+\.\S+$/.test(f.value.trim())) bad = true;
        f.classList.toggle('is-invalid', bad);
        f.setAttribute('aria-invalid', String(bad));
        if (bad) { ok = false; if (!first) first = f; }
      });
      if (!ok) {
        e.preventDefault();
        if (status) status.textContent = 'Please fill in the highlighted fields.';
        if (first) first.focus();
        return;
      }
      if (!form.dataset.ajax || !window.fetch || !window.FormData) return; // normal POST
      e.preventDefault();
      var btn = form.querySelector('[type="submit"]');
      if (btn) btn.disabled = true;
      if (status) status.textContent = 'Sending…';
      fetch(form.action, {
        method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) { return { res: res, data: data }; });
      }).then(function (r) {
        if (r.res.ok) {
          if (status) status.textContent = r.data.message || 'Thank you! Our team will be in touch soon.';
          form.reset();
          form.querySelectorAll('select').forEach(function (s) { s.classList.remove('has-value'); });
          form.querySelectorAll('[aria-invalid]').forEach(function (f) { f.removeAttribute('aria-invalid'); });
        } else if (r.res.status === 422 && r.data.errors) {
          var firstBad = null;
          Object.keys(r.data.errors).forEach(function (name) {
            var f = form.querySelector('[name="' + name + '"]');
            if (f) { mark(f, r.data.errors[name][0]); if (!firstBad) firstBad = f; }
          });
          if (status) status.textContent = 'Please fill in the highlighted fields.';
          if (firstBad) firstBad.focus();
        } else if (r.res.status === 419) {
          if (status) status.textContent = 'Your session has expired. Please reload the page and try again.';
        } else if (r.res.status === 429) {
          if (status) status.textContent = 'Too many attempts. Please wait a minute and try again.';
        } else {
          if (status) status.textContent = 'Sorry, something went wrong. Please try again or call us.';
        }
      }).catch(function () {
        if (status) status.textContent = 'Sorry, we could not send your message. Please check your connection and try again.';
      }).then(function () { if (btn) btn.disabled = false; });
    });
    form.querySelectorAll('input,select,textarea').forEach(function (f) {
      f.addEventListener('input', function () {
        f.classList.remove('is-invalid'); f.removeAttribute('aria-invalid');
        var n = f.nextElementSibling; if (n && n.classList.contains('cms-err')) n.remove();
      });
    });
  });
})();
