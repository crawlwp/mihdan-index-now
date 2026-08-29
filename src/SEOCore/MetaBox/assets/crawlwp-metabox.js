(function($){

  'use strict';

  /**
   * CrawlWP SEO Metabox controller.
   *
   * Implemented as a plain JavaScript object (no Class syntax) with inner
   * methods. Expensive work such as analysis and link rendering is not called
   * directly from every input handler. Instead handlers dispatch custom events
   * on the metabox element and dedicated listeners react to them:
   *
   *   crawlwp:sync        -> refresh the live previews
   *   crawlwp:measure     -> recalculate pixel meters
   *   crawlwp:renderLinks -> rebuild the Links panel
   *   crawlwp:analyze     -> re-run the Analysis panel
   */
  var CrawlWP = {

    /* ---------- state ---------- */
    $mb: null,
    TOKENS: {},
    ctx: null,
    _slugSyncLock: false,

    /* cached fields (populated in cacheElements) */
    $title: null,
    $desc: null,
    $slug: null,
    $fbSync: null,
    $fbTitle: null,
    $fbDesc: null,
    $xSync: null,
    $xTitle: null,
    $xDesc: null,
    $jsonPre: null,
    $schemaType: null,
    $schemaHeadline: null,
    $schemaSection: null,

    /* ---------- bootstrap ---------- */
    init: function() {
      this.$mb = $('#crawlwp-seo-metabox-inner');
      if (!this.$mb.length) return;

      this.ctx = $('<canvas>')[0].getContext('2d');

      this.setupTokens();
      this.cacheElements();
      this.bindEventBus();
      this.bindUi();
      this.watchPostTitle();
      this.watchPostSlug();
      this.watchPostContent();
      this.watchFeaturedImage();
      this.initInsights();

      /* initial paint */
      this.emit('measure');
      this.emit('sync');
      this.toggleSync();
      this.updateSchemaPreview();
      this.initSocialImagePlaceholders();
      this.emit('renderLinks');
      this.emit('analyze');
    },

    setupTokens: function() {
      this.TOKENS = {
        '{{title}}':       crawlwpSEO.postTitle || '',
        '{{sitename}}':    crawlwpSEO.siteName || '',
        '{{sep}}':         crawlwpSEO.separator || '\u2014',
        '{{category}}':    crawlwpSEO.category || '',
        '{{excerpt}}':     crawlwpSEO.excerpt || '',
        '{{currentyear}}': crawlwpSEO.currentYear || '',
        '{{author}}':      crawlwpSEO.author || ''
      };
    },

    cacheElements: function() {
      this.$title = $('#cwpTitle');
      this.$desc  = $('#cwpDesc');
      this.$slug  = $('#cwpSlug');

      this.$fbSync  = $('#cwpFbSync');
      this.$fbTitle = $('#cwpFbTitle');
      this.$fbDesc  = $('#cwpFbDesc');
      this.$xSync   = $('#cwpXSync');
      this.$xTitle  = $('#cwpXTitle');
      this.$xDesc   = $('#cwpXDesc');

      this.$jsonPre        = $('#cwpJson');
      this.$schemaType     = $('#cwpSchema');
      this.$schemaHeadline = $('#cwpHeadline');
      this.$schemaSection  = $('#cwpSection');
    },

    /* ---------- event bus ---------- */
    /* Listeners are registered once; input handlers only emit events. */
    bindEventBus: function() {
      var self = this;
      this.$mb.on('crawlwp:sync',        function() { self.sync(); });
      this.$mb.on('crawlwp:measure',     function() { self.measureAll(); });
      this.$mb.on('crawlwp:renderLinks', function() { self.renderLinks(); });
      this.$mb.on('crawlwp:analyze',     function() { self.runAnalysis(); });
    },

    /* dispatch a namespaced event on the metabox element */
    emit: function(name) {
      this.$mb.trigger('crawlwp:' + name);
    },

    resolve: function(str) {
      var tokens = this.TOKENS;
      return str.replace(/\{\{[a-z]+\}\}/g, function(m) {
        return tokens[m] !== undefined ? tokens[m] : m;
      });
    },

    /* ---------- UI wiring ---------- */
    bindUi: function() {
      var self = this;

      /* tabs */
      this.$mb.find('.cwp-tab').on('click', function() {
        self.$mb.find('.cwp-tab').removeClass('is-active').attr('aria-selected', 'false');
        self.$mb.find('.cwp-panel').removeClass('is-active');
        $(this).addClass('is-active').attr('aria-selected', 'true');
        $('#cwp-panel-' + $(this).data('panel')).addClass('is-active');

        /* re-scan when switching to links/analysis */
        if ($(this).data('panel') === 'links') self.emit('renderLinks');
        if ($(this).data('panel') === 'analysis') self.emit('analyze');
      });

      /* social sub-tabs */
      this.$mb.find('.cwp-social-tab').on('click', function() {
        self.$mb.find('.cwp-social-tab').removeClass('is-active');
        self.$mb.find('.cwp-social-panel').removeClass('is-active');
        $(this).addClass('is-active');
        $('#cwp-social-' + $(this).data('social')).addClass('is-active');
      });

      /* device toggle */
      var $serp = $('#cwpSerp');
      var $deviceSeg = $('#cwpDeviceSeg');
      if ($deviceSeg.length) {
        $deviceSeg.on('click', function(e) {
          var $btn = $(e.target).closest('button');
          if (!$btn.length) return;
          $deviceSeg.find('button').removeClass('is-active');
          $btn.addClass('is-active');
          $serp.toggleClass('is-mobile', $btn.data('device') === 'mobile');
          self.emit('measure');
        });
      }

      /* variable menus */
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
          if (!$target.length) return;
          var target = $target[0];
          var start = target.selectionStart || target.value.length;
          target.value = target.value.slice(0, start) + $item.data('token') + target.value.slice(target.selectionEnd || start);
          target.focus();
          target.selectionStart = target.selectionEnd = start + $item.data('token').length;
          $menu.removeClass('is-open');
          $target.trigger('input');
        }
      });

      /* live preview binding */
      this.$fbSync.add(this.$xSync).on('change', function() { self.toggleSync(); });
      this.$fbTitle.add(this.$fbDesc).add(this.$xTitle).add(this.$xDesc).on('input', function() { self.emit('sync'); });

      this.$title.add(this.$desc).on('input', function() {
        self.measure(this);
        self.emit('sync');
        self.emit('analyze');
      });
      this.$slug.on('input', function() {
        self.emit('sync');
        self.pushSlugToWP(self.$slug.val());
        self.emit('analyze');
      });

      /* focus keyword drives both analysis and suggested links */
      $('#cwpKeyword').on('input', function() {
        self.emit('analyze');
        self.emit('renderLinks');
      });

      /* JSON-LD toggle */
      var $jsonBtn = $('#cwpJsonToggle');
      if ($jsonBtn.length && this.$jsonPre.length) {
        var $pre = this.$jsonPre;
        $jsonBtn.on('click', function() {
          var open = $pre.prop('hidden');
          $pre.prop('hidden', !open);
          $jsonBtn.text(open ? crawlwpSEO.i18n.hideJsonLd : crawlwpSEO.i18n.showJsonLd);
        });
      }

      /* schema type / fields update the preview */
      if (this.$schemaType.length) {
        this.$schemaType.on('change', function() { self.updateSchemaPreview(); });
      }
      if (this.$schemaHeadline.length) this.$schemaHeadline.on('input', function() { self.updateSchemaPreview(); });
      if (this.$schemaSection.length) this.$schemaSection.on('input', function() { self.updateSchemaPreview(); });

      /* image pickers */
      this.bindImagePickers();

      /* AI generate buttons */
      this.$mb.find('.cwp-ai-btn').on('click', function() {
        var targetId = $(this).data('aiTarget');
        var field = targetId === 'cwpTitle' ? 'title' : 'description';
        self.aiGenerate(field, $(this));
      });
    },

    /* ---------- pixel measurement ---------- */
    widthOf: function(text, font) {
      this.ctx.font = font;
      return Math.round(this.ctx.measureText(text).width);
    },

    measure: function(el) {
      var $el = $(el);
      var raw = $el.val();
      /* If this is the SEO title field and it is empty, assume the default template */
      if (!raw && $el.attr('id') === 'cwpTitle') {
        raw = '{{title}} {{sep}} {{sitename}}';
      }
      var text  = this.resolve(raw);
      var limit = parseInt($el.data('limit'), 10);
      var px    = this.widthOf(text, $el.data('font'));
      var pct   = Math.min(100, Math.round(px / limit * 100));
      var $fill  = $('#' + $el.data('meter') + 'Fill');
      var $label = $('#' + $el.data('meter'));

      $fill.css('width', pct + '%').removeClass('is-good is-over');
      var state = 'short';
      if (px > limit) { $fill.addClass('is-over'); state = 'over'; }
      else if (pct >= 70) { $fill.addClass('is-good'); state = 'good'; }

      var L = crawlwpSEO.i18n;
      var words = { short: L.meterTooShort, good: L.meterGoodLength, over: L.meterWillBeCut }[state];
      $label.html('<b>' + words + '</b> \u00b7 ' + this.fmt(L.meterDetail, px, limit, text.length));
    },

    measureAll: function() {
      var self = this;
      this.$mb.find('[data-meter]').each(function() { self.measure(this); });
    },

    /* ---------- live preview ---------- */
    sync: function() {
      var L = crawlwpSEO.i18n;
      var titleVal = this.$title.val() || '{{title}} {{sep}} {{sitename}}';
      var t = this.resolve(titleVal) || crawlwpSEO.postTitle || L.enterTitle;
      var d = this.resolve(this.$desc.val())  || crawlwpSEO.excerpt || L.addMetaDesc;

      $('#cwpSerpTitle').text(t);
      $('#cwpSerpDesc').text(d);

      var urlParts = crawlwpSEO.siteUrl.replace(/^https?:\/\//, '').replace(/\/$/, '');
      $('#cwpSerpUrl').text(urlParts + ' \u203a ' + (this.$slug.val() || '\u2026'));
      $('#cwpSlugEcho').text(this.$slug.val() || '\u2026');

      /* social mirrors */
      var fbT = this.$fbSync.prop('checked') ? t : (this.$fbTitle.val() || t);
      var fbD = this.$fbSync.prop('checked') ? d : (this.$fbDesc.val()  || d);
      $('#cwpFbTitlePrev').text(fbT);
      $('#cwpFbDescPrev').text(fbD);

      $('#cwpXTitlePrev').text(this.$xSync.prop('checked') ? fbT : (this.$xTitle.val() || fbT));
      $('#cwpXDescPrev').text(this.$xSync.prop('checked') ? fbD : (this.$xDesc.val()  || fbD));
    },

    toggleSync: function() {
      this.$fbTitle.prop('disabled', this.$fbSync.prop('checked'));
      this.$fbDesc.prop('disabled', this.$fbSync.prop('checked'));
      this.$xTitle.prop('disabled', this.$xSync.prop('checked'));
      this.$xDesc.prop('disabled', this.$xSync.prop('checked'));
      this.emit('sync');
    },

    updateSchemaPreview: function() {
      if (!this.$jsonPre.length) return;
      var type = this.$schemaType.length ? this.$schemaType.val() : 'Article';
      if (type === 'None \u2014 output nothing') {
        this.$jsonPre.text(crawlwpSEO.i18n.noStructuredData);
        return;
      }
      var headline = (this.$schemaHeadline.length && this.$schemaHeadline.val()) || this.resolve(this.$title.val()) || crawlwpSEO.postTitle;
      var section  = this.$schemaSection.length ? this.$schemaSection.val() : '';
      var schema = {
        '@context': 'https://schema.org',
        '@type': type,
        'headline': headline
      };
      if (section) schema.articleSection = section;
      schema.author = { '@type': 'Person', 'name': crawlwpSEO.author || '' };
      schema.datePublished = new Date().toISOString().slice(0, 10);
      this.$jsonPre.text(JSON.stringify(schema, null, 2));
    },

    /* ---------- slug push (metabox -> WP) ---------- */
    pushSlugToWP: function(val) {
      this._slugSyncLock = true;
      /* Classic editor */
      $('#post_name').val(val);
      $('#editable-post-name').text(val);
      $('#editable-post-name-full').text(val);
      /* Gutenberg */
      if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
        wp.data.dispatch('core/editor').editPost({ slug: val });
      }
      this._slugSyncLock = false;
    },

    /* ---------- watch WP post title -> metabox ---------- */
    watchPostTitle: function() {
      var self = this;

      /* Classic editor */
      var $wpTitle = $('#title');
      if ($wpTitle.length) {
        $wpTitle.on('input', function() {
          self.TOKENS['{{title}}'] = $wpTitle.val();
          crawlwpSEO.postTitle = $wpTitle.val();
          self.emit('measure');
          self.emit('sync');
          self.emit('analyze');
        });
      }

      /* Gutenberg */
      if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe && wp.data.select('core/editor')) {
        var lastTitle = this.TOKENS['{{title}}'];
        wp.data.subscribe(function() {
          var sel = wp.data.select('core/editor');
          if (!sel) return;
          var newTitle = sel.getEditedPostAttribute('title');
          if (newTitle !== undefined && newTitle !== lastTitle) {
            lastTitle = newTitle;
            self.TOKENS['{{title}}'] = newTitle;
            crawlwpSEO.postTitle = newTitle;
            self.emit('measure');
            self.emit('sync');
            self.emit('analyze');
          }
        });
      }
    },

    /* ---------- two-way slug sync (WP -> metabox) ---------- */
    watchPostSlug: function() {
      var self = this;

      /* Classic editor: #post_name */
      var wpSlugInput = document.getElementById('post_name');
      if (wpSlugInput) {
        new MutationObserver(function() {
          if (self._slugSyncLock) return;
          var val = wpSlugInput.value;
          if (val && val !== self.$slug.val()) {
            self.$slug.val(val);
            self.emit('sync');
            self.emit('analyze');
          }
        }).observe(wpSlugInput, { attributes: true, attributeFilter: ['value'] });
        $(wpSlugInput).on('change', function() {
          if (self._slugSyncLock) return;
          if (wpSlugInput.value && wpSlugInput.value !== self.$slug.val()) {
            self.$slug.val(wpSlugInput.value);
            self.emit('sync');
            self.emit('analyze');
          }
        });
      }

      /* Classic editor: editable slug span */
      var editSlug = document.getElementById('editable-post-name');
      if (editSlug) {
        new MutationObserver(function() {
          if (self._slugSyncLock) return;
          var val = $.trim($(editSlug).text());
          if (val && val !== self.$slug.val()) {
            self.$slug.val(val);
            self.emit('sync');
            self.emit('analyze');
          }
        }).observe(editSlug, { childList: true, characterData: true, subtree: true });
      }

      /* Gutenberg */
      if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe && wp.data.select('core/editor')) {
        var lastSlug = this.$slug.val();
        wp.data.subscribe(function() {
          if (self._slugSyncLock) return;
          var sel = wp.data.select('core/editor');
          if (!sel) return;
          var newSlug = sel.getEditedPostAttribute('slug');
          if (newSlug !== undefined && newSlug !== lastSlug) {
            lastSlug = newSlug;
            self.$slug.val(newSlug);
            self.emit('sync');
            self.emit('analyze');
          }
        });
      }
    },

    /* ---------- watch WP post content -> metabox ---------- */
    watchPostContent: function() {
      var self = this;
      var _contentDebounce = null;
      function onContentChange() {
        clearTimeout(_contentDebounce);
        _contentDebounce = setTimeout(function() {
          self.emit('renderLinks');
          self.emit('analyze');
        }, 500);
      }

      /* Classic editor: TinyMCE */
      if (typeof tinymce !== 'undefined') {
        var tryBind = function() {
          var ed = tinymce.get('content');
          if (ed) {
            ed.on('input change keyup Undo Redo', onContentChange);
          } else {
            setTimeout(tryBind, 500);
          }
        };
        tryBind();
      }

      /* Classic editor: plain-text textarea fallback */
      var $contentTA = $('#content');
      if ($contentTA.length) {
        $contentTA.on('input', onContentChange);
      }

      /* Gutenberg */
      if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe && wp.data.select('core/editor')) {
        var lastContent = '';
        var sel = wp.data.select('core/editor');
        if (sel) {
          lastContent = sel.getEditedPostContent() || '';
        }
        wp.data.subscribe(function() {
          var s = wp.data.select('core/editor');
          if (!s) return;
          var newContent = s.getEditedPostContent() || '';
          if (newContent !== lastContent) {
            lastContent = newContent;
            onContentChange();
          }
        });
      }
    },

    /* ---------- AI generation ---------- */
    aiGenerate: function(field, $btn) {
      var self = this;
      var L = crawlwpSEO.i18n;
      var $target = field === 'title' ? this.$title : this.$desc;

      if ($btn.hasClass('is-loading')) return;

      $btn.addClass('is-loading').prop('disabled', true);
      var origHtml = $btn.html();
      $btn.html('<span class="cwp-ai-spinner"></span> ' + L.aiGenerating);

      var postTitle = crawlwpSEO.postTitle || '';
      if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
        var sel = wp.data.select('core/editor');
        if (sel) postTitle = sel.getEditedPostAttribute('title') || postTitle;
      } else {
        var $wpTitle = $('#title');
        if ($wpTitle.length) postTitle = $wpTitle.val() || postTitle;
      }

      var content = this.getEditorContent();
      var keyword = $('#cwpKeyword').val() || '';

      $.ajax({
        url: crawlwpSEO.ajaxUrl,
        type: 'POST',
        data: {
          action: 'crawlwp_ai_generate',
          nonce: crawlwpSEO.aiNonce,
          post_id: crawlwpSEO.postId,
          field: field,
          post_title: postTitle,
          post_content: content,
          focus_keyword: keyword
        },
        success: function(resp) {
          if (resp.success && resp.data && resp.data.text) {
            $target.val(resp.data.text).trigger('input');
          } else {
            self.aiShowError($btn, L.aiError);
          }
        },
        error: function() {
          self.aiShowError($btn, L.aiError);
        },
        complete: function() {
          $btn.removeClass('is-loading').prop('disabled', false).html(origHtml);
        }
      });
    },

    aiShowError: function($btn, msg) {
      var $row = $btn.closest('.cwp-label-row');
      var $err = $row.find('.cwp-ai-error');
      if (!$err.length) {
        $err = $('<span class="cwp-ai-error"></span>').appendTo($row);
      }
      $err.text(msg).show();
      setTimeout(function() { $err.fadeOut(300); }, 3000);
    },

    /* ---------- image pickers (WP media) ---------- */
    bindImagePickers: function() {
      var self = this;

      this.$mb.find('.cwp-img-pick-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var target   = $btn.data('target');
        var $inputEl = $('#' + target);
        var $thumbEl = $btn.closest('.cwp-img-picker').find('.cwp-img-thumb');

        var frame = wp.media({
          title: crawlwpSEO.i18n.selectImage,
          multiple: false,
          library: { type: 'image' }
        });

        frame.on('select', function() {
          var attachment = frame.state().get('selection').first().toJSON();
          $inputEl.val(attachment.id);
          if ($thumbEl.length && attachment.url) {
            $thumbEl.css('backgroundImage', 'url(' + attachment.url + ')').removeClass('cwp-thumb-empty');
          }
          self.updateSocialPreviewImage(target, attachment.url);
        });

        frame.open();
      });

      this.$mb.find('.cwp-img-remove-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var target  = $btn.data('target');
        var $inputEl = $('#' + target);
        var $thumbEl = $btn.closest('.cwp-img-picker').find('.cwp-img-thumb');
        $inputEl.val('');
        if ($thumbEl.length) {
          var fi = crawlwpSEO.featuredImageUrl || '';
          if (fi) {
            $thumbEl.css('backgroundImage', 'url(' + fi + ')').removeClass('cwp-thumb-empty');
          } else {
            $thumbEl.css('backgroundImage', '').addClass('cwp-thumb-empty');
          }
        }
        self.updateSocialPreviewImage(target, '');
      });
    },

    updateSocialPreviewImage: function(targetId, imageUrl) {
      if (targetId === 'cwpOgImage') {
        var $fbCard = this.$mb.find('.cwp-fb-card .cwp-og-img');
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
          var $xCard = this.$mb.find('.cwp-x-card .cwp-og-img');
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
        var $xCard2 = this.$mb.find('.cwp-x-card .cwp-og-img');
        if ($xCard2.length) {
          if (imageUrl) {
            $xCard2.css('backgroundImage', 'url(' + imageUrl + ')').text('');
          } else {
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
    },

    /* ---------- featured image for social placeholders ---------- */
    initSocialImagePlaceholders: function() {
      var featuredUrl = crawlwpSEO.featuredImageUrl || '';
      this.$mb.find('.cwp-img-picker').each(function() {
        var $picker = $(this);
        var $thumb = $picker.find('.cwp-img-thumb');
        var $input = $picker.find('input[type="hidden"]');
        if (!$thumb.length || !$input.length) return;
        if ($input.val()) return;
        if (featuredUrl) {
          $thumb.css('backgroundImage', 'url(' + featuredUrl + ')').removeClass('cwp-thumb-empty');
        } else {
          $thumb.css('backgroundImage', '').addClass('cwp-thumb-empty');
        }
      });
    },

    watchFeaturedImage: function() {
      var self = this;
      if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe && wp.data.select('core/editor')) {
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
          self.initSocialImagePlaceholders();
        });
      }
    },

    /* ---------- helpers ---------- */
    /* simple sprintf: replaces %s, %d, %1$s, %2$s … with positional args */
    fmt: function(str) {
      var args = Array.prototype.slice.call(arguments, 1);
      var i = 0;
      return str.replace(/%(?:(\d+)\$)?[sd]/g, function(m, num) {
        if (num) { var idx = parseInt(num, 10) - 1; return args[idx] !== undefined ? args[idx] : ''; }
        return args[i] !== undefined ? args[i++] : '';
      });
    },

    escHtml: function(str) {
      return $('<div>').text(str).html();
    },

    stripTags: function(html) {
      return $('<div>').html(html).text();
    },

    getWordCount: function(text) {
      return text.split(/\s+/).filter(function(w) { return w.length > 0; }).length;
    },

    getEditorContent: function() {
      /* Try TinyMCE first, then Gutenberg data store, then DOM fallbacks */
      if (typeof tinymce !== 'undefined') {
        var ed = tinymce.get('content');
        if (ed && !ed.isHidden()) return ed.getContent();
      }
      /* Gutenberg: the block editor canvas is iframed, so DOM scraping fails.
         Use the data store which returns clean serialized post content. */
      if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
        var sel = wp.data.select('core/editor');
        if (sel && typeof sel.getEditedPostContent === 'function') {
          var content = sel.getEditedPostContent();
          if (content) return content;
        }
      }
      var $wpBlock = $('.block-editor-block-list__layout');
      if ($wpBlock.length) return $wpBlock.html();
      var $ta = $('#content');
      if ($ta.length) return $ta.val();
      return crawlwpSEO.postContent || '';
    },

    parseLinks: function(html) {
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
    },

    /* ---------- show-all toggle helpers ---------- */
    addShowAll: function($container, total) {
      var $btn = $('<a>', {
        href: '#',
        'class': 'cwp-show-all-link',
        text: this.fmt(crawlwpSEO.i18n.showAllLinks, total)
      }).on('click', function(e) {
        e.preventDefault();
        $container.find('.cwp-link-hidden').removeClass('cwp-link-hidden');
        $btn.remove();
      });
      $container.append($btn);
    },

    removeShowAll: function($container) {
      $container.find('.cwp-show-all-link').remove();
    },

    /* ---------- links panel ---------- */
    renderLinks: function() {
      var self = this;
      var html = this.getEditorContent();
      var parsed = this.parseLinks(html);
      var outTotal = parsed.internal.length + parsed.external.length;
      var inbound = crawlwpSEO.inboundLinks || [];

      $('#cwpLinksOut').text(outTotal);
      $('#cwpLinksIn').text(inbound.length);
      $('#cwpLinksExt').text(parsed.external.length);

      /* notice */
      var $notice = $('#cwpLinksNotice');
      var $noticeText = $('#cwpLinksNoticeText');
      var $linksDot = this.$mb.find('.cwp-dot-links');
      if (parsed.internal.length === 0) {
        $notice.prop('hidden', false);
        $noticeText.text(crawlwpSEO.i18n.noInternalLinks);
        $linksDot.prop('hidden', false);
      } else {
        $notice.prop('hidden', true);
        $linksDot.prop('hidden', true);
      }

      /* outbound list */
      var $outList = $('#cwpOutboundLinks');
      var $outEmpty = $('#cwpOutboundEmpty');
      $outList.find('.cwp-link-item').remove();
      this.removeShowAll($outList);
      if (outTotal === 0) {
        $outEmpty.prop('hidden', false);
      } else {
        $outEmpty.prop('hidden', true);
        var all = parsed.internal.map(function(l) { l.type = 'internal'; return l; })
          .concat(parsed.external.map(function(l) { l.type = 'external'; return l; }));
        $.each(all, function(idx, link) {
          var chipClass = link.type === 'internal' ? 'is-good' : 'is-muted';
          var chipLabel = link.type === 'internal' ? crawlwpSEO.i18n.internal : crawlwpSEO.i18n.external;
          var $div = $('<div>', { 'class': 'cwp-link-item' });
          if (idx >= 6) $div.addClass('cwp-link-hidden');
          $div.html('<div class="cwp-link-main">' +
            '<a class="cwp-link-title" href="' + link.href + '" target="_blank">' + self.escHtml(link.text) + '</a>' +
            '<div class="cwp-link-meta">' + self.escHtml(link.href) + '</div>' +
            '</div>' +
            '<div class="cwp-link-side"><span class="cwp-chip ' + chipClass + '">' + chipLabel + '</span></div>');
          $outList.append($div);
        });
        if (all.length > 6) this.addShowAll($outList, all.length);
      }

      /* inbound list */
      var $inList = $('#cwpInboundLinks');
      var $inEmpty = $('#cwpInboundEmpty');
      $inList.find('.cwp-link-item').remove();
      this.removeShowAll($inList);
      if (inbound.length === 0) {
        $inEmpty.prop('hidden', false);
      } else {
        $inEmpty.prop('hidden', true);
        $.each(inbound, function(idx, link) {
          var $div = $('<div>', { 'class': 'cwp-link-item is-inbound' });
          if (idx >= 6) $div.addClass('cwp-link-hidden');
          var anchorInfo = link.anchor ? self.fmt(crawlwpSEO.i18n.anchorLabel, self.escHtml(link.anchor)) + ' \u00b7 ' : '';
          $div.html('<div class="cwp-link-main">' +
            '<a class="cwp-link-title" href="' + self.escHtml(link.url) + '" target="_blank">' + self.escHtml(link.title) + '</a>' +
            '<div class="cwp-link-meta">' + anchorInfo + self.fmt(crawlwpSEO.i18n.publishedDate, self.escHtml(link.date)) + '</div>' +
            '</div>');
          $inList.append($div);
        });
        if (inbound.length > 6) this.addShowAll($inList, inbound.length);
      }

      /* suggested links */
      var suggested = crawlwpSEO.suggestedLinks || [];
      var $sugList = $('#cwpSuggestedLinks');
      var $sugEmpty = $('#cwpSuggestedEmpty');
      $sugList.find('.cwp-link-item').remove();
      this.removeShowAll($sugList);
      if (suggested.length === 0) {
        $sugEmpty.prop('hidden', false);
      } else {
        $sugEmpty.prop('hidden', true);
        $.each(suggested, function(idx, link) {
          var $div = $('<div>', { 'class': 'cwp-link-item is-suggested' });
          if (idx >= 6) $div.addClass('cwp-link-hidden');
          $div.html('<div class="cwp-link-main">' +
            '<a class="cwp-link-title" href="' + self.escHtml(link.url) + '" target="_blank">' + self.escHtml(link.title) + '</a>' +
            '<div class="cwp-link-meta">' + self.escHtml(link.url) + ' \u00b7 ' + self.escHtml(link.date) + '</div>' +
            '</div>' +
            '<div class="cwp-link-side">' +
              '<button class="cwp-copy-url-btn" type="button" data-url="' + self.escHtml(link.url) + '" title="' + crawlwpSEO.i18n.copyUrl + '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>' +
              '<span class="cwp-chip is-info">' + crawlwpSEO.i18n.suggested + '</span>' +
            '</div>');
          $sugList.append($div);
        });
        if (suggested.length > 6) this.addShowAll($sugList, suggested.length);

        /* bind copy buttons */
        $sugList.find('.cwp-copy-url-btn').on('click', function(e) {
          e.preventDefault();
          var $btn = $(this);
          var url = $btn.attr('data-url');
          if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
              $btn.addClass('is-copied').attr('title', crawlwpSEO.i18n.copied);
              setTimeout(function() { $btn.removeClass('is-copied').attr('title', crawlwpSEO.i18n.copyUrl); }, 1500);
            });
          } else {
            var $ta = $('<textarea>').val(url).css({ position: 'fixed', left: '-9999px' }).appendTo('body');
            $ta[0].select();
            document.execCommand('copy');
            $ta.remove();
            $btn.addClass('is-copied').attr('title', crawlwpSEO.i18n.copied);
            setTimeout(function() { $btn.removeClass('is-copied').attr('title', crawlwpSEO.i18n.copyUrl); }, 1500);
          }
        });
      }
    },

    /* ---------- analysis panel ---------- */
    runAnalysis: function() {
      var self = this;
      var keyword = $.trim($('#cwpKeyword').val()).toLowerCase();
      var $checklist = $('#cwpChecklist');
      var $noticeText = $('#cwpAnalysisNoticeText');
      var $analysisDot = this.$mb.find('.cwp-dot-analysis');

      $checklist.empty();

      var L = crawlwpSEO.i18n;

      if (!keyword) {
        $noticeText.text(L.enterFocusKw);
        $analysisDot.prop('hidden', true);
        this.updateScore(0, 0);
        return;
      }

      $noticeText.html(this.fmt(L.scoredAgainst, '<b>' + this.escHtml(keyword) + '</b>'));

      var seoTitle = this.resolve(this.$title.val() || '{{title}} {{sep}} {{sitename}}').toLowerCase();
      var seoDesc = this.resolve(this.$desc.val() || '').toLowerCase();
      var slugVal = (this.$slug.val() || '').toLowerCase();
      var html = this.getEditorContent();
      var plainText = this.stripTags(html).toLowerCase();
      var wordCount = this.getWordCount(plainText);
      var parsed = this.parseLinks(html);

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
          addCheck('good', L.kwInTitleGood, L.kwInTitleStart);
        } else {
          addCheck('good', L.kwInTitleGood, L.kwInTitleMove);
        }
      } else {
        addCheck('bad', L.kwInTitleBad, L.kwInTitleFix);
      }

      /* 2. Keyword in URL slug */
      if (slugVal.indexOf(keyword.replace(/\s+/g, '-')) !== -1 || slugVal.indexOf(keyword.replace(/\s+/g, '')) !== -1) {
        addCheck('good', L.kwInSlugGood, '');
      } else {
        addCheck('bad', L.kwInSlugBad, L.kwInSlugFix);
      }

      /* 3. Title length (pixel width) */
      var titleText = this.resolve(this.$title.val() || '{{title}} {{sep}} {{sitename}}');
      var titlePx = this.widthOf(titleText, 'bold 20px Arial');
      if (titlePx >= 200 && titlePx <= 580) {
        addCheck('good', L.titleLenGood, this.fmt(L.titleLenDetail, titlePx));
      } else if (titlePx > 580) {
        addCheck('warn', L.titleLenLong, this.fmt(L.titleLenLongD, titlePx));
      } else {
        addCheck('warn', L.titleLenShort, this.fmt(L.titleLenShortD, titlePx));
      }

      /* 4. Meta description */
      if (seoDesc.length > 0) {
        if (seoDesc.indexOf(keyword) !== -1) {
          addCheck('good', L.kwInDescGood, '');
        } else {
          addCheck('warn', L.kwInDescWarn, L.kwInDescWarnD);
        }
      } else {
        addCheck('bad', L.noDescBad, L.noDescFix);
      }

      /* 5. Meta description length */
      if (seoDesc.length > 0) {
        var descPx = this.widthOf(this.resolve(this.$desc.val()), '14px Arial');
        if (descPx >= 400 && descPx <= 920) {
          addCheck('good', L.descLenGood, this.fmt(L.descLenGoodD, descPx));
        } else if (descPx > 920) {
          addCheck('warn', L.descLenLong, this.fmt(L.descLenLongD, descPx));
        } else {
          addCheck('warn', L.descLenShort, L.descLenShortD);
        }
      }

      /* 6. Keyword in first paragraph */
      if (firstParaText && firstParaText.indexOf(keyword) !== -1) {
        addCheck('good', L.kwFirstParaGood, '');
      } else if (plainText.length > 0) {
        addCheck('warn', L.kwFirstParaWarn, L.kwFirstParaFix);
      }

      /* 7. Keyword in subheadings */
      if (headingsWithKw >= 2) {
        addCheck('good', this.fmt(L.kwSubheadGood, headingsWithKw), '');
      } else if (headingsWithKw === 1) {
        addCheck('warn', L.kwSubheadOne, L.kwSubheadOneFix);
      } else if ($headings.length > 0) {
        addCheck('bad', L.kwSubheadBad, L.kwSubheadFix);
      }

      /* 8. H1 check */
      if ($h1s.length === 1) {
        addCheck('good', L.h1Good, '');
      } else if ($h1s.length === 0) {
        addCheck('warn', L.h1None, L.h1NoneFix);
      } else {
        addCheck('warn', this.fmt(L.h1Multiple, $h1s.length), L.h1MultipleFix);
      }

      /* 9. Images alt text */
      if ($images.length === 0) {
        addCheck('warn', L.noImages, L.noImagesFix);
      } else if (imagesNoAlt === 0) {
        addCheck('good', L.allImgAlt, this.fmt(L.imgAltDetail, $images.length));
      } else {
        addCheck('bad', this.fmt(L.imgAltMissing, imagesNoAlt), L.imgAltFix);
      }

      /* 10. Keyword in image alt */
      if ($images.length > 0) {
        if (keywordInAlt) {
          addCheck('good', L.kwImgAltGood, '');
        } else {
          addCheck('warn', L.kwImgAltWarn, L.kwImgAltFix);
        }
      }

      /* 11. Internal links */
      if (parsed.internal.length >= 2) {
        addCheck('good', this.fmt(L.intLinksGood, parsed.internal.length), L.intLinksGoodD);
      } else if (parsed.internal.length === 1) {
        addCheck('warn', L.intLinksOne, L.intLinksOneFix);
      } else {
        addCheck('bad', L.intLinksNone, L.intLinksNoneFix);
      }

      /* 12. External links */
      if (parsed.external.length >= 1) {
        addCheck('good', this.fmt(L.extLinksGood, parsed.external.length), L.extLinksGoodD);
      } else {
        addCheck('warn', L.extLinksNone, L.extLinksNoneFix);
      }

      /* 13. Content length */
      if (wordCount >= 300) {
        addCheck('good', this.fmt(L.wordsLabel, wordCount.toLocaleString()), L.wordsEnough);
      } else if (wordCount >= 100) {
        addCheck('warn', this.fmt(L.wordsLabel, wordCount.toLocaleString()), L.wordsAim300);
      } else {
        addCheck('bad', this.fmt(L.wordsLabel, wordCount.toLocaleString()), L.wordsThin);
      }

      /* 14. Keyword density */
      if (wordCount > 50) {
        if (density >= 0.5 && density <= 3.0) {
          addCheck('good', this.fmt(L.densityLabel, density.toFixed(1)), L.densityGoodD);
        } else if (density > 3.0) {
          addCheck('warn', this.fmt(L.densityLabel, density.toFixed(1)), L.densityHighD);
        } else {
          addCheck('warn', this.fmt(L.densityLabel, density.toFixed(1)), L.densityLowD);
        }
      }

      /* 15. Readability: avg sentence length */
      if (sentences.length >= 3) {
        if (avgSentenceLen <= 20) {
          addCheck('good', this.fmt(L.readability, avgSentenceLen), L.readabilityGoodD);
        } else if (avgSentenceLen <= 25) {
          addCheck('warn', this.fmt(L.readability, avgSentenceLen), L.readabilityWarnD);
        } else {
          addCheck('bad', this.fmt(L.readability, avgSentenceLen), L.readabilityBadD);
        }
      }

      /* 16. Heading hierarchy (uses H2s) */
      if ($h2s.length >= 2) {
        addCheck('good', this.fmt(L.h2Good, $h2s.length), '');
      } else if ($h2s.length === 1) {
        addCheck('warn', L.h2One, L.h2OneFix);
      } else if (wordCount > 300) {
        addCheck('warn', L.h2None, L.h2NoneFix);
      }

      /* render */
      var symbols = { good: '\u2713', warn: '!', bad: '\u2715' };
      $.each(checks, function(i, c) {
        var $div = $('<div>', { 'class': 'cwp-checkitem' });
        $div.html('<span class="cwp-badge is-' + c.status + '">' + symbols[c.status] + '</span>' +
          '<span class="cwp-checktext"><b>' + self.escHtml(c.bold) + '</b>' +
          (c.detail ? ' ' + self.escHtml(c.detail) : '') + '</span>');
        $checklist.append($div);
      });

      /* update dot */
      var issues = total - passed;
      if ($analysisDot.length) {
        if (issues > 0) {
          $analysisDot.prop('hidden', false).attr('title', this.fmt(L.issueCount, issues));
        } else {
          $analysisDot.prop('hidden', true);
        }
      }

      this.updateScore(passed, total);
    },

    updateScore: function(passed, total) {
      var pct = total > 0 ? Math.round(passed / total * 100) : 0;
      var $wrap = this.$mb.closest('#crawlwp-seo-metabox');
      if (!$wrap.length) $wrap = this.$mb;
      var $ring = $wrap.find('.cwp-fill');
      var $num = $wrap.find('.cwp-score-num');
      if ($ring.length) $ring.attr('stroke-dasharray', pct + ' 100');
      if ($num.length) $num.text(total > 0 ? pct : '\u2014');
    },

    /* ---------- insights panel (pro only) ---------- */
    initInsights: function() {
      if (!crawlwpSEO.isProActive) return;

      var self = this;
      var $insightsPeriod = $('#cwpInsightsPeriod');
      var _insightsLoading = false;
      var _insightsCache = {};
      var _currentEngine = 'google';
      var engineLabels = { google: 'Google', bing: 'Bing', yandex: 'Yandex' };
      var L = crawlwpSEO.i18n;

      function populateInsightsForEngine(data, engine) {
        var label = engineLabels[engine] || engine;
        $('#cwpInsClicks').text(data.clicks !== undefined ? Number(data.clicks).toLocaleString() : '\u2014');
        $('#cwpInsImpressions').text(data.impressions !== undefined ? Number(data.impressions).toLocaleString() : '\u2014');
        $('#cwpInsPosition').text(data.position !== undefined ? Number(data.position).toFixed(1) : '\u2014');
        $('#cwpInsCTR').text(data.ctr !== undefined ? (Number(data.ctr) * 100).toFixed(1) + '%' : '\u2014');

        /* Update keywords description */
        $('#cwpKeywordsDesc').text(self.fmt(L.insightsQueriesDesc, label));

        /* keywords table */
        var $tbody = $('#cwpInsightsKeywordsBody');
        if ($tbody.length && data.keywords && data.keywords.length > 0) {
          $tbody.empty();
          $.each(data.keywords, function(i, kw) {
            var $tr = $('<tr>').html(
              '<td>' + self.escHtml(kw.keyword) + '</td>' +
              '<td>' + (kw.clicks || 0) + '</td>' +
              '<td>' + (kw.impressions || 0) + '</td>' +
              '<td>' + (kw.position ? Number(kw.position).toFixed(1) : '\u2014') + '</td>' +
              '<td>' + (kw.ctr ? (Number(kw.ctr) * 100).toFixed(1) + '%' : '\u2014') + '</td>'
            );
            $tbody.append($tr);
          });
        } else if ($tbody.length && (!data.keywords || data.keywords.length === 0)) {
          $tbody.html('<tr><td colspan="5" class="cwp-empty-msg">' + L.insightsNoKw + '</td></tr>');
        }

        /* indexing status */
        $('#cwpEngineIndexLabel').text(self.fmt(L.insightsIndex, label));
        var $engineIndex = $('#cwpEngineIndex');
        if ($engineIndex.length && data.indexStatus !== undefined) {
          $engineIndex.text(data.indexStatus ? L.indexed : L.notIndexed)
            .attr('class', 'cwp-chip ' + (data.indexStatus ? 'is-good' : 'is-warn'));
        }
        if (data.lastCrawled) $('#cwpLastCrawled').text(data.lastCrawled);
        if (data.indexNowSubmitted) $('#cwpIndexNowStatus').text(data.indexNowSubmitted);
      }

      function loadInsights(days) {
        var postId = crawlwpSEO.postId || 0;
        if (!postId) {
          $('#cwpInsightsNoticeText').text(L.savPostFirst);
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

      /* Engine tab switching */
      var $engineTabs = this.$mb.find('.cwp-engine-tab');
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
      this.$mb.find('.cwp-tab').on('click', function() {
        if ($(this).data('panel') === 'insights') {
          var period = $insightsPeriod.length ? $insightsPeriod.val() : '28';
          loadInsights(period);
        }
      });
    }

  };

  window.CrawlWPMetabox = CrawlWP;

  $(function() { CrawlWP.init(); });

})(jQuery);
