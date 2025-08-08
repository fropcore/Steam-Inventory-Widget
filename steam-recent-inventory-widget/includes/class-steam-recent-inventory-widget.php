<?php
if (!defined('ABSPATH')) exit;

class SRIW_Recent_Inventory_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'sriw_recent_inventory_widget',
            __('Steam Recent Inventory', 'sriw'),
            array('description' => __('Shows the 5 or 10 most recent TF2 or CS2 items in a player inventory (public profiles only).', 'sriw'))
        );
    }

    public function widget($args, $instance) {
        $title   = !empty($instance['title']) ? $instance['title'] : '';
        $steamid = !empty($instance['steamid']) ? $instance['steamid'] : '';
        $game    = !empty($instance['game']) ? $instance['game'] : 'cs2';
        $count   = !empty($instance['count']) ? intval($instance['count']) : 5;
        $lang    = !empty($instance['lang']) ? $instance['lang'] : 'english';

        $appid = strtolower($game) === 'tf2' ? 440 : 730;

        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }

        echo self::render_list(array(
            'steamid' => $steamid,
            'appid'   => $appid,
            'count'   => in_array($count, array(5,10)) ? $count : 5,
            'lang'    => $lang,
            'title'   => $title
        ));

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title   = isset($instance['title']) ? $instance['title'] : '';
        $steamid = isset($instance['steamid']) ? $instance['steamid'] : '';
        $game    = isset($instance['game']) ? $instance['game'] : 'cs2';
        $count   = isset($instance['count']) ? intval($instance['count']) : 5;
        $lang    = isset($instance['lang']) ? $instance['lang'] : 'english';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('steamid'); ?>"><?php _e('SteamID64:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('steamid'); ?>" name="<?php echo $this->get_field_name('steamid'); ?>" type="text" value="<?php echo esc_attr($steamid); ?>" placeholder="7656119XXXXXXXXXX">
            <small><?php _e('Inventory must be public.'); ?></small>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('game'); ?>"><?php _e('Game:'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('game'); ?>" name="<?php echo $this->get_field_name('game'); ?>">
                <option value="cs2" <?php selected($game, 'cs2'); ?>>CS2 (AppID 730)</option>
                <option value="tf2" <?php selected($game, 'tf2'); ?>>TF2 (AppID 440)</option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Count:'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>">
                <option value="5" <?php selected($count, 5); ?>>5</option>
                <option value="10" <?php selected($count, 10); ?>>10</option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('lang'); ?>"><?php _e('Language:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('lang'); ?>" name="<?php echo $this->get_field_name('lang'); ?>" type="text" value="<?php echo esc_attr($lang); ?>" placeholder="english">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title']   = sanitize_text_field($new_instance['title']);
        $instance['steamid'] = sanitize_text_field($new_instance['steamid']);
        $instance['game']    = sanitize_text_field($new_instance['game']);
        $instance['count']   = intval($new_instance['count']);
        $instance['lang']    = sanitize_text_field($new_instance['lang']);
        return $instance;
    }

    public static function render_list($args) {
        $steamid = isset($args['steamid']) ? $args['steamid'] : '';
        $appid   = isset($args['appid']) ? intval($args['appid']) : 730;
        $count   = isset($args['count']) ? intval($args['count']) : 5;
        $lang    = isset($args['lang']) ? $args['lang'] : 'english';

        if (empty($steamid)) {
            return '<p>' . esc_html__('Please configure a SteamID64.', 'sriw') . '</p>';
        }

        $items = self::fetch_inventory_items($steamid, $appid, $count, $lang);
        if (is_wp_error($items)) {
            return '<p>' . esc_html($items->get_error_message()) . '</p>';
        }

        if (empty($items)) {
            return '<p>' . esc_html__('No items found (inventory may be private).', 'sriw') . '</p>';
        }

        $out  = '<ul class="sriw-list">';
        foreach ($items as $item) {
            $name = esc_html($item['name']);
            $img  = esc_url($item['icon']);
            $out .= '<li class="sriw-item">';
            if ($img) {
                $out .= '<img src="' . $img . '" alt="' . $name . '">';
            }
            $out .= '<span class="sriw-name">' . $name . '</span>';
            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
    }

    protected static function fetch_inventory_items($steamid, $appid, $count, $lang = 'english') {
        $cache_key = 'sriw_' . md5(implode(':', array($steamid, $appid, $count, $lang)));
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $contextid = 2; // for TF2(440) & CS2(730)
        $url = sprintf('https://steamcommunity.com/inventory/%s/%d/%d?l=%s&count=%d',
            rawurlencode($steamid),
            intval($appid),
            intval($contextid),
            rawurlencode($lang),
            200 // fetch a reasonable page to sort from
        );

        $response = wp_remote_get($url, array(
            'timeout' => 12,
            'user-agent' => 'SRIW/1.0 (+WordPress)'
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('sriw_http', sprintf(__('Steam returned HTTP %d (inventory may be private).', 'sriw'), $code));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (empty($data) || !isset($data['assets'], $data['descriptions'])) {
            return new WP_Error('sriw_badjson', __('Unexpected response from Steam. Inventory may be private.', 'sriw'));
        }

        // Map descriptions by classid:instanceid
        $desc_map = array();
        foreach ($data['descriptions'] as $d) {
            $key = $d['classid'] . ':' . (isset($d['instanceid']) ? $d['instanceid'] : '0');
            $icon = '';
            if (!empty($d['icon_url'])) {
                $icon = 'https://steamcommunity-a.akamaihd.net/economy/image/' . $d['icon_url'] . '/64fx64f';
            } elseif (!empty($d['icon_url_large'])) {
                $icon = 'https://steamcommunity-a.akamaihd.net/economy/image/' . $d['icon_url_large'] . '/64fx64f';
            }
            $desc_map[$key] = array(
                'name' => isset($d['market_hash_name']) ? $d['market_hash_name'] : (isset($d['name']) ? $d['name'] : ''),
                'icon' => $icon
            );
        }

        // Sort assets by assetid (desc) as a proxy for recency
        $assets = $data['assets'];
        usort($assets, function($a, $b) {
            // assetid is a string numeric; compare as integers via bc or fallback
            if (strlen($a['assetid']) === strlen($b['assetid'])) {
                return $a['assetid'] < $b['assetid'] ? 1 : -1;
            }
            return strlen($a['assetid']) < strlen($b['assetid']) ? 1 : -1;
        });

        $items = array();
        
        // For TF2: exclude metals and keys
        $exclude_names = array('Reclaimed Metal', 'Refined Metal', 'Scrap Metal');
        $exclude_key_pattern = '/(Mann\s*Co\.)?.*\bKey\b/i';

        foreach ($assets as $asset) {
            $k = $asset['classid'] . ':' . (isset($asset['instanceid']) ? $asset['instanceid'] : '0');
            if (isset($desc_map[$k])) {
                $name = $desc_map[$k]['name'];
                if ($appid == 440) { // TF2 specific filters
                    if (in_array($name, $exclude_names) || preg_match($exclude_key_pattern, $name)) {
                        continue;
                    }
                }
                $items[] = array(
                    'name' => $name,
                    'icon' => $desc_map[$k]['icon'],
                );
            }
            if (count($items) >= $count) break;
        }

        set_transient($cache_key, $items, 60 * 5); // cache 5 minutes
        return $items;
    }
}
