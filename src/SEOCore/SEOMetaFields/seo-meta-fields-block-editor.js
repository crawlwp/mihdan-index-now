(function (wp) {
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var PanelBody = wp.components.PanelBody;
    var __ = wp.i18n.__;

    var META_PREFIX = '_crawlwp_';

    function CrawlWPSEOSidebar() {
        var meta = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);

        var editPost = useDispatch('core/editor').editPost;

        function updateMeta(key, value) {
            var newMeta = {};
            newMeta[META_PREFIX + key] = value;
            editPost({ meta: newMeta });
        }

        function getMeta(key, fallback) {
            var val = meta[META_PREFIX + key];
            return val !== undefined && val !== null ? val : (fallback !== undefined ? fallback : '');
        }

        return el(
            Fragment,
            null,
            el(PluginSidebarMoreMenuItem, { target: 'crawlwp-seo-sidebar', icon: 'search' },
                __('CrawlWP SEO', 'mihdan-index-now')
            ),
            el(
                PluginSidebar,
                {
                    name: 'crawlwp-seo-sidebar',
                    title: __('CrawlWP SEO Settings', 'mihdan-index-now'),
                    icon: 'search'
                },

                // General
                el(PanelBody, { title: __('General', 'mihdan-index-now'), initialOpen: true },
                    el(TextControl, {
                        label: __('Meta Title', 'mihdan-index-now'),
                        help: __('Custom title tag for this page. Leave empty to use the default.', 'mihdan-index-now'),
                        value: getMeta('meta_title'),
                        onChange: function (v) { updateMeta('meta_title', v); }
                    }),
                    el(TextareaControl, {
                        label: __('Meta Description', 'mihdan-index-now'),
                        help: __('Custom meta description for search engines.', 'mihdan-index-now'),
                        value: getMeta('meta_description'),
                        onChange: function (v) { updateMeta('meta_description', v); }
                    }),
                    el(TextControl, {
                        label: __('Canonical URL', 'mihdan-index-now'),
                        help: __('Set a custom canonical URL for this page.', 'mihdan-index-now'),
                        value: getMeta('canonical_url'),
                        onChange: function (v) { updateMeta('canonical_url', v); }
                    })
                ),

                // Robots
                el(PanelBody, { title: __('Robots', 'mihdan-index-now'), initialOpen: false },
                    el(ToggleControl, {
                        label: __('Set this page to noindex', 'mihdan-index-now'),
                        checked: !!getMeta('robots_noindex', false),
                        onChange: function (v) { updateMeta('robots_noindex', v); }
                    }),
                    el(ToggleControl, {
                        label: __('Set this page to nofollow', 'mihdan-index-now'),
                        checked: !!getMeta('robots_nofollow', false),
                        onChange: function (v) { updateMeta('robots_nofollow', v); }
                    })
                ),

                // Open Graph
                el(PanelBody, { title: __('Open Graph', 'mihdan-index-now'), initialOpen: false },
                    el(TextControl, {
                        label: __('OG Title', 'mihdan-index-now'),
                        value: getMeta('og_title'),
                        onChange: function (v) { updateMeta('og_title', v); }
                    }),
                    el(SelectControl, {
                        label: __('OG Type', 'mihdan-index-now'),
                        value: getMeta('og_type'),
                        options: [
                            { label: __('Default (article)', 'mihdan-index-now'), value: '' },
                            { label: 'article', value: 'article' },
                            { label: 'website', value: 'website' },
                            { label: 'product', value: 'product' },
                            { label: 'profile', value: 'profile' }
                        ],
                        onChange: function (v) { updateMeta('og_type', v); }
                    }),
                    el(TextareaControl, {
                        label: __('OG Description', 'mihdan-index-now'),
                        value: getMeta('og_description'),
                        onChange: function (v) { updateMeta('og_description', v); }
                    }),
                    el(TextControl, {
                        label: __('OG Image URL', 'mihdan-index-now'),
                        help: __('Recommended size: 1200×630 pixels.', 'mihdan-index-now'),
                        value: getMeta('og_image'),
                        onChange: function (v) { updateMeta('og_image', v); }
                    })
                ),

                // Twitter Card
                el(PanelBody, { title: __('Twitter Card', 'mihdan-index-now'), initialOpen: false },
                    el(SelectControl, {
                        label: __('Card Type', 'mihdan-index-now'),
                        value: getMeta('twitter_card'),
                        options: [
                            { label: __('Default (summary_large_image)', 'mihdan-index-now'), value: '' },
                            { label: 'summary', value: 'summary' },
                            { label: 'summary_large_image', value: 'summary_large_image' }
                        ],
                        onChange: function (v) { updateMeta('twitter_card', v); }
                    }),
                    el(TextControl, {
                        label: __('Twitter Title', 'mihdan-index-now'),
                        value: getMeta('twitter_title'),
                        onChange: function (v) { updateMeta('twitter_title', v); }
                    }),
                    el(TextareaControl, {
                        label: __('Twitter Description', 'mihdan-index-now'),
                        value: getMeta('twitter_description'),
                        onChange: function (v) { updateMeta('twitter_description', v); }
                    }),
                    el(TextControl, {
                        label: __('Twitter Image URL', 'mihdan-index-now'),
                        value: getMeta('twitter_image'),
                        onChange: function (v) { updateMeta('twitter_image', v); }
                    })
                )
            )
        );
    }

    registerPlugin('crawlwp-seo-meta-fields', {
        render: CrawlWPSEOSidebar,
        icon: 'search'
    });
})(window.wp);
