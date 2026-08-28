(function($){
  'use strict';

  var $mb = $('#crawlwp-seo-metabox-inner');
  if (!$mb.length) return;

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
    var $wpTitle = $('#title');
    if ($wpTitle.length) {
      $wpTitle.on('input', function() {
        TOKENS['%%title%%'] = $wpTitle.val();
        measureAll();
        sync();
        runAnalysis();
      });
    }
    /* Gutenberg: subscribe to title changes */
    if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe && wp.data.select('core/editor')) {
      var lastTitle = TOKENS['%%title%%'];
      wp.data.subscribe(function() {
        var sel = wp.data.select('core/editor');
        if (!sel) return;
        var newTitle = sel.getEditedPostAttribute('title');
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
        if (val && val !== $slug.val()) {
          $slug.val(val);
          sync();
          runAnalysis();
        }
      }).observe(wpSlugInput, { attributes: true, attributeFilter: ['value'] });
      $(wpSlugInput).on('change', function() {
        if (_slugSyncLock) return;
        if (wpSlugInput.value && wpSlugInput.value !== $slug.val()) {
          $slug.val(wpSlugInput.value);
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
        var val = $.trim($(editSlug).text());
        if (val && val !== $slug.val()) {
          $slug.val(val);
          sync();
          runAnalysis();
        }
      }).observe(editSlug, { childList: true, characterData: true, subtree: true });
    }
    /* Gutenberg: subscribe to slug changes */
    if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe && wp.data.select('core/editor')) {
      var lastSlug = $slug.val();
      wp.data.subscribe(function() {
        if (_slugSyncLock) return;
        var sel = wp.data.select('core/editor');
        if (!sel) return;
        var newSlug = sel.getEditedPostAttribute('slug');
        if (newSlug !== undefined && newSlug !== lastSlug) {
          lastSlug = newSlug;
          $slug.val(newSlug);
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
    $('#post_name').val(val);
    $('#editable-post-name').text(val);
    $('#editable-post-name-full').text(val);
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
  $mb.find('.cwp-tab').on('click', function() {
    $mb.find('.cwp-tab').removeClass('is-active').attr('aria-selected', 'false');
    $mb.find('.cwp-panel').removeClass('is-active');
    $(this).addClass('is-active').attr('aria-selected', 'true');
    $('#cwp-panel-' + $(this).data('panel')).addClass('is-active');
  });

  /* ---------- social sub-tabs ---------- */
  $mb.find('.cwp-social-tab').on('click', function() {
    $mb.find('.cwp-social-tab').removeClass('is-active');
    $mb.find('.cwp-social-panel').removeClass('is-active');
    $(this).addClass('is-active');
    $('#cwp-social-' + $(this).data('social')).addClass('is-active');
  });

  /* ---------- device toggle ---------- */
  var $serp = $('#cwpSerp');
  var $deviceSeg = $('#cwpDeviceSeg');
  if ($deviceSeg.length) {
    $deviceSeg.on('click', function(e) {
      var $btn = $(e.target).closest('button');
      if (!$btn.length) return;
      $deviceSeg.find('button').removeClass('is-active');
      $btn.addClass('is-active');
      $serp.toggleClass('is-mobile', $btn.data('device') === 'mobile');
      measureAll();
    });
  }

  /* ---------- variable menus ---------- */
  $(document).on('click', function(e) {
    var $btn = $(e.target).closest('.cwp-var-btn');
    $('.cwp-var-menu').each(function() {
      var $m = $(this);
      if (!$btn.length || $m.prev()[0] !== $btn[0]) $m.removeClass('is-open');
    });
    if ($btn.length) $btn.next().toggleClass('is-open');

    var $item = $(e.target).closest('.cwp-var-item');
    if ($item.length) {
      var $menu = $item.closest('.cwp-var-menu');
      var $target = $('#' + $menu.prev().data('varFor'));
      var target = $target[0];
      var start = target.selectionStart || target.value.length;
      target.value = target.value.slice(0, start) + $item.data('token') + target.value.slice(target.selectionEnd || start);
      target.focus();
      target.selectionStart = target.selectionEnd = start + $item.data('token').length;
      $menu.removeClass('is-open');
      $target.trigger('input');
    }
  });

  /* ---------- pixel measurement ---------- */
  var ctx = $('<canvas>')[0].getContext('2d');
  function widthOf(text, font) { ctx.font = font; return Math.round(ctx.measureText(text).width); }

  function measure(el) {
    var $el = $(el);
    var text  = resolve($el.val());
    var limit = parseInt($el.data('limit'), 10);
    var px    = widthOf(text, $el.data('font'));
    var pct   = Math.min(100, Math.round(px / limit * 100));
    var $fill  = $('#' + $el.data('meter') + 'Fill');
    var $label = $('#' + $el.data('meter'));

    $fill.css('width', pct + '%').removeClass('is-good is-over');
    var state = 'short';
    if (px > limit) { $fill.addClass('is-over'); state = 'over'; }
    else if (pct >= 70) { $fill.addClass('is-good'); state = 'good'; }

    var words = { short: 'Too short', good: 'Good length', over: 'Will be cut off' }[state];
    $label.html('<b>' + words + '</b> · ' + px + ' / ' + limit + ' px · ' + text.length + ' chars');
  }

  function measureAll() {
    $mb.find('[data-meter]').each(function() { measure(this); });
  }

  /* ---------- live preview binding ---------- */
  var $title = $('#cwpTitle');
  var $desc  = $('#cwpDesc');
  var $slug  = $('#cwpSlug');

  var $fbSync  = $('#cwpFbSync');
  var $fbTitle = $('#cwpFbTitle');
  var $fbDesc  = $('#cwpFbDesc');
  var $xSync   = $('#cwpXSync');
  var $xTitle  = $('#cwpXTitle');
  var $xDesc   = $('#cwpXDesc');

  function sync() {
    var t = resolve($title.val()) || crawlwpSEO.postTitle || 'Enter a title';
    var d = resolve($desc.val())  || crawlwpSEO.excerpt || 'Add a meta description to control what appears here.';

    $('#cwpSerpTitle').text(t);
    $('#cwpSerpDesc').text(d);

    var urlParts = crawlwpSEO.siteUrl.replace(/^https?:\/\//, '').replace(/\/$/, '');
    $('#cwpSerpUrl').text(urlParts + ' › ' + ($slug.val() || '…'));
    $('#cwpSlugEcho').text($slug.val() || '…');

    /* social mirrors */
    var fbT = $fbSync.prop('checked') ? t : ($fbTitle.val() || t);
    var fbD = $fbSync.prop('checked') ? d : ($fbDesc.val()  || d);
    $('#cwpFbTitlePrev').text(fbT);
    $('#cwpFbDescPrev').text(fbD);

    $('#cwpXTitlePrev').text($xSync.prop('checked') ? fbT : ($xTitle.val() || fbT));
    $('#cwpXDescPrev').text($xSync.prop('checked') ? fbD : ($xDesc.val()  || fbD));
  }

  function toggleSync() {
    $fbTitle.prop('disabled', $fbSync.prop('checked'));
    $fbDesc.prop('disabled', $fbSync.prop('checked'));
    $xTitle.prop('disabled', $xSync.prop('checked'));
    $xDesc.prop('disabled', $xSync.prop('checked'));
    sync();
  }

  $fbSync.add($xSync).on('change', toggleSync);
  $fbTitle.add($fbDesc).add($xTitle).add($xDesc).on('input', sync);

  $title.add($desc).on('input', function() { measure(this); sync(); });
  $slug.on('input', function() {
    sync();
    pushSlugToWP($slug.val());
  });

  /* ---------- JSON-LD toggle ---------- */
  var $jsonBtn = $('#cwpJsonToggle');
  var $jsonPre = $('#cwpJson');
  if ($jsonBtn.length && $jsonPre.length) {
    $jsonBtn.on('click', function() {
      var open = $jsonPre.prop('hidden');
      $jsonPre.prop('hidden', !open);
      $jsonBtn.text(open ? 'Hide JSON-LD' : 'Show JSON-LD');
    });
  }

  /* ---------- schema type change updates preview ---------- */
  var $schemaType = $('#cwpSchema');
  if ($schemaType.length) {
    $schemaType.on('change', function() {
      updateSchemaPreview();
    });
  }
  var $schemaHeadline = $('#cwpHeadline');
  var $schemaSection  = $('#cwpSection');
  if ($schemaHeadline.length) $schemaHeadline.on('input', updateSchemaPreview);
  if ($schemaSection.length) $schemaSection.on('input', updateSchemaPreview);

  function updateSchemaPreview() {
    if (!$jsonPre.length) return;
    var type = $schemaType.length ? $schemaType.val() : 'Article';
    if (type === 'None — output nothing') {
      $jsonPre.text('// No structured data will be output for this post.');
      return;
    }
    var headline = ($schemaHeadline.length && $schemaHeadline.val()) || resolve($title.val()) || crawlwpSEO.postTitle;
    var section  = $schemaSection.length ? $schemaSection.val() : '';
    var schema = {
      '@context': 'https://schema.org',
      '@type': type,
      'headline': headline
    };
    if (section) schema.articleSection = section;
    schema.author = { '@type': 'Person', 'name': crawlwpSEO.author || '' };
    schema.datePublished = new Date().toISOString().slice(0, 10);
    $jsonPre.text(JSON.stringify(schema, null, 2));
  }

  /* ---------- image pickers (WP media) ---------- */

  /* Map input IDs to their corresponding social preview card image selectors */
  function updateSocialPreviewImage(targetId, imageUrl) {
    if (targetId === 'cwpOgImage') {
      /* Update Facebook preview card image */
      var $fbCard = $mb.find('.cwp-fb-card .cwp-og-img');
      if ($fbCard.length) {
        if (imageUrl) {
          $fbCard.css('backgroundImage', 'url(' + imageUrl + ')').text('');
        } else {
          var fiFb = crawlwpSEO.featuredImageUrl || '';
          if (fiFb) {
            $fbCard.css('backgroundImage', 'url(' + fiFb + ')').text('');
          } else {
            $fbCard.css('backgroundImage', '').text('1200 \u00d7 630');
          }
        }
      }
      /* Also update X preview if X has no custom image */
      var $xInput = $('#cwpXImage');
      if ($xInput.length && !$xInput.val()) {
        var $xCard = $mb.find('.cwp-x-card .cwp-og-img');
        if ($xCard.length) {
          if (imageUrl) {
            $xCard.css('backgroundImage', 'url(' + imageUrl + ')').text('');
          } else {
            var fiX1 = crawlwpSEO.featuredImageUrl || '';
            if (fiX1) {
              $xCard.css('backgroundImage', 'url(' + fiX1 + ')').text('');
            } else {
              $xCard.css('backgroundImage', '').text('1200 \u00d7 675');
            }
          }
        }
      }
    } else if (targetId === 'cwpXImage') {
      /* Update X preview card image */
      var $xCard2 = $mb.find('.cwp-x-card .cwp-og-img');
      if ($xCard2.length) {
        if (imageUrl) {
          $xCard2.css('backgroundImage', 'url(' + imageUrl + ')').text('');
        } else {
          /* Fall back to OG image or clear */
          var $ogInput = $('#cwpOgImage');
          var $ogThumb = $ogInput.length ? $ogInput.closest('.cwp-img-picker').find('.cwp-img-thumb') : $();
          var fallback = $ogThumb.length ? $ogThumb.css('backgroundImage') : '';
          $xCard2.css('backgroundImage', fallback || '');
          if (fallback && fallback !== 'none') {
            $xCard2.text('');
          } else {
            var fiX2 = crawlwpSEO.featuredImageUrl || '';
            if (fiX2) {
              $xCard2.css('backgroundImage', 'url(' + fiX2 + ')').text('');
            } else {
              $xCard2.text('1200 \u00d7 675');
            }
          }
        }
      }
    }
  }

  $mb.find('.cwp-img-pick-btn').on('click', function(e) {
    e.preventDefault();
    var $btn = $(this);
    var target   = $btn.data('target');
    var $inputEl = $('#' + target);
    var $thumbEl = $btn.closest('.cwp-img-picker').find('.cwp-img-thumb');

    var frame = wp.media({
      title: 'Select Image',
      multiple: false,
      library: { type: 'image' }
    });

    frame.on('select', function() {
      var attachment = frame.state().get('selection').first().toJSON();
      $inputEl.val(attachment.id);
      if ($thumbEl.length && attachment.url) {
        $thumbEl.css('backgroundImage', 'url(' + attachment.url + ')');
      }
      /* Instantly update the social preview card */
      updateSocialPreviewImage(target, attachment.url);
    });

    frame.open();
  });

  $mb.find('.cwp-img-remove-btn').on('click', function(e) {
    e.preventDefault();
    var $btn = $(this);
    var target  = $btn.data('target');
    var $inputEl = $('#' + target);
    var $thumbEl = $btn.closest('.cwp-img-picker').find('.cwp-img-thumb');
    $inputEl.val('');
    if ($thumbEl.length) {
      /* Fall back to featured image or green placeholder */
      var fi = crawlwpSEO.featuredImageUrl || '';
      if (fi) {
        $thumbEl.css('backgroundImage', 'url(' + fi + ')').removeClass('cwp-thumb-empty');
      } else {
        $thumbEl.css('backgroundImage', '').addClass('cwp-thumb-empty');
      }
    }
    /* Instantly update the social preview card */
    updateSocialPreviewImage(target, '');
  });

  /* ---------- links panel ---------- */
  function getEditorContent() {
    /* Try TinyMCE first, then Gutenberg, then fall back to localized content */
    if (typeof tinymce !== 'undefined') {
      var ed = tinymce.get('content');
      if (ed && !ed.isHidden()) return ed.getContent();
    }
    var $wpBlock = $('.block-editor-block-list__layout');
    if ($wpBlock.length) return $wpBlock.html();
    var $ta = $('#content');
    if ($ta.length) return $ta.val();
    return crawlwpSEO.postContent || '';
  }

  function parseLinks(html) {
    var $tmp = $('<div>').html(html);
    var siteHost = new URL(crawlwpSEO.siteUrl).hostname;
    var internal = [], external = [];
    $tmp.find('a[href]').each(function() {
      var $a = $(this);
      var href = $a.attr('href');
      if (!href || href.charAt(0) === '#') return;
      var text = $.trim($a.text()) || href;
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
  function addShowAll($container, total) {
    var $btn = $('<a>', {
      href: '#',
      'class': 'cwp-show-all-link',
      text: 'Show all ' + total + ' links'
    }).on('click', function(e) {
      e.preventDefault();
      $container.find('.cwp-link-hidden').removeClass('cwp-link-hidden');
      $btn.remove();
    });
    $container.append($btn);
  }

  function removeShowAll($container) {
    $container.find('.cwp-show-all-link').remove();
  }

  function renderLinks() {
    var html = getEditorContent();
    var parsed = parseLinks(html);
    var outTotal = parsed.internal.length + parsed.external.length;
    var inbound = crawlwpSEO.inboundLinks || [];

    $('#cwpLinksOut').text(outTotal);
    $('#cwpLinksIn').text(inbound.length);
    $('#cwpLinksExt').text(parsed.external.length);

    /* notice */
    var $notice = $('#cwpLinksNotice');
    var $noticeText = $('#cwpLinksNoticeText');
    var $linksDot = $mb.find('.cwp-dot-links');
    if (parsed.internal.length === 0) {
      $notice.prop('hidden', false);
      $noticeText.text('This post links to nothing on your site. Adding two or three internal links helps crawlers reach related posts and passes ranking signals along.');
      $linksDot.prop('hidden', false);
    } else {
      $notice.prop('hidden', true);
      $linksDot.prop('hidden', true);
    }

    /* outbound list */
    var $outList = $('#cwpOutboundLinks');
    var $outEmpty = $('#cwpOutboundEmpty');
    $outList.find('.cwp-link-item').remove();
    removeShowAll($outList);
    if (outTotal === 0) {
      $outEmpty.prop('hidden', false);
    } else {
      $outEmpty.prop('hidden', true);
      var all = parsed.internal.map(function(l) { l.type = 'internal'; return l; })
        .concat(parsed.external.map(function(l) { l.type = 'external'; return l; }));
      $.each(all, function(idx, link) {
        var chipClass = link.type === 'internal' ? 'is-good' : 'is-muted';
        var chipLabel = link.type === 'internal' ? 'Internal' : 'External';
        var $div = $('<div>', { 'class': 'cwp-link-item' });
        if (idx >= 6) $div.addClass('cwp-link-hidden');
        $div.html('<div class="cwp-link-main">' +
          '<a class="cwp-link-title" href="' + link.href + '" target="_blank">' + escHtml(link.text) + '</a>' +
          '<div class="cwp-link-meta">' + escHtml(link.href) + '</div>' +
          '</div>' +
          '<div class="cwp-link-side"><span class="cwp-chip ' + chipClass + '">' + chipLabel + '</span></div>');
        $outList.append($div);
      });
      if (all.length > 6) addShowAll($outList, all.length);
    }

    /* inbound list */
    var $inList = $('#cwpInboundLinks');
    var $inEmpty = $('#cwpInboundEmpty');
    $inList.find('.cwp-link-item').remove();
    removeShowAll($inList);
    if (inbound.length === 0) {
      $inEmpty.prop('hidden', false);
    } else {
      $inEmpty.prop('hidden', true);
      $.each(inbound, function(idx, link) {
        var $div = $('<div>', { 'class': 'cwp-link-item is-inbound' });
        if (idx >= 6) $div.addClass('cwp-link-hidden');
        var anchorInfo = link.anchor ? 'Anchor: "' + escHtml(link.anchor) + '" \u00b7 ' : '';
        $div.html('<div class="cwp-link-main">' +
          '<a class="cwp-link-title" href="' + escHtml(link.url) + '" target="_blank">' + escHtml(link.title) + '</a>' +
          '<div class="cwp-link-meta">' + anchorInfo + 'published ' + escHtml(link.date) + '</div>' +
          '</div>');
        $inList.append($div);
      });
      if (inbound.length > 6) addShowAll($inList, inbound.length);
    }

    /* suggested links */
    var suggested = crawlwpSEO.suggestedLinks || [];
    var $sugList = $('#cwpSuggestedLinks');
    var $sugEmpty = $('#cwpSuggestedEmpty');
    $sugList.find('.cwp-link-item').remove();
    removeShowAll($sugList);
    if (suggested.length === 0) {
      $sugEmpty.prop('hidden', false);
    } else {
      $sugEmpty.prop('hidden', true);
      $.each(suggested, function(idx, link) {
        var $div = $('<div>', { 'class': 'cwp-link-item is-suggested' });
        if (idx >= 6) $div.addClass('cwp-link-hidden');
        $div.html('<div class="cwp-link-main">' +
          '<a class="cwp-link-title" href="' + escHtml(link.url) + '" target="_blank">' + escHtml(link.title) + '</a>' +
          '<div class="cwp-link-meta">' + escHtml(link.url) + ' \u00b7 ' + escHtml(link.date) + '</div>' +
          '</div>' +
          '<div class="cwp-link-side">' +
            '<button class="cwp-copy-url-btn" type="button" data-url="' + escHtml(link.url) + '" title="Copy URL"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>' +
            '<span class="cwp-chip is-info">Suggested</span>' +
          '</div>');
        $sugList.append($div);
      });
      if (suggested.length > 6) addShowAll($sugList, suggested.length);

      /* bind copy buttons */
      $sugList.find('.cwp-copy-url-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var url = $btn.attr('data-url');
        if (navigator.clipboard) {
          navigator.clipboard.writeText(url).then(function() {
            $btn.addClass('is-copied').attr('title', 'Copied!');
            setTimeout(function() { $btn.removeClass('is-copied').attr('title', 'Copy URL'); }, 1500);
          });
        } else {
          var $ta = $('<textarea>').val(url).css({ position: 'fixed', left: '-9999px' }).appendTo('body');
          $ta[0].select();
          document.execCommand('copy');
          $ta.remove();
          $btn.addClass('is-copied').attr('title', 'Copied!');
          setTimeout(function() { $btn.removeClass('is-copied').attr('title', 'Copy URL'); }, 1500);
        }
      });
    }
  }

  function escHtml(str) {
    return $('<div>').text(str).html();
  }

  /* ---------- analysis panel ---------- */
  function stripTags(html) {
    return $('<div>').html(html).text();
  }

  function getWordCount(text) {
    return text.split(/\s+/).filter(function(w) { return w.length > 0; }).length;
  }

  function runAnalysis() {
    var keyword = $.trim($('#cwpKeyword').val()).toLowerCase();
    var $checklist = $('#cwpChecklist');
    var $noticeText = $('#cwpAnalysisNoticeText');
    var $analysisDot = $mb.find('.cwp-dot-analysis');

    $checklist.empty();

    if (!keyword) {
      $noticeText.text('Enter a focus keyword on the General tab to run the analysis.');
      $analysisDot.prop('hidden', true);
      updateScore(0, 0);
      return;
    }

    $noticeText.html('Scored against <b>' + escHtml(keyword) + '</b>. Change the focus keyword on the General tab to rescore.');

    var seoTitle = resolve($title.val() || '%%title%% %%sep%% %%sitename%%').toLowerCase();
    var seoDesc = resolve($desc.val() || '').toLowerCase();
    var slugVal = ($slug.val() || '').toLowerCase();
    var html = getEditorContent();
    var plainText = stripTags(html).toLowerCase();
    var wordCount = getWordCount(plainText);
    var parsed = parseLinks(html);

    /* extract headings */
    var $tmp = $('<div>').html(html);
    var $headings = $tmp.find('h1,h2,h3,h4,h5,h6');
    var $h1s = $tmp.find('h1');
    var $h2s = $tmp.find('h2');

    /* extract images */
    var $images = $tmp.find('img');
    var imagesNoAlt = 0;
    $images.each(function() {
      var alt = $.trim($(this).attr('alt') || '');
      if (!alt) imagesNoAlt++;
    });

    /* keyword in image alt */
    var keywordInAlt = false;
    $images.each(function() {
      if (($(this).attr('alt') || '').toLowerCase().indexOf(keyword) !== -1) keywordInAlt = true;
    });

    /* first paragraph */
    var $paragraphs = $tmp.find('p');
    var firstParaText = $paragraphs.length > 0 ? ($paragraphs.first().text() || '').toLowerCase() : '';

    /* headings with keyword */
    var headingsWithKw = 0;
    $headings.each(function() {
      if ($(this).text().toLowerCase().indexOf(keyword) !== -1) headingsWithKw++;
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
    var sentences = plainText.split(/[.!?]+/).filter(function(s) { return $.trim(s).length > 5; });
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
    var titleText = resolve($title.val() || '%%title%% %%sep%% %%sitename%%');
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
      var descPx = widthOf(resolve($desc.val()), '14px Arial');
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
    } else if ($headings.length > 0) {
      addCheck('bad', 'No subheading uses the keyword.', 'Add the keyword to at least one H2 or H3.');
    }

    /* 8. H1 check */
    if ($h1s.length === 1) {
      addCheck('good', 'Page has exactly one H1 tag.', '');
    } else if ($h1s.length === 0) {
      addCheck('warn', 'No H1 tag found in the content.', 'Add one H1 \u2014 it helps search engines understand the main topic.');
    } else {
      addCheck('warn', 'Multiple H1 tags found (' + $h1s.length + ').', 'Use only one H1 per page for best SEO practice.');
    }

    /* 9. Images alt text */
    if ($images.length === 0) {
      addCheck('warn', 'No images found.', 'Adding relevant images can improve engagement and image search traffic.');
    } else if (imagesNoAlt === 0) {
      addCheck('good', 'All images have alt text.', $images.length + ' image(s) found.');
    } else {
      addCheck('bad', imagesNoAlt + ' image(s) missing alt text.', 'Describe what each one shows for accessibility and SEO.');
    }

    /* 10. Keyword in image alt */
    if ($images.length > 0) {
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
    if ($h2s.length >= 2) {
      addCheck('good', $h2s.length + ' H2 subheadings structure the content.', '');
    } else if ($h2s.length === 1) {
      addCheck('warn', 'Only 1 H2 subheading found.', 'Adding more H2s improves readability and SEO.');
    } else if (wordCount > 300) {
      addCheck('warn', 'No H2 subheadings found.', 'Break up long content with H2 headings for better structure.');
    }

    /* render */
    var symbols = { good: '\u2713', warn: '!', bad: '\u2715' };
    $.each(checks, function(i, c) {
      var $div = $('<div>', { 'class': 'cwp-checkitem' });
      $div.html('<span class="cwp-badge is-' + c.status + '">' + symbols[c.status] + '</span>' +
        '<span class="cwp-checktext"><b>' + escHtml(c.bold) + '</b>' +
        (c.detail ? ' ' + escHtml(c.detail) : '') + '</span>');
      $checklist.append($div);
    });

    /* update dot */
    var issues = total - passed;
    if ($analysisDot.length) {
      if (issues > 0) {
        $analysisDot.prop('hidden', false).attr('title', issues + ' issue' + (issues > 1 ? 's' : ''));
      } else {
        $analysisDot.prop('hidden', true);
      }
    }

    updateScore(passed, total);
  }

  function updateScore(passed, total) {
    var pct = total > 0 ? Math.round(passed / total * 100) : 0;
    var $ring = $mb.find('.cwp-fill');
    var $num = $mb.find('.cwp-score-num');
    if ($ring.length) $ring.attr('stroke-dasharray', pct + ' 100');
    if ($num.length) $num.text(total > 0 ? pct : '\u2014');
  }

  /* bind analysis to keyword, title, desc, slug changes */
  var $kwField = $('#cwpKeyword');
  $kwField.on('input', function() { runAnalysis(); renderLinks(); });
  $title.on('input', function() { runAnalysis(); });
  $desc.on('input', function() { runAnalysis(); });
  $slug.on('input', function() { runAnalysis(); });

  /* also re-scan when switching to links/analysis tab */
  $mb.find('.cwp-tab').on('click', function() {
    if ($(this).data('panel') === 'links') renderLinks();
    if ($(this).data('panel') === 'analysis') runAnalysis();
  });

  /* ---------- insights panel (pro only) ---------- */
  if (crawlwpSEO.isProActive) {
    var $insightsPeriod = $('#cwpInsightsPeriod');
    var _insightsLoading = false;
    var _insightsCache = {};
    var _currentEngine = 'google';
    var engineLabels = { google: 'Google', bing: 'Bing', yandex: 'Yandex' };

    /* Engine tab switching */
    var $engineTabs = $mb.find('.cwp-engine-tab');
    $engineTabs.on('click', function() {
      $engineTabs.removeClass('is-active');
      $(this).addClass('is-active');
      _currentEngine = $(this).data('engine');
      if (_insightsCache[_currentEngine]) {
        populateInsightsForEngine(_insightsCache[_currentEngine], _currentEngine);
      } else {
        var period = $insightsPeriod.length ? $insightsPeriod.val() : '28';
        loadInsights(period);
      }
    });

    if ($insightsPeriod.length) {
      $insightsPeriod.on('change', function() {
        _insightsCache = {};
        loadInsights($insightsPeriod.val());
      });
    }

    function loadInsights(days) {
      var postId = crawlwpSEO.postId || 0;
      if (!postId) {
        $('#cwpInsightsNoticeText').text('Save the post first to load search performance data.');
        return;
      }

      if (_insightsLoading) return;
      _insightsLoading = true;

      /* Show loading state */
      var cardIds = ['cwpInsClicks', 'cwpInsImpressions', 'cwpInsPosition', 'cwpInsCTR'];
      $.each(cardIds, function(i, id) {
        $('#' + id).text('\u2026');
      });

      $.ajax({
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
          $.each(cardIds, function(i, id) {
            $('#' + id).text('\u2014');
          });
        }
      });

      /* Also fire custom event for additional pro plugin listeners */
      $(document).trigger('crawlwp:loadInsights', {
        permalink: crawlwpSEO.permalink || '',
        days: parseInt(days, 10)
      });
    }

    function populateInsightsForEngine(data, engine) {
      var label = engineLabels[engine] || engine;
      $('#cwpInsClicks').text(data.clicks !== undefined ? Number(data.clicks).toLocaleString() : '\u2014');
      $('#cwpInsImpressions').text(data.impressions !== undefined ? Number(data.impressions).toLocaleString() : '\u2014');
      $('#cwpInsPosition').text(data.position !== undefined ? Number(data.position).toFixed(1) : '\u2014');
      $('#cwpInsCTR').text(data.ctr !== undefined ? (Number(data.ctr) * 100).toFixed(1) + '%' : '\u2014');

      /* Update keywords description */
      $('#cwpKeywordsDesc').text('Search queries where this page appeared in ' + label + ' results.');

      /* keywords table */
      var $tbody = $('#cwpInsightsKeywordsBody');
      if ($tbody.length && data.keywords && data.keywords.length > 0) {
        $tbody.empty();
        $.each(data.keywords, function(i, kw) {
          var $tr = $('<tr>').html(
            '<td>' + escHtml(kw.keyword) + '</td>' +
            '<td>' + (kw.clicks || 0) + '</td>' +
            '<td>' + (kw.impressions || 0) + '</td>' +
            '<td>' + (kw.position ? Number(kw.position).toFixed(1) : '\u2014') + '</td>' +
            '<td>' + (kw.ctr ? (Number(kw.ctr) * 100).toFixed(1) + '%' : '\u2014') + '</td>'
          );
          $tbody.append($tr);
        });
      } else if ($tbody.length && (!data.keywords || data.keywords.length === 0)) {
        $tbody.html('<tr><td colspan="5" class="cwp-empty-msg">No keyword data available for this period.</td></tr>');
      }

      /* indexing status */
      $('#cwpEngineIndexLabel').text(label + ' Index');
      var $engineIndex = $('#cwpEngineIndex');
      if ($engineIndex.length && data.indexStatus !== undefined) {
        $engineIndex.text(data.indexStatus ? 'Indexed' : 'Not indexed')
          .attr('class', 'cwp-chip ' + (data.indexStatus ? 'is-good' : 'is-warn'));
      }
      if (data.lastCrawled) $('#cwpLastCrawled').text(data.lastCrawled);
      if (data.indexNowSubmitted) $('#cwpIndexNowStatus').text(data.indexNowSubmitted);
    }

    /* Also listen for insights data from the pro plugin via custom event */
    $(document).on('crawlwp:insightsData', function(e, detail) {
      if (detail) {
        if (detail.engines) {
          _insightsCache = detail.engines;
          populateInsightsForEngine(_insightsCache[_currentEngine] || {}, _currentEngine);
        } else {
          populateInsightsForEngine(detail, _currentEngine);
        }
      }
    });

    /* load insights when switching to the tab */
    $mb.find('.cwp-tab').on('click', function() {
      if ($(this).data('panel') === 'insights') {
        var period = $insightsPeriod.length ? $insightsPeriod.val() : '28';
        loadInsights(period);
      }
    });
  }

  /* ---------- featured image for social placeholders ---------- */
  function initSocialImagePlaceholders() {
    var featuredUrl = crawlwpSEO.featuredImageUrl || '';
    $mb.find('.cwp-img-picker').each(function() {
      var $picker = $(this);
      var $thumb = $picker.find('.cwp-img-thumb');
      var $input = $picker.find('input[type="hidden"]');
      if (!$thumb.length || !$input.length) return;
      /* Only set fallback if no custom image is selected */
      if ($input.val()) return;
      if (featuredUrl) {
        $thumb.css('backgroundImage', 'url(' + featuredUrl + ')').removeClass('cwp-thumb-empty');
      } else {
        $thumb.css('backgroundImage', '').addClass('cwp-thumb-empty');
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
