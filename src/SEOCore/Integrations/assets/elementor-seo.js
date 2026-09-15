/**
 * CrawlWP SEO - Elementor Integration
 */
(function($) {
  'use strict';

  var SEO = {
    init: function() {
      this.bindUIEvents();
    },

    bindUIEvents: function() {
      var self = this;

      // AI Generate action
      $(document).on('click', '#cwpElementorAiBtn', function(e) {
        e.preventDefault();
        self.handleAiGenerate($(this));
      });
    },

    getSetting: function(key) {
      if (window.elementor && elementor.settings && elementor.settings.page) {
        var val = elementor.settings.page.model.get(key);
        if (val !== undefined && val !== null) {
          if (typeof val === 'object' && val.url !== undefined) {
            return val.url;
          }
          return val;
        }
      }
      var $el = $('[data-setting="' + key + '"]');
      if ($el.length) {
        return $el.val() || '';
      }
      return '';
    },

    setSetting: function(key, value) {
      if (window.elementor && elementor.settings && elementor.settings.page) {
        elementor.settings.page.model.set(key, value);
      }
      var $el = $('[data-setting="' + key + '"]');
      if ($el.length) {
        $el.val(value).trigger('input').trigger('change');
      }
    },

    getEditorContent: function() {
      if (window.elementor && elementor.$previewContents) {
        var $body = elementor.$previewContents.find('.elementor-section-wrap, .elementor-inner, body');
        if ($body.length) {
          return $body.html() || '';
        }
      }
      return '';
    },

    stripTags: function(html) {
      return $('<div>').html(html).text();
    },

    handleAiGenerate: function($btn) {
      var self = this;
      var data = window.crawlwpSEO || {};
      if (!data.ajaxUrl || !data.aiNonce) return;

      var html = this.getEditorContent();
      var text = this.stripTags(html);
      var keyword = this.getSetting('_crawlwp_focus_keyword');
      var postTitle = data.postTitle || '';

      $btn.prop('disabled', true).text('Generating...');

      // Generate Title
      $.ajax({
        url: data.ajaxUrl,
        type: 'POST',
        data: {
          action: 'crawlwp_ai_generate',
          nonce: data.aiNonce,
          post_id: data.postId || 0,
          field: 'title',
          post_title: postTitle,
          post_content: text.slice(0, 4000),
          focus_keyword: keyword
        }
      }).done(function(res) {
        if (res.success && res.data && res.data.text) {
          self.setSetting('_crawlwp_seo_title', res.data.text);
        }
        // Also Generate Description
        $.ajax({
          url: data.ajaxUrl,
          type: 'POST',
          data: {
            action: 'crawlwp_ai_generate',
            nonce: data.aiNonce,
            post_id: data.postId || 0,
            field: 'description',
            post_title: postTitle,
            post_content: text.slice(0, 4000),
            focus_keyword: keyword
          }
        }).done(function(descRes) {
          if (descRes.success && descRes.data && descRes.data.text) {
            self.setSetting('_crawlwp_seo_description', descRes.data.text);
          }
        }).always(function() {
          $btn.prop('disabled', false).html(
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.09 6.26L20 10l-5.91 1.74L12 18l-2.09-6.26L4 10l5.91-1.74z"/><path d="M19 2l.87 2.61L22.5 5.5l-2.63.89L19 9l-.87-2.61L15.5 5.5l2.63-.89z"/></svg> Generate SEO with AI'
          );
        });
      }).fail(function() {
        $btn.prop('disabled', false).html(
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.09 6.26L20 10l-5.91 1.74L12 18l-2.09-6.26L4 10l5.91-1.74z"/><path d="M19 2l.87 2.61L22.5 5.5l-2.63.89L19 9l-.87-2.61L15.5 5.5l2.63-.89z"/></svg> Generate SEO with AI'
        );
      });
    }
  };

  $(function() {
    SEO.init();
  });

})(jQuery);
