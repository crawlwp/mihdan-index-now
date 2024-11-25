<?php

if ( ! class_exists('\ProperP_Shogun')) {

    class ProperP_Shogun
    {
        public function __construct()
        {
            if (is_admin()) {

                add_filter('install_plugins_table_api_args_featured', function ($args) {
                    add_filter('plugins_api_result', [$this, 'plugins_api_result'], 9999, 3);

                    return $args;
                });
            }
        }

        public function plugins_api_result($res, $action, $args)
        {
            remove_filter('plugins_api_result', [$this, 'plugins_api_result'], 9999);

            $res = $this->add_plugin_favs('rate-my-post', $res);
            $res = $this->add_plugin_favs('fusewp', $res);
            $res = $this->add_plugin_favs('mihdan-index-now', $res);
            $res = $this->add_plugin_favs('mailoptin', $res);
            $res = $this->add_plugin_favs('wp-user-avatar', $res);

            return $res;
        }

        public function add_plugin_favs($plugin_slug, $res)
        {
            if ( ! empty($res->plugins) && is_array($res->plugins)) {
                foreach ($res->plugins as $plugin) {
                    if (is_object($plugin) && ! empty($plugin->slug) && $plugin->slug == $plugin_slug) {
                        return $res;
                    }
                }
            }

            if ($plugin_info = get_transient('yolo-plugin-info-' . $plugin_slug)) {
                array_unshift($res->plugins, $plugin_info);
            } else {
                $plugin_info = plugins_api('plugin_information', array(
                    'slug'   => $plugin_slug,
                    'is_ssl' => is_ssl(),
                    'fields' => array(
                        'banners'           => true,
                        'reviews'           => true,
                        'downloaded'        => true,
                        'active_installs'   => true,
                        'icons'             => true,
                        'short_description' => true,
                    )
                ));
                if ( ! is_wp_error($plugin_info)) {
                    $res->plugins[] = $plugin_info;
                    set_transient('yolo-plugin-info-' . $plugin_slug, $plugin_info, DAY_IN_SECONDS * 7);
                }
            }

            return $res;
        }

        /**
         * @return self
         */
        public static function get_instance()
        {
            static $instance = null;

            if (is_null($instance)) {
                $instance = new self();
            }

            return $instance;
        }
    }
}
