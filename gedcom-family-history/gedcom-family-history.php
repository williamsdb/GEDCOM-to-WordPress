<?php
/**
 * Plugin Name: GEDCOM Family History
 * Description: Import a GEDCOM and publish accessible family profiles, ancestry and name indexes with living-person privacy.
 * Version: 1.4.2
 * Author: Neil Thompson
 * Author URI: https://thompsonhistory.co.uk/
 * Plugin URI: https://thompsonhistory.co.uk/
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: gedcom-family-history
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/includes/class-gfh-gedcom.php';
require_once __DIR__ . '/includes/class-gfh-profile.php';
require_once __DIR__ . '/includes/class-gfh-relatives.php';
require_once __DIR__ . '/includes/class-gfh-maps.php';
require_once __DIR__ . '/includes/class-gfh-statistics.php';
final class GFH_Plugin {
    const OPTION = 'gfh_dataset';
    public static function boot() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_gfh_place', ['GFH_Maps', 'save']);
        add_action('admin_post_gfh_save', [__CLASS__, 'save']);
        add_shortcode('gedcom_family_statistics', [__CLASS__, 'statistics']);
        add_shortcode('gedcom_family_history', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', function() { wp_enqueue_style('gfh-leaflet', plugins_url('assets/leaflet/leaflet.css', __FILE__), [], '1.9.4'); wp_enqueue_script('gfh-leaflet', plugins_url('assets/leaflet/leaflet.js', __FILE__), [], '1.9.4', true); wp_enqueue_script('gfh-maps', plugins_url('assets/maps.js', __FILE__), ['gfh-leaflet'], '1.4.2', true); wp_enqueue_style('gfh', plugins_url('assets/family-history.css', __FILE__), [], '1.4.2'); });
    }
    public static function menu() { add_menu_page('Family History', 'Family History', 'manage_options', 'gfh', [__CLASS__, 'admin'], 'dashicons-networking'); add_submenu_page('gfh', 'Family History Places', 'Places', 'manage_options', 'gfh-places', ['GFH_Maps', 'admin']); }
    private static function state() { return get_option(self::OPTION, ['people' => [], 'families' => [], 'root' => '', 'page' => 0]); }
    public static function admin() {
        if (!current_user_can('manage_options')) { return; }
        $data = self::state();
        echo '<div class="wrap"><h1>GEDCOM Family History</h1>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Family history saved.</p></div>'; }
        echo '<p>Upload a UTF-8 GEDCOM (.ged), up to 10 MB. A valid replacement replaces the entire tree. Keep your original file as a backup.</p><p>People without recorded death or burial evidence are treated as living. Their dates, places and facts are hidden from all visitors, including administrators.</p>';
        if (!empty($data['people'])) { echo '<p>' . count($data['people']) . ' people · Imported ' . esc_html($data['imported'] ?? '') . '</p>'; }
        if (!isset($data['sources']) && $data['people']) { echo '<div class="notice notice-info"><p>Re-import your GEDCOM to load source records for the new timeline citations. Your root selection will be retained when its ID matches.</p></div>'; }
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="gfh_save">';
        wp_nonce_field('gfh_save');
        echo '<h2>Import or replace GEDCOM</h2><p><label for="gfh-file">GEDCOM file</label> <input id="gfh-file" type="file" name="gedcom" accept=".ged"></p>';
        if ($data['people']) {
            echo '<h2>Starting person</h2><p><label for="gfh-root">Root person</label> <select id="gfh-root" name="root">';
            $people = $data['people']; uasort($people, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
            foreach ($people as $id => $person) { echo '<option value="' . esc_attr($id) . '" ' . selected($data['root'], $id, false) . '>' . esc_html($person['name'] . ' [' . $id . ']') . '</option>'; }
            echo '</select></p><p>After replacement, select the starting person from the refreshed list. A matching existing root ID is retained.</p>';
        }
        submit_button('Save family history'); echo '</form>';
        if (!empty($data['page'])) { echo '<p><a href="' . esc_url(get_permalink($data['page'])) . '">View Family History</a></p>'; }
        echo '<p>The plugin creates a published Family History page on first import. You can edit its title and introductory text; keep the <code>[gedcom_family_history]</code> shortcode. Names and relationships of living people remain public. Notes are only shown for deceased people; review your source notes for references to living relatives before publishing.</p></div>';
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission.', '', ['response' => 403]); }
        check_admin_referer('gfh_save');
        $data = self::state();
        try {
            $file = $_FILES['gedcom'] ?? null;
            if ($file && (int) $file['error'] !== UPLOAD_ERR_NO_FILE) {
                if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) { throw new RuntimeException('The file upload failed. Please try again.'); }
                if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'ged') { throw new RuntimeException('Please select a .ged file.'); }
                if (filesize($file['tmp_name']) > 10 * 1024 * 1024) { throw new RuntimeException('The GEDCOM must be no larger than 10 MB.'); }
                $text = file_get_contents($file['tmp_name']);
                if ($text === false) { throw new RuntimeException('The upload could not be read.'); }
                $parsed = GFH_Gedcom::parse($text);
                $data = array_merge($data, $parsed);
                $data['imported'] = current_time('mysql');
                // Do not apply a stale submitted root selection to a replacement file.
            } elseif (isset($_POST['root']) && is_string($_POST['root'])) {
                $root = sanitize_text_field(wp_unslash($_POST['root']));
                if (!isset($data['people'][$root])) { throw new RuntimeException('Select a person from this tree.'); }
                $data['root'] = $root;
            }
            if (!$data['people']) { throw new RuntimeException('Choose a GEDCOM file to import.'); }
            if (!isset($data['people'][$data['root']])) { $data['root'] = array_key_first($data['people']); }
            if (empty($data['page']) || !get_post($data['page'])) {
                $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Family History', 'post_content' => '[gedcom_family_history]'], true);
                if (is_wp_error($page)) { throw new RuntimeException('Could not create the Family History page.'); }
                $data['page'] = $page;
            }
            update_option(self::OPTION, $data, false);
        } catch (RuntimeException $error) { wp_die(esc_html($error->getMessage()) . ' Your existing tree has not been replaced.', 'Import failed', ['back_link' => true, 'response' => 400]); }
        wp_safe_redirect(admin_url('admin.php?page=gfh&saved=1')); exit;
    }
    private static function url($data, $args = []) { return add_query_arg($args, get_permalink($data['page']) ?: get_permalink()); }
    private static function link($data, $id) {
        if (!isset($data['people'][$id])) { return '<span>Unknown person</span>'; }
        return '<a href="' . esc_url(self::url($data, ['gfh_person' => $id])) . '">' . esc_html($data['people'][$id]['name']) . '</a>';
    }
    private static function event($node) {
        $parts = array_filter([GFH_Gedcom::text($node) === 'Y' ? '' : GFH_Gedcom::text($node), GFH_Gedcom::value($node, 'DATE')], function($v) { return $v !== ''; });
        $text = esc_html(implode(' · ', $parts));
        $location = GFH_Profile::location($node);
        return ($text . ($text && $location ? '<br>' : '') . $location) ?: 'Recorded; details unknown';
    }
    private static function tree_name($data, $id, $current = false) {
        if (!isset($data['people'][$id])) { return '<span class="gfh-unknown">Unknown</span>'; }
        $person = $data['people'][$id];
        $raw = GFH_Gedcom::value($person['record'], 'NAME');
        $label = esc_html($person['name']);
        if (preg_match('~^(.*?)/([^/]*)/(.*)$~s', $raw, $parts)) {
            $label = esc_html($parts[1]) . '<em>' . esc_html($parts[2]) . '</em>' . esc_html($parts[3]);
        }
        if ($current) { return '<strong aria-current="page">' . $label . '</strong>'; }
        return '<a href="' . esc_url(self::url($data, ['gfh_person' => $id])) . '">' . $label . '</a>';
    }
    private static function parents($data, $id) {
        foreach ($data['families'] as $family) {
            if (in_array($id, $family['children'], true)) { return array_pad(array_slice($family['parents'], 0, 2), 2, ''); }
        }
        return ['', ''];
    }
    private static function tree($data, $id) {
        $width = 620;
        foreach ($data['families'] as $family) {
            if (in_array($id, $family['parents'], true)) { $width = max($width, count($family['children']) * 140); }
        }
        $out = '<div class="gfh-tree" tabindex="0" role="region" aria-label="Family tree chart; scroll horizontally on small screens"><div class="gfh-chart" style="min-width:' . (int) $width . 'px"><div class="gfh-branches">';
        foreach (self::parents($data, $id) as $parent) {
            $out .= '<div class="gfh-branch"><ul class="gfh-grandparents" aria-label="Grandparents">';
            foreach (self::parents($data, $parent) as $grandparent) { $out .= '<li>' . self::tree_name($data, $grandparent) . '</li>'; }
            $out .= '</ul><div class="gfh-parent" role="group" aria-label="Parent">' . self::tree_name($data, $parent) . '</div></div>';
        }
        $out .= '</div><div class="gfh-current" role="group" aria-label="Selected person">' . self::tree_name($data, $id, true) . '</div>';
        foreach ($data['families'] as $family) {
            if (!in_array($id, $family['parents'], true)) { continue; }
            $out .= '<div class="gfh-descendants' . ($family['children'] ? ' gfh-has-children' : '') . '"><div class="gfh-partner" role="group" aria-label="Partner"><span class="gfh-relation">Partner: </span>';
            $partners = array_values(array_diff($family['parents'], [$id]));
            $out .= $partners ? implode(', ', array_map(function($partner) use ($data) { return self::tree_name($data, $partner); }, $partners)) : '<span class="gfh-unknown">Unknown</span>';
            $out .= '</div>';
            if ($family['children']) {
                $out .= '<ul class="gfh-children" aria-label="Children">';
                foreach ($family['children'] as $child) { $out .= '<li>' . self::tree_name($data, $child) . '</li>'; }
                $out .= '</ul>';
            }
            $out .= '</div>';
        }
        return $out . '</div></div>';
    }
    private static function search_form($data, $query) {
        $url = self::url($data); $args = [];
        parse_str((string)parse_url($url, PHP_URL_QUERY), $args);
        $out = '<form class="gfh-search" method="get" action="' . esc_url($url) . '"><label>Search names<input type="search" name="gfh_search" value="' . esc_html($query) . '" placeholder="Given name or surname"></label>';
        foreach ($args as $key=>$value) { if (strpos($key, 'gfh_') !== 0 && is_scalar($value)) { $out .= '<input type="hidden" name="' . esc_html($key) . '" value="' . esc_html($value) . '">'; } }
        return $out . '<input type="hidden" name="gfh_view" value="names"><button type="submit">Search</button></form>';
    }
    private static function relatives($data, $id) {
        $families = GFH_Relatives::families($data, $id);
        $out = '<h3>Parents and siblings</h3>';
        if (!$families) { return $out . '<p>No parents or siblings recorded.</p>'; }
        foreach ($families as $family) {
            $types = ['birth'=>'Birth family', 'adopted'=>'Adoptive family', 'foster'=>'Foster family', 'sealing'=>'Sealing family'];
            $out .= '<div class="gfh-family"><h4>' . esc_html($types[$family['pedigree']] ?? 'Parents') . '</h4><ul>';
            foreach ($family['parents'] as $parent) {
                $role = in_array($parent, GFH_Gedcom::refs($family['record'], 'HUSB'), true) ? 'Father' : (in_array($parent, GFH_Gedcom::refs($family['record'], 'WIFE'), true) ? 'Mother' : 'Parent');
                if ($family['pedigree'] === 'adopted' || in_array($parent, $family['adopted'], true)) { $role = 'Adoptive ' . strtolower($role); }
                elseif ($family['pedigree'] === 'foster') { $role = 'Foster ' . strtolower($role); }
                $out .= '<li>' . $role . ': ' . self::link($data, $parent) . '</li>';
            }
            if (!$family['parents']) { $out .= '<li>Parents not recorded.</li>'; }
            $out .= '</ul></div>';
        }
        $groups = GFH_Relatives::siblings($data, $id, $families);
        foreach ($groups as $label=>$ids) {
            usort($ids, function($a,$b) use ($data) { return strcasecmp($data['people'][$a]['name'], $data['people'][$b]['name']); });
            $out .= '<div class="gfh-family"><h4>' . esc_html($label) . '</h4><ul>';
            foreach ($ids as $relative) { $out .= '<li>' . self::link($data, $relative) . '</li>'; }
            $out .= '</ul></div>';
        }
        if (!$groups) { $out .= '<p>No siblings recorded.</p>'; }
        return $out;
    }
    private static function toolbar($data, $query = '') {
        return '<div class="gfh-toolbar"><nav aria-label="Family history navigation"><a href="' . esc_url(self::url($data)) . '">Starting person</a> <a href="' . esc_url(self::url($data, ['gfh_view'=>'names'])) . '">All names</a> <a href="' . esc_url(self::url($data, ['gfh_view'=>'surnames'])) . '">Surnames</a> <a href="' . esc_url(self::url($data, ['gfh_view'=>'statistics'])) . '">Statistics</a></nav>' . self::search_form($data, $query) . '</div>';
    }
    public static function statistics() {
        $data = self::state();
        if (!$data['people']) { return '<p>No family history has been imported yet.</p>'; }
        return '<section class="gfh gfh-layout" aria-label="Family statistics">' . self::toolbar($data) . '<div class="gfh-content">' . GFH_Statistics::render($data) . '</div></section>';
    }
    public static function render() {
        $data = self::state();
        if (!$data['people']) { return '<p>No family history has been imported yet.</p>'; }
        $view = isset($_GET['gfh_view']) && is_string($_GET['gfh_view']) ? sanitize_key($_GET['gfh_view']) : '';
        $query = isset($_GET['gfh_search']) && is_string($_GET['gfh_search']) ? trim(sanitize_text_field(wp_unslash($_GET['gfh_search']))) : '';
        $out = '<section class="gfh gfh-layout" aria-label="Family history">' . self::toolbar($data, $query) . '<div class="gfh-content">';
        if ($view === 'statistics' && $query === '') { return $out . GFH_Statistics::render($data) . '</div></section>'; }
        if ($query !== '') { $view = 'names'; }
        if (in_array($view, ['names', 'surnames'], true)) {
            $surname = isset($_GET['gfh_surname']) && is_string($_GET['gfh_surname']) ? sanitize_text_field(wp_unslash($_GET['gfh_surname'])) : null;
            $people = $data['people']; uasort($people, function($a, $b) { return strcasecmp($a['surname'] . ' ' . $a['name'], $b['surname'] . ' ' . $b['name']); });
            if ($query !== '') {
                $tokens = preg_split('/\s+/u', $query);
                $normalize = function($text) { if (function_exists('remove_accents')) { $text = remove_accents($text); } return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text); };
                $people = array_filter($people, function($person) use ($tokens, $normalize) {
                    foreach ($tokens as $token) { if (strpos($normalize($person['name'] . ' ' . $person['surname']), $normalize($token)) === false) { return false; } }
                    return true;
                });
                $out .= '<p>' . count($people) . ' matching people for “' . esc_html($query) . '”.</p>';
                if (!$people) { $out .= '<p>No names matched. Try a given name, surname, or part of a name.</p>'; }
            }
            $out .= '<h2>' . ($view === 'names' ? 'All names' : 'Surnames') . '</h2><ul class="gfh-index">';
            if ($view === 'surnames' && $surname === null) {
                $counts = []; foreach ($people as $person) { $key = $person['surname']; $counts[$key] = ($counts[$key] ?? 0) + 1; }
                foreach ($counts as $name => $count) { $out .= '<li><a href="' . esc_url(self::url($data, ['gfh_view' => 'surnames', 'gfh_surname' => $name])) . '">' . esc_html($name ?: '(Unknown surname)') . '</a> (' . $count . ')</li>'; }
            } else {
                foreach ($people as $id => $person) { if ($view === 'names' || $person['surname'] === $surname) { $out .= '<li>' . self::link($data, $id) . '</li>'; } }
            }
            return $out . '</ul></div></section>';
        }
        $id = isset($_GET['gfh_person']) && is_string($_GET['gfh_person']) ? sanitize_text_field(wp_unslash($_GET['gfh_person'])) : $data['root'];
        if (!isset($data['people'][$id])) { return $out . '<p>This person is no longer in the current tree. Use the name index to find them.</p></div></section>'; }
        $person = $data['people'][$id]; $living = GFH_Gedcom::living($person);
        $out .= '<header class="gfh-profile-header"><p class="gfh-eyebrow">The family archive</p><h2>' . esc_html($person['name']) . '</h2></header>';
        $out .= '<h3>Family tree</h3><p>Grandparents, parents, the selected person, and their partners and children. Select a linked name to explore their family.</p>' . self::tree($data, $id) . self::relatives($data, $id) . '<h3>Partners and children</h3>';
        $found = false;
        foreach ($data['families'] as $family) {
            if (!in_array($id, $family['parents'], true)) { continue; }
            $found = true; $private = $living || count($family['parents']) < 2;
            foreach ($family['parents'] as $parent) { if (!isset($data['people'][$parent]) || GFH_Gedcom::living($data['people'][$parent])) { $private = true; } }
            $out .= '<div class="gfh-family"><p>Partner: ';
            $partners = array_values(array_diff($family['parents'], [$id]));
            $out .= $partners ? implode(', ', array_map(function($p) use ($data) { return self::link($data, $p); }, $partners)) : 'Unknown'; $out .= '</p>';
            foreach (['MARR' => 'Marriage', 'DIV' => 'Divorce'] as $tag => $label) {
                foreach (GFH_Gedcom::nodes($family['record'], $tag) as $event) { $out .= '<p>' . $label . ': ' . ($private ? 'Details private' : self::event($event)) . '</p>'; }
            }
            if ($family['children']) { $out .= '<h4>Children</h4><ul>'; foreach ($family['children'] as $child) { $out .= '<li>' . self::link($data, $child) . '</li>'; } $out .= '</ul>'; }
            else { $out .= '<p>No children recorded.</p>'; }
            $out .= '</div>';
        }
        if (!$found) { $out .= '<p>No partners or children recorded.</p>'; }
        if ($living) { $out .= '<h3>Details</h3><p class="gfh-private">Living or status unknown — dates, places and personal facts are private.</p>'; }
        else { $out .= GFH_Profile::timeline($data, $id) . GFH_Maps::render($data, $id); }
        if (!$living) {
            $notes = GFH_Gedcom::nodes($person['record'], 'NOTE');
            $notes = array_filter($notes, function($n) { return !preg_match('/^@[^@]+@$/', $n['value']); });
            if ($notes) { $out .= '<h3>Notes</h3>'; foreach ($notes as $note) { $out .= '<p>' . nl2br(esc_html(GFH_Gedcom::text($note))) . '</p>'; } }
        }
        return $out . '</div></section>';
    }
}
GFH_Plugin::boot();
