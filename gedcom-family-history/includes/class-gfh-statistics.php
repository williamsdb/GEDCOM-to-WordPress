<?php
/** Statistics use public names and relationships; sensitive facts require deceased status. */
class GFH_Statistics {
    public static function collect($data) {
        $stats = ['people'=>count($data['people']), 'families'=>count($data['families']), 'deceased'=>0, 'living'=>0, 'surnames'=>[], 'places'=>[], 'events'=>[], 'birth_years'=>[], 'death_years'=>[], 'centuries'=>[], 'ages'=>[], 'longest'=>null];
        $labels = ['BIRT'=>'Birth', 'CHR'=>'Christening', 'BAPM'=>'Baptism', 'DEAT'=>'Death', 'BURI'=>'Burial', 'CREM'=>'Cremation', 'OCCU'=>'Occupation', 'EDUC'=>'Education', 'RESI'=>'Residence', 'NATI'=>'Nationality', 'EVEN'=>'Event', 'FACT'=>'Fact', 'CENS'=>'Census', 'IMMI'=>'Immigration', 'EMIG'=>'Emigration', 'RETI'=>'Retirement', 'PROB'=>'Probate', 'WILL'=>'Will'];
        foreach ($data['people'] as $id=>$person) {
            if ($person['surname'] !== '') { $name = $person['surname']; $stats['surnames'][$name] = ($stats['surnames'][$name] ?? 0) + 1; }
            if (GFH_Gedcom::living($person)) { $stats['living']++; continue; }
            $stats['deceased']++;
            foreach ($person['record']['children'] as $node) {
                if (!isset($labels[$node['tag']])) { continue; }
                self::count_event($stats, $node, $labels[$node['tag']]);
                if ($node['tag'] === 'BIRT' || $node['tag'] === 'DEAT') {
                    $parts = GFH_Profile::date_parts(GFH_Gedcom::value($node, 'DATE'));
                    if ($parts) {
                        $key = $node['tag'] === 'BIRT' ? 'birth_years' : 'death_years'; $stats[$key][] = $parts[0];
                        if ($node['tag'] === 'BIRT') { $century = (int)floor($parts[0] / 100) * 100; $stats['centuries'][$century] = ($stats['centuries'][$century] ?? 0) + 1; }
                    }
                }
            }
            $age = GFH_Profile::age_at_death($person);
            if ($age !== null) {
                $stats['ages'][] = $age;
                if ($stats['longest'] === null || $age > $stats['longest']['age']) { $stats['longest'] = ['id'=>$id, 'name'=>$person['name'], 'age'=>$age]; }
            }
        }
        foreach ($data['families'] as $family) {
            if (count($family['parents']) < 2) { continue; }
            $private = false;
            foreach ($family['parents'] as $parent) { if (!isset($data['people'][$parent]) || GFH_Gedcom::living($data['people'][$parent])) { $private = true; } }
            if ($private) { continue; }
            foreach (['MARR'=>'Marriage', 'DIV'=>'Divorce'] as $tag=>$label) {
                foreach (GFH_Gedcom::nodes($family['record'], $tag) as $node) { self::count_event($stats, $node, $label); }
            }
        }
        foreach (['surnames','places','events'] as $key) {
            arsort($stats[$key]);
        }
        ksort($stats['centuries']);
        return $stats;
    }
    private static function count_event(&$stats, $node, $label) {
        $stats['events'][$label] = ($stats['events'][$label] ?? 0) + 1;
        $place = GFH_Maps::place($node);
        if ($place !== '') { $stats['places'][$place] = ($stats['places'][$place] ?? 0) + 1; }
    }
    private static function table($caption, $counts, $first, $link = null) {
        if (!$counts) { return '<p>No eligible records available.</p>'; }
        $out = '<div class="gfh-stats-table"><table><caption>' . esc_html($caption) . '</caption><thead><tr><th scope="col">' . esc_html($first) . '</th><th scope="col">Count</th></tr></thead><tbody>';
        foreach ($counts as $name=>$count) {
            $label = $link ? '<a href="' . esc_url($link($name)) . '">' . esc_html($name) . '</a>' : esc_html($name);
            $out .= '<tr><th scope="row">' . $label . '</th><td>' . (int)$count . '</td></tr>';
        }
        return $out . '</tbody></table></div>';
    }
    public static function render($data) {
        $s = self::collect($data);
        $out = '<h2>Family statistics</h2><p>A snapshot of the imported family history. Names and family totals include everyone. Dates, places and life events use deceased people only; marriage and divorce facts require both partners to be known deceased.</p><div class="gfh-stat-cards">';
        foreach (['people'=>'People', 'families'=>'Family records', 'deceased'=>'Recorded deceased', 'living'=>'Living or status unknown'] as $key=>$label) { $out .= '<div class="gfh-stat-card"><strong>' . $s[$key] . '</strong><span>' . $label . '</span></div>'; }
        $out .= '</div><h3>Dates and lifespans</h3><dl class="gfh-stat-facts">';
        foreach (['birth_years'=>'Recorded birth years', 'death_years'=>'Recorded death years'] as $key=>$label) {
            $out .= '<dt>' . $label . '</dt><dd>' . ($s[$key] ? min($s[$key]) . '–' . max($s[$key]) : 'Not available') . '</dd>';
        }
        $n = count($s['ages']);
        $out .= '<dt>Average recorded lifespan</dt><dd>' . ($n ? number_format(array_sum($s['ages']) / $n, 1) . ' years · ' . $n . ($n === 1 ? ' person' : ' people') . ' with exact birth and death dates' : 'Not enough exact dates') . '</dd>';
        if ($s['longest']) {
            $p = $s['longest']; $url = add_query_arg(['gfh_person'=>$p['id']], get_permalink($data['page']) ?: get_permalink());
            $out .= '<dt>Longest recorded lifespan</dt><dd><a href="' . esc_url($url) . '">' . esc_html($p['name']) . '</a> · ' . $p['age'] . ' years</dd>';
        }
        $out .= '</dl><p class="gfh-stats-note">Year ranges include qualified dates such as ABT and the first year of date ranges. Lifespans require exact Gregorian dates and exclude negative ages or ages above 120. These figures describe the imported records, not a complete population.</p><h3>Most common surnames</h3>';
        $out .= self::table('Top 10 surnames · people', array_slice($s['surnames'], 0, 10, true), 'Surname', function($name) use ($data) { return add_query_arg(['gfh_view'=>'surnames', 'gfh_surname'=>$name], get_permalink($data['page']) ?: get_permalink()); });
        $out .= '<h3>Most recorded places</h3><p>Counts are event records, including undated facts. Each marriage or divorce event is counted once per family. Addresses are grouped under their recorded place.</p>' . self::table('Top 10 places · public event records', array_slice($s['places'], 0, 10, true), 'Place');
        $out .= '<h3>Recorded events</h3>' . self::table('Public event records by type', $s['events'], 'Event');
        $centuries = [];
        foreach ($s['centuries'] as $year=>$count) { $centuries[$year . '–' . ($year+99)] = $count; }
        $out .= '<h3>Birth records by century</h3>' . self::table('Deceased-person birth records with a recognized year', $centuries, 'Years');
        return $out;
    }
}
