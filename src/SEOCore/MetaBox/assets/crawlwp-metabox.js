(function($){
  'use strict';

  var mb = document.getElementById('crawlwp-seo-metabox-inner');
  if (!mb) return;

  var TOKENS = {
    '%%title%%':       crawlwpSEO.postTitle || '',
    '%%sitename%%':    crawlwpSEO.siteName || '',
    '%%sep%%':         crawlwpSEO.separator || '—',
    '%%category%%':    crawlwpSEO.category || '',
    '%%excerpt%%':     crawlwpSEO.excerpt || '',
    '%%currentyear%%': crawlwpSEO.currentYear || '',
    '%%author%%':      crawlwpSEO.author || ''
  };

  function resolve(str) {
    return str.replace(/%%[a-z]+%%/g, function(m) {
      return TOKENS[m] !== undefined ? TOKENS[m] : m;
    });
  }

  /* ---------- tabs ---------- */
  mb.querySelectorAll('.cwp-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      mb.querySelectorAll('.cwp-tab').forEach(function(t) {
        t.classList.remove('is-active');
        t.setAttribute('aria-selected', 'false');
      });
      mb.querySelectorAll('.cwp-panel').forEach(function(p) { p.classList.remove('is-active'); });
      tab.classList.add('is-active');
      tab.setAttribute('aria-selected', 'true');
      var panel = document.getElementById('cwp-panel-' + tab.dataset.panel);
      if (panel) panel.classList.add('is-active');
    });
  });

  /* ---------- social sub-tabs ---------- */
  mb.querySelectorAll('.cwp-social-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      mb.querySelectorAll('.cwp-social-tab').forEach(function(t) { t.classList.remove('is-active'); });
      mb.querySelectorAll('.cwp-social-panel').forEach(function(p) { p.classList.remove('is-active'); });
      tab.classList.add('is-active');
      document.getElementById('cwp-social-' + tab.dataset.social).classList.add('is-active');
    });
  });

  /* ---------- device toggle ---------- */
  var serp = document.getElementById('cwpSerp');
  var deviceSeg = document.getElementById('cwpDeviceSeg');
  if (deviceSeg) {
    deviceSeg.addEventListener('click', function(e) {
      var btn = e.target.closest('button');
      if (!btn) return;
      this.querySelectorAll('button').forEach(function(b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      serp.classList.toggle('is-mobile', btn.dataset.device === 'mobile');
      measureAll();
    });
  }

  /* ---------- variable menus ---------- */
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.cwp-var-btn');
    document.querySelectorAll('.cwp-var-menu').forEach(function(m) {
      if (!btn || m.previousElementSibling !== btn) m.classList.remove('is-open');
    });
    if (btn) btn.nextElementSibling.classList.toggle('is-open');

    var item = e.target.closest('.cwp-var-item');
    if (item) {
      var menu = item.closest('.cwp-var-menu');
      var target = document.getElementById(menu.previousElementSibling.dataset.varFor);
      var start = target.selectionStart || target.value.length;
      target.value = target.value.slice(0, start) + item.dataset.token + target.value.slice(target.selectionEnd || start);
      target.focus();
      target.selectionStart = target.selectionEnd = start + item.dataset.token.length;
      menu.classList.remove('is-open');
      target.dispatchEvent(new Event('input'));
    }
  });

  /* ---------- pixel measurement ---------- */
  var ctx = document.createElement('canvas').getContext('2d');
  function widthOf(text, font) { ctx.font = font; return Math.round(ctx.measureText(text).width); }

  function measure(el) {
    var text  = resolve(el.value);
    var limit = parseInt(el.dataset.limit, 10);
    var px    = widthOf(text, el.dataset.font);
    var pct   = Math.min(100, Math.round(px / limit * 100));
    var fill  = document.getElementById(el.dataset.meter + 'Fill');
    var label = document.getElementById(el.dataset.meter);

    fill.style.width = pct + '%';
    fill.classList.remove('is-good', 'is-over');
    var state = 'short';
    if (px > limit) { fill.classList.add('is-over'); state = 'over'; }
    else if (pct >= 70) { fill.classList.add('is-good'); state = 'good'; }

    var words = { short: 'Too short', good: 'Good length', over: 'Will be cut off' }[state];
    label.innerHTML = '<b>' + words + '</b> · ' + px + ' / ' + limit + ' px · ' + text.length + ' chars';
  }

  function measureAll() {
    mb.querySelectorAll('[data-meter]').forEach(measure);
  }

  /* ---------- live preview binding ---------- */
  var title = document.getElementById('cwpTitle');
  var desc  = document.getElementById('cwpDesc');
  var slug  = document.getElementById('cwpSlug');

  var fbSync  = document.getElementById('cwpFbSync');
  var fbTitle = document.getElementById('cwpFbTitle');
  var fbDesc  = document.getElementById('cwpFbDesc');
  var xSync   = document.getElementById('cwpXSync');
  var xTitle  = document.getElementById('cwpXTitle');
  var xDesc   = document.getElementById('cwpXDesc');

  function sync() {
    var t = resolve(title.value) || crawlwpSEO.postTitle || 'Enter a title';
    var d = resolve(desc.value)  || crawlwpSEO.excerpt || 'Add a meta description to control what appears here.';

    document.getElementById('cwpSerpTitle').textContent = t;
    document.getElementById('cwpSerpDesc').textContent  = d;

    var urlParts = crawlwpSEO.siteUrl.replace(/^https?:\/\//, '').replace(/\/$/, '');
    document.getElementById('cwpSerpUrl').textContent = urlParts + ' › ' + (slug.value || '…');
    document.getElementById('cwpSlugEcho').textContent = slug.value || '…';

    /* social mirrors */
    var fbT = fbSync.checked ? t : (fbTitle.value || t);
    var fbD = fbSync.checked ? d : (fbDesc.value  || d);
    document.getElementById('cwpFbTitlePrev').textContent = fbT;
    document.getElementById('cwpFbDescPrev').textContent  = fbD;

    document.getElementById('cwpXTitlePrev').textContent = xSync.checked ? fbT : (xTitle.value || fbT);
    document.getElementById('cwpXDescPrev').textContent  = xSync.checked ? fbD : (xDesc.value  || fbD);
  }

  function toggleSync() {
    fbTitle.disabled = fbDesc.disabled = fbSync.checked;
    xTitle.disabled  = xDesc.disabled  = xSync.checked;
    sync();
  }

  [fbSync, xSync].forEach(function(el) { el.addEventListener('change', toggleSync); });
  [fbTitle, fbDesc, xTitle, xDesc].forEach(function(el) { el.addEventListener('input', sync); });

  [title, desc].forEach(function(el) {
    el.addEventListener('input', function() { measure(el); sync(); });
  });
  slug.addEventListener('input', sync);

  /* ---------- JSON-LD toggle ---------- */
  var jsonBtn = document.getElementById('cwpJsonToggle');
  var jsonPre = document.getElementById('cwpJson');
  if (jsonBtn && jsonPre) {
    jsonBtn.addEventListener('click', function() {
      var open = jsonPre.hasAttribute('hidden');
      jsonPre.toggleAttribute('hidden', !open);
      jsonBtn.textContent = open ? 'Hide JSON-LD' : 'Show JSON-LD';
    });
  }

  /* ---------- schema type change updates preview ---------- */
  var schemaType = document.getElementById('cwpSchema');
  if (schemaType) {
    schemaType.addEventListener('change', function() {
      updateSchemaPreview();
    });
  }
  var schemaHeadline = document.getElementById('cwpHeadline');
  var schemaSection  = document.getElementById('cwpSection');
  if (schemaHeadline) schemaHeadline.addEventListener('input', updateSchemaPreview);
  if (schemaSection) schemaSection.addEventListener('input', updateSchemaPreview);

  function updateSchemaPreview() {
    if (!jsonPre) return;
    var type = schemaType ? schemaType.value : 'Article';
    if (type === 'None — output nothing') {
      jsonPre.textContent = '// No structured data will be output for this post.';
      return;
    }
    var headline = (schemaHeadline && schemaHeadline.value) || resolve(title.value) || crawlwpSEO.postTitle;
    var section  = schemaSection ? schemaSection.value : '';
    var schema = {
      '@context': 'https://schema.org',
      '@type': type,
      'headline': headline
    };
    if (section) schema.articleSection = section;
    schema.author = { '@type': 'Person', 'name': crawlwpSEO.author || '' };
    schema.datePublished = new Date().toISOString().slice(0, 10);
    jsonPre.textContent = JSON.stringify(schema, null, 2);
  }

  /* ---------- image pickers (WP media) ---------- */
  mb.querySelectorAll('.cwp-img-pick-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var target   = btn.dataset.target;
      var inputEl  = document.getElementById(target);
      var thumbEl  = btn.closest('.cwp-img-picker').querySelector('.cwp-img-thumb');

      var frame = wp.media({
        title: 'Select Image',
        multiple: false,
        library: { type: 'image' }
      });

      frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        inputEl.value = attachment.id;
        if (thumbEl && attachment.url) {
          thumbEl.style.backgroundImage = 'url(' + attachment.url + ')';
        }
      });

      frame.open();
    });
  });

  mb.querySelectorAll('.cwp-img-remove-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      var target  = btn.dataset.target;
      var inputEl = document.getElementById(target);
      var thumbEl = btn.closest('.cwp-img-picker').querySelector('.cwp-img-thumb');
      inputEl.value = '';
      if (thumbEl) {
        thumbEl.style.backgroundImage = '';
      }
    });
  });

  /* ---------- init ---------- */
  measureAll();
  sync();
  toggleSync();
  updateSchemaPreview();

})(jQuery);
