<?php
/** Local coordinate registry. No automatic geocoding or public location endpoint. */
class GFH_Maps {
    const OPTION = 'gfh_places';
    public static function place($node) {
        return implode(', ', array_filter(array_map('trim', explode(',', GFH_Gedcom::value($node, 'PLAC'))), function($v) { return $v !== ''; }));
    }
    public static function key($place) {
        $place = preg_replace('/\s+/u', ' ', trim($place));
        $place = function_exists('mb_strtolower') ? mb_strtolower($place, 'UTF-8') : strtolower($place);
        return hash('sha256', $place);
    }
    public static function coordinate($value, $latitude) {
        $value = strtoupper(trim($value));
        if (!preg_match('/^([NSEW])?\s*([+-]?(?:\d+(?:\.\d*)?|\.\d+))$/', $value, $m)) { return null; }
        $direction = $m[1];
        if ($direction && !in_array($direction, $latitude ? ['N','S'] : ['E','W'], true)) { return null; }
        $number = (float)$m[2];
        if ($direction && $number < 0) { return null; }
        if ($direction === 'S' || $direction === 'W') { $number = -$number; }
        return abs($number) <= ($latitude ? 90 : 180) ? $number : null;
    }
    public static function embedded($node) {
        foreach (GFH_Gedcom::nodes($node, 'PLAC') as $place) {
            foreach (GFH_Gedcom::nodes($place, 'MAP') as $map) {
                $lat = self::coordinate(GFH_Gedcom::value($map, 'LATI'), true);
                $lng = self::coordinate(GFH_Gedcom::value($map, 'LONG'), false);
                if ($lat !== null && $lng !== null) { return ['lat'=>$lat, 'lng'=>$lng, 'accuracy'=>'GEDCOM coordinates']; }
            }
        }
        return null;
    }
    public static function points($data, $id, $saved) {
        $groups = []; $missing = [];
        foreach (GFH_Profile::events($data, $id, false) as $event) {
            $place = self::place($event['node']);
            if ($place === '') { continue; }
            $key = self::key($place);
            $coords = $saved[$key] ?? self::embedded($event['node']);
            if (!$coords) { $missing[$key] = $place; continue; }
            $lat = self::coordinate((string)($coords['lat'] ?? ''), true);
            $lng = self::coordinate((string)($coords['lng'] ?? ''), false);
            if ($lat === null || $lng === null) { $missing[$key] = $place; continue; }
            $group = $lat . ':' . $lng;
            if (!isset($groups[$group])) { $groups[$group] = ['lat'=>$lat, 'lng'=>$lng, 'events'=>[]]; }
            $groups[$group]['events'][] = ['label'=>$event['label'], 'detail'=>GFH_Profile::event_value($event['node']), 'location'=>GFH_Profile::location_lines($event['node']), 'date'=>GFH_Gedcom::value($event['node'], 'DATE'), 'place'=>$place, 'accuracy'=>$coords['accuracy'] ?? 'Town/parish (approximate)'];
        }
        return ['points'=>array_values($groups), 'missing'=>array_values($missing)];
    }
    public static function render($data, $id) {
        if (GFH_Gedcom::living($data['people'][$id])) { return ''; }
        $result = self::points($data, $id, get_option(self::OPTION, []));
        if (!$result['points'] && !$result['missing']) { return ''; }
        $out = '<section class="gfh-places" aria-label="Places in this life"><h3>Places in this life</h3>';
        if ($result['points']) {
            $json = json_encode($result['points'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $out .= '<div class="gfh-map-wrap" data-gfh-points="' . esc_html($json) . '"><p>Load the interactive map to view recorded places. Map tiles come from OpenStreetMap; your browser connects to that service when you load it.</p><button type="button" class="gfh-load-map">Load map</button><div class="gfh-map" role="region" aria-label="Map of recorded life events" hidden></div><p class="gfh-map-status" role="status"></p></div><details class="gfh-map-list"><summary>Mapped places and events</summary><ul>';
            foreach ($result['points'] as $point) {
                foreach ($point['events'] as $event) { $out .= '<li><strong>' . esc_html($event['label']) . '</strong>' . ($event['date'] !== '' ? ' · ' . esc_html($event['date']) : '') . ($event['detail'] !== '' ? '<br>' . nl2br(esc_html($event['detail'])) : '') . '<br>' . implode('<br>', array_map('esc_html', $event['location'])) . ' <small>(' . esc_html($event['accuracy']) . ')</small></li>'; }
            }
            $out .= '</ul></details>';
        } else { $out .= '<p>No coordinates have been saved for these places yet.</p>'; }
        if ($result['missing']) {
            $out .= '<details><summary>Places awaiting location (' . count($result['missing']) . ')</summary><ul>';
            foreach ($result['missing'] as $place) { $out .= '<li>' . esc_html($place) . '</li>'; }
            $out .= '</ul></details>';
        }
        return $out . '</section>';
    }
    public static function catalog($data) {
        $places = [];
        foreach ($data['people'] as $id=>$person) {
            foreach (GFH_Profile::events($data, $id, false) as $event) {
                $place = self::place($event['node']);
                if ($place === '') { continue; }
                $key = self::key($place);
                if (!isset($places[$key])) { $places[$key] = ['name'=>$place, 'embedded'=>0]; }
                if (self::embedded($event['node'])) { $places[$key]['embedded']++; }
            }
        }
        uasort($places, function($a,$b) { return strcasecmp($a['name'], $b['name']); });
        return $places;
    }
    public static function admin() {
        if (!current_user_can('manage_options')) { return; }
        $data = get_option(GFH_Plugin::OPTION, ['people'=>[], 'families'=>[]]);
        $places = self::catalog($data); $saved = get_option(self::OPTION, []);
        $query = isset($_GET['place_search']) && is_string($_GET['place_search']) ? sanitize_text_field(wp_unslash($_GET['place_search'])) : '';
        if ($query !== '') { $places = array_filter($places, function($p) use ($query) { return stripos($p['name'], $query) !== false; }); }
        $page = max(1, isset($_GET['place_page']) ? (int)$_GET['place_page'] : 1); $total = count($places); $pages = max(1, (int)ceil($total / 25)); $page = min($pages, $page);
        echo '<div class="wrap"><h1>Family History Places</h1><p>Save coordinates once per place; they are reused across profiles and GEDCOM refreshes. Only places from public deceased-person events are listed. GEDCOM coordinates are used automatically unless overridden here.</p><p>For historical locations, use the town/parish unless the building is certain. Follow Find on OpenStreetMap, then use the map’s “Show address” context menu or URL to read latitude and longitude. No automatic geocoding requests are sent.</p>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Place updated.</p></div>'; }
        echo '<form method="get"><input type="hidden" name="page" value="gfh-places"><label>Filter places <input name="place_search" value="' . esc_html($query) . '"></label> <button class="button">Filter</button></form><p>' . $total . ' places · Page ' . $page . ' of ' . $pages . '</p>';
        foreach (array_slice($places, ($page-1)*25, 25, true) as $key=>$place) {
            $entry = $saved[$key] ?? [];
            echo '<div style="padding:16px;margin:12px 0;background:white;border:1px solid #ccc"><h2>' . esc_html($place['name']) . '</h2><p>' . ($place['embedded'] ? 'GEDCOM coordinates available for ' . $place['embedded'] . ' event(s).' : 'No GEDCOM coordinates recorded.') . ' <a target="_blank" rel="noopener noreferrer" href="' . esc_url('https://www.openstreetmap.org/search?query=' . rawurlencode($place['name'])) . '">Find on OpenStreetMap</a></p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="gfh_place"><input type="hidden" name="place_key" value="' . esc_html($key) . '">';
            wp_nonce_field('gfh_place');
            echo '<label>Latitude <input name="lat" type="number" step="any" min="-90" max="90" value="' . esc_html($entry['lat'] ?? '') . '"></label> <label>Longitude <input name="lng" type="number" step="any" min="-180" max="180" value="' . esc_html($entry['lng'] ?? '') . '"></label> <label>Precision <select name="accuracy">';
            foreach (['Town/parish (approximate)', 'Building/site (verified)'] as $accuracy) { echo '<option ' . selected($entry['accuracy'] ?? '', $accuracy, false) . '>' . esc_html($accuracy) . '</option>'; }
            echo '</select></label> <button class="button button-primary">Save coordinates</button><p>Leave both coordinates blank and save to remove your override and return to GEDCOM coordinates, if any.</p></form></div>';
        }
        for ($n=1;$n<=$pages;$n++) { echo '<a style="margin-right:12px" href="' . esc_url(add_query_arg(['page'=>'gfh-places','place_page'=>$n,'place_search'=>$query], admin_url('admin.php'))) . '">' . $n . '</a>'; }
        echo '</div>';
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission.', '', ['response'=>403]); }
        check_admin_referer('gfh_place');
        $data = get_option(GFH_Plugin::OPTION, ['people'=>[], 'families'=>[]]);
        $key = isset($_POST['place_key']) && is_string($_POST['place_key']) ? sanitize_text_field(wp_unslash($_POST['place_key'])) : '';
        if (!isset(self::catalog($data)[$key])) { wp_die('This place is no longer in the current public tree.', '', ['response'=>400]); }
        $saved = get_option(self::OPTION, []);
        $lat = isset($_POST['lat']) && is_string($_POST['lat']) ? trim(wp_unslash($_POST['lat'])) : '';
        $lng = isset($_POST['lng']) && is_string($_POST['lng']) ? trim(wp_unslash($_POST['lng'])) : '';
        if ($lat === '' && $lng === '') { unset($saved[$key]); }
        else {
            $latitude = self::coordinate($lat, true); $longitude = self::coordinate($lng, false);
            if ($latitude === null || $longitude === null) { wp_die('Enter valid latitude and longitude, or leave both blank.', '', ['response'=>400, 'back_link'=>true]); }
            $accuracy = isset($_POST['accuracy']) && $_POST['accuracy'] === 'Building/site (verified)' ? 'Building/site (verified)' : 'Town/parish (approximate)';
            $saved[$key] = ['lat'=>$latitude, 'lng'=>$longitude, 'accuracy'=>$accuracy];
        }
        update_option(self::OPTION, $saved, false);
        wp_safe_redirect(admin_url('admin.php?page=gfh-places&saved=1')); exit;
    }
}
