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

  /* ---------- sync WP post title → metabox ---------- */
  function watchPostTitle() {
    /* Classic editor */
    var wpTitle = document.getElementById('title');
    if (wpTitle) {
      wpTitle.addEventListener('input', function() {
        TOKENS['%%title%%'] = wpTitle.value;
        measureAll();
        sync();
        runAnalysis();
      });
    }
    /* Gutenberg: subscribe to title changes */
    if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
      var lastTitle = TOKENS['%%title%%'];
      wp.data.subscribe(function() {
        var newTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
        if (newTitle !== undefined && newTitle !== lastTitle) {
          lastTitle = newTitle;
          TOKENS['%%title%%'] = newTitle;
          measureAll();
          sync();
          runAnalysis();
        }
      });
    }
  }

  /* ---------- two-way slug sync ---------- */
  var _slugSyncLock = false;

  function watchPostSlug() {
    /* Classic editor: #post_name or the editable slug span */
    var wpSlugInput = document.getElementById('post_name');
    if (wpSlugInput) {
      new MutationObserver(function() {
        if (_slugSyncLock) return;
        var val = wpSlugInput.value;
        if (val && val !== slug.value) {
          slug.value = val;
          sync();
          runAnalysis();
        }
      }).observe(wpSlugInput, { attributes: true, attributeFilter: ['value'] });
      wpSlugInput.addEventListener('change', function() {
        if (_slugSyncLock) return;
        if (wpSlugInput.value && wpSlugInput.value !== slug.value) {
          slug.value = wpSlugInput.value;
          sync();
          runAnalysis();
        }
      });
    }
    /* Classic editor editable slug span */
    var editSlug = document.getElementById('editable-post-name');
    if (editSlug) {
      new MutationObserver(function() {
        if (_slugSyncLock) return;
        var val = editSlug.textContent.trim();
        if (val && val !== slug.value) {
          slug.value = val;
          sync();
          runAnalysis();
        }
      }).observe(editSlug, { childList: true, characterData: true, subtree: true });
    }
    /* Gutenberg: subscribe to slug changes */
    if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
      var lastSlug = slug.value;
      wp.data.subscribe(function() {
        if (_slugSyncLock) return;
        var sel = wp.data.select('core/editor');
        var newSlug = sel.getEditedPostAttribute('slug');
        if (newSlug !== undefined && newSlug !== lastSlug) {
          lastSlug = newSlug;
          slug.value = newSlug;
          sync();
          runAnalysis();
        }
      });
    }
  }

  /* Push metabox slug → WP post slug */
  function pushSlugToWP(val) {
    _slugSyncLock = true;
    /* Classic editor */
    var wpSlugInput = document.getElementById('post_name');
    if (wpSlugInput) {
      wpSlugInput.value = val;
    }
    var editSlug = document.getElementById('editable-post-name');
    if (editSlug) {
      editSlug.textContent = val;
    }
    var editSlugFull = document.getElementById('editable-post-name-full');
    if (editSlugFull) {
      editSlugFull.textContent = val;
    }
    /* Gutenberg */
    if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
      wp.data.dispatch('core/editor').editPost({ slug: val });
    }
    _slugSyncLock = false;
  }

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
  slug.addEventListener('input', function() {
    sync();
    pushSlugToWP(slug.value);
  });

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

  /* Map input IDs to their corresponding social preview card image selectors */
  function updateSocialPreviewImage(targetId, imageUrl) {
    if (targetId === 'cwpOgImage') {
      /* Update Facebook preview card image */
      var fbCard = mb.querySelector('.cwp-fb-card .cwp-og-img');
      if (fbCard) {
        if (imageUrl) {
          fbCard.style.backgroundImage = 'url(' + imageUrl + ')';
          fbCard.textContent = '';
        } else {
          var fiFb = crawlwpSEO.featuredImageUrl || '';
          if (fiFb) {
            fbCard.style.backgroundImage = 'url(' + fiFb + ')';
            fbCard.textContent = '';
          } else {
            fbCard.style.backgroundImage = '';
            fbCard.textContent = '1200 \u00d7 630';
          }
        }
      }
      /* Also update X preview if X has no custom image */
      var xInput = document.getElementById('cwpXImage');
      if (xInput && !xInput.value) {
        var xCard = mb.querySelector('.cwp-x-card .cwp-og-img');
        if (xCard) {
          if (imageUrl) {
            xCard.style.backgroundImage = 'url(' + imageUrl + ')';
            xCard.textContent = '';
          } else {
            var fiX1 = crawlwpSEO.featuredImageUrl || '';
            if (fiX1) {
              xCard.style.backgroundImage = 'url(' + fiX1 + ')';
              xCard.textContent = '';
            } else {
              xCard.style.backgroundImage = '';
              xCard.textContent = '1200 \u00d7 675';
            }
          }
        }
      }
    } else if (targetId === 'cwpXImage') {
      /* Update X preview card image */
      var xCard = mb.querySelector('.cwp-x-card .cwp-og-img');
      if (xCard) {
        if (imageUrl) {
          xCard.style.backgroundImage = 'url(' + imageUrl + ')';
          xCard.textContent = '';
        } else {
          /* Fall back to OG image or clear */
          var ogInput = document.getElementById('cwpOgImage');
          var ogThumb = ogInput ? ogInput.closest('.cwp-img-picker') : null;
          var ogBg = ogThumb ? ogThumb.querySelector('.cwp-img-thumb') : null;
          var fallback = ogBg ? ogBg.style.backgroundImage : '';
          xCard.style.backgroundImage = fallback;
          if (fallback) {
            xCard.textContent = '';
          } else {
            var fiX2 = crawlwpSEO.featuredImageUrl || '';
            if (fiX2) {
              xCard.style.backgroundImage = 'url(' + fiX2 + ')';
              xCard.textContent = '';
            } else {
              xCard.textContent = '1200 \u00d7 675';
            }
          }
        }
      }
    }
  }

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
        /* Instantly update the social preview card */
        updateSocialPreviewImage(target, attachment.url);
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
        /* Fall back to featured image or green placeholder */
        var fi = crawlwpSEO.featuredImageUrl || '';
        if (fi) {
          thumbEl.style.backgroundImage = 'url(' + fi + ')';
          thumbEl.classList.remove('cwp-thumb-empty');
        } else {
          thumbEl.style.backgroundImage = '';
          thumbEl.classList.add('cwp-thumb-empty');
        }
      }
      /* Instantly update the social preview card */
      updateSocialPreviewImage(target, '');
    });
  });

  /* ---------- links panel ---------- */
  function getEditorContent() {
    /* Try TinyMCE first, then Gutenberg, then fall back to localized content */
    if (typeof tinymce !== 'undefined') {
      var ed = tinymce.get('content');
      if (ed && !ed.isHidden()) return ed.getContent();
    }
    var wpBlock = document.querySelector('.block-editor-block-list__layout');
    if (wpBlock) return wpBlock.innerHTML;
    var ta = document.getElementById('content');
    if (ta) return ta.value;
    return crawlwpSEO.postContent || '';
  }

  function parseLinks(html) {
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var anchors = tmp.querySelectorAll('a[href]');
    var siteHost = new URL(crawlwpSEO.siteUrl).hostname;
    var internal = [], external = [];
    anchors.forEach(function(a) {
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#') return;
      var text = a.textContent.trim() || href;
      try {
        var url = new URL(href, crawlwpSEO.siteUrl);
        var item = { href: url.href, text: text };
        if (url.hostname === siteHost) {
          internal.push(item);
        } else {
          external.push(item);
        }
      } catch(e) {
        external.push({ href: href, text: text });
      }
    });
    return { internal: internal, external: external };
  }

  /* ---------- show-all toggle helpers ---------- */
  function addShowAll(container, total) {
    var btn = document.createElement('a');
    btn.href = '#';
    btn.className = 'cwp-show-all-link';
    btn.textContent = 'Show all ' + total + ' links';
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      container.querySelectorAll('.cwp-link-hidden').forEach(function(el) {
        el.classList.remove('cwp-link-hidden');
      });
      btn.remove();
    });
    container.appendChild(btn);
  }

  function removeShowAll(container) {
    var existing = container.querySelector('.cwp-show-all-link');
    if (existing) existing.remove();
  }

  function renderLinks() {
    var html = getEditorContent();
    var parsed = parseLinks(html);
    var outTotal = parsed.internal.length + parsed.external.length;
    var inbound = crawlwpSEO.inboundLinks || [];

    document.getElementById('cwpLinksOut').textContent = outTotal;
    document.getElementById('cwpLinksIn').textContent = inbound.length;
    document.getElementById('cwpLinksExt').textContent = parsed.external.length;

    /* notice */
    var notice = document.getElementById('cwpLinksNotice');
    var noticeText = document.getElementById('cwpLinksNoticeText');
    var linksDot = mb.querySelector('.cwp-dot-links');
    if (parsed.internal.length === 0) {
      notice.hidden = false;
      noticeText.textContent = 'This post links to nothing on your site. Adding two or three internal links helps crawlers reach related posts and passes ranking signals along.';
      if (linksDot) { linksDot.hidden = false; }
    } else {
      notice.hidden = true;
      if (linksDot) { linksDot.hidden = true; }
    }

    /* outbound list */
    var outList = document.getElementById('cwpOutboundLinks');
    var outEmpty = document.getElementById('cwpOutboundEmpty');
    outList.querySelectorAll('.cwp-link-item').forEach(function(el) { el.remove(); });
    removeShowAll(outList);
    if (outTotal === 0) {
      outEmpty.hidden = false;
    } else {
      outEmpty.hidden = true;
      var all = parsed.internal.map(function(l) { l.type = 'internal'; return l; })
        .concat(parsed.external.map(function(l) { l.type = 'external'; return l; }));
      all.forEach(function(link, idx) {
        var div = document.createElement('div');
        div.className = 'cwp-link-item';
        if (idx >= 6) div.classList.add('cwp-link-hidden');
        var chipClass = link.type === 'internal' ? 'is-good' : 'is-muted';
        var chipLabel = link.type === 'internal' ? 'Internal' : 'External';
        div.innerHTML = '<div class="cwp-link-main">' +
          '<a class="cwp-link-title" href="' + link.href + '" target="_blank">' + escHtml(link.text) + '</a>' +
          '<div class="cwp-link-meta">' + escHtml(link.href) + '</div>' +
          '</div>' +
          '<div class="cwp-link-side"><span class="cwp-chip ' + chipClass + '">' + chipLabel + '</span></div>';
        outList.appendChild(div);
      });
      if (all.length > 6) addShowAll(outList, all.length);
    }

    /* inbound list */
    var inList = document.getElementById('cwpInboundLinks');
    var inEmpty = document.getElementById('cwpInboundEmpty');
    inList.querySelectorAll('.cwp-link-item').forEach(function(el) { el.remove(); });
    removeShowAll(inList);
    if (inbound.length === 0) {
      inEmpty.hidden = false;
    } else {
      inEmpty.hidden = true;
      inbound.forEach(function(link, idx) {
        var div = document.createElement('div');
        div.className = 'cwp-link-item is-inbound';
        if (idx >= 6) div.classList.add('cwp-link-hidden');
        var anchorInfo = link.anchor ? 'Anchor: "' + escHtml(link.anchor) + '" \u00b7 ' : '';
        div.innerHTML = '<div class="cwp-link-main">' +
          '<a class="cwp-link-title" href="' + escHtml(link.url) + '" target="_blank">' + escHtml(link.title) + '</a>' +
          '<div class="cwp-link-meta">' + anchorInfo + 'published ' + escHtml(link.date) + '</div>' +
          '</div>';
        inList.appendChild(div);
      });
      if (inbound.length > 6) addShowAll(inList, inbound.length);
    }

    /* suggested links */
    var suggested = crawlwpSEO.suggestedLinks || [];
    var sugList = document.getElementById('cwpSuggestedLinks');
    var sugEmpty = document.getElementById('cwpSuggestedEmpty');
    sugList.querySelectorAll('.cwp-link-item').forEach(function(el) { el.remove(); });
    removeShowAll(sugList);
    if (suggested.length === 0) {
      sugEmpty.hidden = false;
    } else {
      sugEmpty.hidden = true;
      suggested.forEach(function(link, idx) {
        var div = document.createElement('div');
        div.className = 'cwp-link-item is-suggested';
        if (idx >= 6) div.classList.add('cwp-link-hidden');
        div.innerHTML = '<div class="cwp-link-main">' +
          '<a class="cwp-link-title" href="' + escHtml(link.url) + '" target="_blank">' + escHtml(link.title) + '</a>' +
          '<div class="cwp-link-meta">' + escHtml(link.url) + ' \u00b7 ' + escHtml(link.date) + '</div>' +
          '</div>' +
          '<div class="cwp-link-side">' +
            '<button class="cwp-copy-url-btn" type="button" data-url="' + escHtml(link.url) + '" title="Copy URL"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>' +
            '<span class="cwp-chip is-info">Suggested</span>' +
          '</div>';
        sugList.appendChild(div);
      });
      if (suggested.length > 6) addShowAll(sugList, suggested.length);

      /* bind copy buttons */
      sugList.querySelectorAll('.cwp-copy-url-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          var url = btn.getAttribute('data-url');
          if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
              btn.classList.add('is-copied');
              btn.title = 'Copied!';
              setTimeout(function() { btn.classList.remove('is-copied'); btn.title = 'Copy URL'; }, 1500);
            });
          } else {
            var ta = document.createElement('textarea');
            ta.value = url; ta.style.position = 'fixed'; ta.style.left = '-9999px';
            document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
            btn.classList.add('is-copied');
            btn.title = 'Copied!';
            setTimeout(function() { btn.classList.remove('is-copied'); btn.title = 'Copy URL'; }, 1500);
          }
        });
      });
    }
  }

  function escHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  /* ---------- analysis panel ---------- */
  function stripTags(html) {
    var d = document.createElement('div');
    d.innerHTML = html;
    return d.textContent || d.innerText || '';
  }

  function getWordCount(text) {
    return text.split(/\s+/).filter(function(w) { return w.length > 0; }).length;
  }

  function runAnalysis() {
    var keyword = (document.getElementById('cwpKeyword').value || '').trim().toLowerCase();
    var checklist = document.getElementById('cwpChecklist');
    var noticeText = document.getElementById('cwpAnalysisNoticeText');
    var analysisDot = mb.querySelector('.cwp-dot-analysis');

    checklist.innerHTML = '';

    if (!keyword) {
      noticeText.textContent = 'Enter a focus keyword on the General tab to run the analysis.';
      if (analysisDot) analysisDot.hidden = true;
      updateScore(0, 0);
      return;
    }

    noticeText.innerHTML = 'Scored against <b>' + escHtml(keyword) + '</b>. Change the focus keyword on the General tab to rescore.';

    var seoTitle = resolve(title.value || '%%title%% %%sep%% %%sitename%%').toLowerCase();
    var seoDesc = resolve(desc.value || '').toLowerCase();
    var slugVal = (slug.value || '').toLowerCase();
    var html = getEditorContent();
    var plainText = stripTags(html).toLowerCase();
    var wordCount = getWordCount(plainText);
    var parsed = parseLinks(html);

    /* extract headings */
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var headings = tmp.querySelectorAll('h1,h2,h3,h4,h5,h6');
    var h1s = tmp.querySelectorAll('h1');
    var h2s = tmp.querySelectorAll('h2');

    /* extract images */
    var images = tmp.querySelectorAll('img');
    var imagesNoAlt = 0;
    images.forEach(function(img) {
      var alt = (img.getAttribute('alt') || '').trim();
      if (!alt) imagesNoAlt++;
    });

    /* keyword in image alt */
    var keywordInAlt = false;
    images.forEach(function(img) {
      if ((img.getAttribute('alt') || '').toLowerCase().indexOf(keyword) !== -1) keywordInAlt = true;
    });

    /* first paragraph */
    var paragraphs = tmp.querySelectorAll('p');
    var firstParaText = paragraphs.length > 0 ? (paragraphs[0].textContent || '').toLowerCase() : '';

    /* headings with keyword */
    var headingsWithKw = 0;
    headings.forEach(function(h) {
      if (h.textContent.toLowerCase().indexOf(keyword) !== -1) headingsWithKw++;
    });

    /* keyword density */
    var kwCount = 0;
    if (keyword && plainText) {
      var re = new RegExp(keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
      var matches = plainText.match(re);
      kwCount = matches ? matches.length : 0;
    }
    var density = wordCount > 0 ? (kwCount / wordCount * 100) : 0;

    /* sentence count & avg length for readability */
    var sentences = plainText.split(/[.!?]+/).filter(function(s) { return s.trim().length > 5; });
    var avgSentenceLen = sentences.length > 0 ? Math.round(wordCount / sentences.length) : 0;

    var checks = [];
    var passed = 0;
    var total = 0;

    function addCheck(status, boldText, detail) {
      total++;
      if (status === 'good') passed++;
      checks.push({ status: status, bold: boldText, detail: detail });
    }

    /* 1. Keyword in SEO title */
    if (seoTitle.indexOf(keyword) !== -1) {
      var pos = seoTitle.indexOf(keyword);
      if (pos < seoTitle.length / 3) {
        addCheck('good', 'Keyword is in the SEO title.', 'It appears near the start, where it carries the most weight.');
      } else {
        addCheck('good', 'Keyword is in the SEO title.', 'Try moving it closer to the beginning for more impact.');
      }
    } else {
      addCheck('bad', 'Keyword is missing from the SEO title.', 'Add it to the title so search engines and users see it immediately.');
    }

    /* 2. Keyword in URL slug */
    if (slugVal.indexOf(keyword.replace(/\s+/g, '-')) !== -1 || slugVal.indexOf(keyword.replace(/\s+/g, '')) !== -1) {
      addCheck('good', 'Keyword is in the URL slug.', '');
    } else {
      addCheck('bad', 'Keyword is missing from the URL slug.', 'Include it in the slug for better URL relevance.');
    }

    /* 3. Title length (pixel width) */
    var titleText = resolve(title.value || '%%title%% %%sep%% %%sitename%%');
    var titlePx = widthOf(titleText, 'bold 20px Arial');
    if (titlePx >= 200 && titlePx <= 580) {
      addCheck('good', 'Title length fits.', titlePx + ' px of the 580 px Google shows.');
    } else if (titlePx > 580) {
      addCheck('warn', 'Title is too long.', titlePx + ' px exceeds the 580 px limit \u2014 it will be cut off in search results.');
    } else {
      addCheck('warn', 'Title is too short.', titlePx + ' px of the 580 px Google shows. Aim for at least 200 px.');
    }

    /* 4. Meta description */
    if (seoDesc.length > 0) {
      if (seoDesc.indexOf(keyword) !== -1) {
        addCheck('good', 'Keyword is in the meta description.', '');
      } else {
        addCheck('warn', 'Meta description does not contain the keyword.', 'Mentioning it helps bold the term in search results.');
      }
    } else {
      addCheck('bad', 'No meta description set.', 'Write a compelling description that includes the keyword.');
    }

    /* 5. Meta description length */
    if (seoDesc.length > 0) {
      var descPx = widthOf(resolve(desc.value), '14px Arial');
      if (descPx >= 400 && descPx <= 920) {
        addCheck('good', 'Meta description length is good.', descPx + ' px of the 920 px limit.');
      } else if (descPx > 920) {
        addCheck('warn', 'Meta description is too long.', descPx + ' px exceeds 920 px \u2014 it may be truncated.');
      } else {
        addCheck('warn', 'Meta description is too short.', 'Aim for at least 400 px to use the available space.');
      }
    }

    /* 6. Keyword in first paragraph */
    if (firstParaText && firstParaText.indexOf(keyword) !== -1) {
      addCheck('good', 'Keyword appears in the first paragraph.', '');
    } else if (plainText.length > 0) {
      addCheck('warn', 'Keyword is missing from the first paragraph.', 'Introduce the topic early so readers and engines see it upfront.');
    }

    /* 7. Keyword in subheadings */
    if (headingsWithKw >= 2) {
      addCheck('good', 'Keyword appears in ' + headingsWithKw + ' subheadings.', '');
    } else if (headingsWithKw === 1) {
      addCheck('warn', 'Only one subheading uses the keyword.', 'Work it into one or two more H2s where it reads naturally.');
    } else if (headings.length > 0) {
      addCheck('bad', 'No subheading uses the keyword.', 'Add the keyword to at least one H2 or H3.');
    }

    /* 8. H1 check */
    if (h1s.length === 1) {
      addCheck('good', 'Page has exactly one H1 tag.', '');
    } else if (h1s.length === 0) {
      addCheck('warn', 'No H1 tag found in the content.', 'Add one H1 \u2014 it helps search engines understand the main topic.');
    } else {
      addCheck('warn', 'Multiple H1 tags found (' + h1s.length + ').', 'Use only one H1 per page for best SEO practice.');
    }

    /* 9. Images alt text */
    if (images.length === 0) {
      addCheck('warn', 'No images found.', 'Adding relevant images can improve engagement and image search traffic.');
    } else if (imagesNoAlt === 0) {
      addCheck('good', 'All images have alt text.', images.length + ' image(s) found.');
    } else {
      addCheck('bad', imagesNoAlt + ' image(s) missing alt text.', 'Describe what each one shows for accessibility and SEO.');
    }

    /* 10. Keyword in image alt */
    if (images.length > 0) {
      if (keywordInAlt) {
        addCheck('good', 'Keyword found in an image alt attribute.', '');
      } else {
        addCheck('warn', 'No image alt text contains the keyword.', 'Add the keyword to at least one relevant image alt tag.');
      }
    }

    /* 11. Internal links */
    if (parsed.internal.length >= 2) {
      addCheck('good', parsed.internal.length + ' internal links.', 'Good internal linking structure.');
    } else if (parsed.internal.length === 1) {
      addCheck('warn', 'Only 1 internal link.', 'Add at least one more internal link to improve crawlability.');
    } else {
      addCheck('bad', 'No internal links.', 'Link to at least two related posts so crawlers can reach them from here.');
    }

    /* 12. External links */
    if (parsed.external.length >= 1) {
      addCheck('good', parsed.external.length + ' external link(s).', 'Linking to authoritative sources adds credibility.');
    } else {
      addCheck('warn', 'No external links.', 'Consider linking to a relevant authoritative source to add context.');
    }

    /* 13. Content length */
    if (wordCount >= 300) {
      addCheck('good', wordCount.toLocaleString() + ' words.', 'Long enough to cover the topic.');
    } else if (wordCount >= 100) {
      addCheck('warn', wordCount.toLocaleString() + ' words.', 'Aim for at least 300 words to provide enough depth.');
    } else {
      addCheck('bad', wordCount.toLocaleString() + ' words.', 'Content is too thin. Search engines prefer in-depth articles.');
    }

    /* 14. Keyword density */
    if (wordCount > 50) {
      if (density >= 0.5 && density <= 3.0) {
        addCheck('good', 'Keyword density is ' + density.toFixed(1) + '%.', 'Within the recommended 0.5\u20133% range.');
      } else if (density > 3.0) {
        addCheck('warn', 'Keyword density is ' + density.toFixed(1) + '%.', 'This may look like keyword stuffing. Aim for 0.5\u20133%.');
      } else {
        addCheck('warn', 'Keyword density is ' + density.toFixed(1) + '%.', 'Try to mention the keyword a few more times naturally.');
      }
    }

    /* 15. Readability: avg sentence length */
    if (sentences.length >= 3) {
      if (avgSentenceLen <= 20) {
        addCheck('good', 'Average sentence length is ' + avgSentenceLen + ' words.', 'Easy to read.');
      } else if (avgSentenceLen <= 25) {
        addCheck('warn', 'Average sentence length is ' + avgSentenceLen + ' words.', 'Some sentences may be hard to follow. Try breaking them up.');
      } else {
        addCheck('bad', 'Average sentence length is ' + avgSentenceLen + ' words.', 'Sentences are too long. Aim for under 20 words on average.');
      }
    }

    /* 16. Heading hierarchy (uses H2s) */
    if (h2s.length >= 2) {
      addCheck('good', h2s.length + ' H2 subheadings structure the content.', '');
    } else if (h2s.length === 1) {
      addCheck('warn', 'Only 1 H2 subheading found.', 'Adding more H2s improves readability and SEO.');
    } else if (wordCount > 300) {
      addCheck('warn', 'No H2 subheadings found.', 'Break up long content with H2 headings for better structure.');
    }

    /* render */
    var symbols = { good: '\u2713', warn: '!', bad: '\u2715' };
    checks.forEach(function(c) {
      var div = document.createElement('div');
      div.className = 'cwp-checkitem';
      div.innerHTML = '<span class="cwp-badge is-' + c.status + '">' + symbols[c.status] + '</span>' +
        '<span class="cwp-checktext"><b>' + escHtml(c.bold) + '</b>' +
        (c.detail ? ' ' + escHtml(c.detail) : '') + '</span>';
      checklist.appendChild(div);
    });

    /* update dot */
    var issues = total - passed;
    if (analysisDot) {
      if (issues > 0) {
        analysisDot.hidden = false;
        analysisDot.title = issues + ' issue' + (issues > 1 ? 's' : '');
      } else {
        analysisDot.hidden = true;
      }
    }

    updateScore(passed, total);
  }

  function updateScore(passed, total) {
    var pct = total > 0 ? Math.round(passed / total * 100) : 0;
    var ring = mb.querySelector('.cwp-fill');
    var num = mb.querySelector('.cwp-score-num');
    if (ring) ring.setAttribute('stroke-dasharray', pct + ' 100');
    if (num) num.textContent = total > 0 ? pct : '\u2014';
  }

  /* bind analysis to keyword, title, desc, slug changes */
  var kwField = document.getElementById('cwpKeyword');
  kwField.addEventListener('input', function() { runAnalysis(); renderLinks(); });
  title.addEventListener('input', function() { runAnalysis(); });
  desc.addEventListener('input', function() { runAnalysis(); });
  slug.addEventListener('input', function() { runAnalysis(); });

  /* also re-scan when switching to links/analysis tab */
  mb.querySelectorAll('.cwp-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      if (tab.dataset.panel === 'links') renderLinks();
      if (tab.dataset.panel === 'analysis') runAnalysis();
    });
  });

  /* ---------- insights panel (pro only) ---------- */
  if (crawlwpSEO.isProActive) {
    var insightsPeriod = document.getElementById('cwpInsightsPeriod');
    var _insightsLoading = false;
    var _insightsCache = {};
    var _currentEngine = 'google';
    var engineLabels = { google: 'Google', bing: 'Bing', yandex: 'Yandex' };

    /* Engine tab switching */
    var engineTabs = mb.querySelectorAll('.cwp-engine-tab');
    engineTabs.forEach(function(tab) {
      tab.addEventListener('click', function() {
        engineTabs.forEach(function(t) { t.classList.remove('is-active'); });
        tab.classList.add('is-active');
        _currentEngine = tab.dataset.engine;
        if (_insightsCache[_currentEngine]) {
          populateInsightsForEngine(_insightsCache[_currentEngine], _currentEngine);
        } else {
          var period = insightsPeriod ? insightsPeriod.value : '28';
          loadInsights(period);
        }
      });
    });

    if (insightsPeriod) {
      insightsPeriod.addEventListener('change', function() {
        _insightsCache = {};
        loadInsights(insightsPeriod.value);
      });
    }

    function loadInsights(days) {
      var postId = crawlwpSEO.postId || 0;
      if (!postId) {
        var noticeText = document.getElementById('cwpInsightsNoticeText');
        if (noticeText) noticeText.textContent = 'Save the post first to load search performance data.';
        return;
      }

      if (_insightsLoading) return;
      _insightsLoading = true;

      /* Show loading state */
      var cardEls = ['cwpInsClicks', 'cwpInsImpressions', 'cwpInsPosition', 'cwpInsCTR'];
      cardEls.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.textContent = '\u2026';
      });

      jQuery.ajax({
        url: crawlwpSEO.ajaxUrl,
        type: 'POST',
        data: {
          action: 'crawlwp_load_insights',
          nonce: crawlwpSEO.insightsNonce,
          post_id: postId,
          days: parseInt(days, 10)
        },
        success: function(response) {
          _insightsLoading = false;
          if (response.success && response.data) {
            if (response.data.engines) {
              _insightsCache = response.data.engines;
              var engineData = _insightsCache[_currentEngine] || {};
              populateInsightsForEngine(engineData, _currentEngine);
            } else {
              /* Legacy flat format — treat as google */
              _insightsCache = { google: response.data };
              populateInsightsForEngine(response.data, _currentEngine);
            }
          }
        },
        error: function() {
          _insightsLoading = false;
          cardEls.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.textContent = '\u2014';
          });
        }
      });

      /* Also fire custom event for additional pro plugin listeners */
      var evt = new CustomEvent('crawlwp:loadInsights', {
        detail: { permalink: crawlwpSEO.permalink || '', days: parseInt(days, 10) }
      });
      document.dispatchEvent(evt);
    }

    function populateInsightsForEngine(data, engine) {
      var label = engineLabels[engine] || engine;
      var clicks = document.getElementById('cwpInsClicks');
      var impressions = document.getElementById('cwpInsImpressions');
      var position = document.getElementById('cwpInsPosition');
      var ctr = document.getElementById('cwpInsCTR');

      if (clicks) clicks.textContent = data.clicks !== undefined ? Number(data.clicks).toLocaleString() : '\u2014';
      if (impressions) impressions.textContent = data.impressions !== undefined ? Number(data.impressions).toLocaleString() : '\u2014';
      if (position) position.textContent = data.position !== undefined ? Number(data.position).toFixed(1) : '\u2014';
      if (ctr) ctr.textContent = data.ctr !== undefined ? (Number(data.ctr) * 100).toFixed(1) + '%' : '\u2014';

      /* Update keywords description */
      var kwDesc = document.getElementById('cwpKeywordsDesc');
      if (kwDesc) kwDesc.textContent = 'Search queries where this page appeared in ' + label + ' results.';

      /* keywords table */
      var tbody = document.getElementById('cwpInsightsKeywordsBody');
      if (tbody && data.keywords && data.keywords.length > 0) {
        tbody.innerHTML = '';
        data.keywords.forEach(function(kw) {
          var tr = document.createElement('tr');
          tr.innerHTML = '<td>' + escHtml(kw.keyword) + '</td>' +
            '<td>' + (kw.clicks || 0) + '</td>' +
            '<td>' + (kw.impressions || 0) + '</td>' +
            '<td>' + (kw.position ? Number(kw.position).toFixed(1) : '\u2014') + '</td>' +
            '<td>' + (kw.ctr ? (Number(kw.ctr) * 100).toFixed(1) + '%' : '\u2014') + '</td>';
          tbody.appendChild(tr);
        });
      } else if (tbody && (!data.keywords || data.keywords.length === 0)) {
        tbody.innerHTML = '<tr><td colspan="5" class="cwp-empty-msg">No keyword data available for this period.</td></tr>';
      }

      /* indexing status */
      var engineIndexLabel = document.getElementById('cwpEngineIndexLabel');
      var engineIndex = document.getElementById('cwpEngineIndex');
      var lastCrawled = document.getElementById('cwpLastCrawled');
      var indexNowStatus = document.getElementById('cwpIndexNowStatus');

      if (engineIndexLabel) engineIndexLabel.textContent = label + ' Index';
      if (engineIndex && data.indexStatus !== undefined) {
        engineIndex.textContent = data.indexStatus ? 'Indexed' : 'Not indexed';
        engineIndex.className = 'cwp-chip ' + (data.indexStatus ? 'is-good' : 'is-warn');
      }
      if (lastCrawled && data.lastCrawled) lastCrawled.textContent = data.lastCrawled;
      if (indexNowStatus && data.indexNowSubmitted) indexNowStatus.textContent = data.indexNowSubmitted;
    }

    /* Also listen for insights data from the pro plugin via custom event */
    document.addEventListener('crawlwp:insightsData', function(e) {
      if (e.detail) {
        if (e.detail.engines) {
          _insightsCache = e.detail.engines;
          populateInsightsForEngine(_insightsCache[_currentEngine] || {}, _currentEngine);
        } else {
          populateInsightsForEngine(e.detail, _currentEngine);
        }
      }
    });

    /* load insights when switching to the tab */
    mb.querySelectorAll('.cwp-tab').forEach(function(tab) {
      tab.addEventListener('click', function() {
        if (tab.dataset.panel === 'insights') {
          var period = insightsPeriod ? insightsPeriod.value : '28';
          loadInsights(period);
        }
      });
    });
  }

  /* ---------- featured image for social placeholders ---------- */
  function initSocialImagePlaceholders() {
    var featuredUrl = crawlwpSEO.featuredImageUrl || '';
    mb.querySelectorAll('.cwp-img-picker').forEach(function(picker) {
      var thumb = picker.querySelector('.cwp-img-thumb');
      var input = picker.querySelector('input[type="hidden"]');
      if (!thumb || !input) return;
      /* Only set fallback if no custom image is selected */
      if (input.value) return;
      if (featuredUrl) {
        thumb.style.backgroundImage = 'url(' + featuredUrl + ')';
        thumb.classList.remove('cwp-thumb-empty');
      } else {
        thumb.style.backgroundImage = '';
        thumb.classList.add('cwp-thumb-empty');
      }
    });
  }

  /* Watch for featured image changes in Gutenberg */
  function watchFeaturedImage() {
    if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
      var _lastFeaturedId = null;
      wp.data.subscribe(function() {
        var editor = wp.data.select('core/editor');
        if (!editor) return;
        var fid = editor.getEditedPostAttribute('featured_media');
        if (fid === _lastFeaturedId) return;
        _lastFeaturedId = fid;
        if (fid) {
          var media = wp.data.select('core').getMedia(fid);
          if (media && media.source_url) {
            crawlwpSEO.featuredImageUrl = media.source_url;
          }
        } else {
          crawlwpSEO.featuredImageUrl = '';
        }
        initSocialImagePlaceholders();
      });
    }
  }

  /* ---------- init ---------- */
  measureAll();
  sync();
  toggleSync();
  updateSchemaPreview();
  renderLinks();
  runAnalysis();
  watchPostTitle();
  watchPostSlug();
  initSocialImagePlaceholders();
  watchFeaturedImage();

})(jQuery);
