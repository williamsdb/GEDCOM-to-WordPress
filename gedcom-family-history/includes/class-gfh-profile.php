<?php
/** Presentation of facts and citations; called only for deceased profiles. */
class GFH_Profile {
    public static function location_lines($node) {
        $lines = [];
        $addresses = GFH_Gedcom::nodes($node, 'ADDR');
        foreach (GFH_Gedcom::nodes($node, 'PLAC') as $place) {
            $addresses = array_merge($addresses, GFH_Gedcom::nodes($place, 'ADDR'));
        }
        foreach ($addresses as $address) {
            foreach (preg_split('/\r\n|\n|\r/', GFH_Gedcom::text($address)) as $line) {
                if (trim($line) !== '') { $lines[] = trim($line); }
            }
            foreach (['ADR1', 'ADR2', 'ADR3', 'CITY', 'STAE', 'POST', 'CTRY'] as $tag) {
                $value = trim(GFH_Gedcom::value($address, $tag));
                if ($value !== '') { $lines[] = $value; }
            }
        }
        $parts = array_filter(array_map('trim', explode(',', GFH_Gedcom::value($node, 'PLAC'))), function($part) { return $part !== ''; });
        if ($parts) { $lines[] = implode(', ', $parts); }
        return array_values(array_unique($lines));
    }
    public static function location($node) {
        return implode('<br>', array_map('esc_html', self::location_lines($node)));
    }
    public static function event_value($node) {
        $value = trim(GFH_Gedcom::text($node));
        if ($node['tag'] === 'OCCU' && ($value === '' || $value === 'Y')) {
            $type = trim(GFH_Gedcom::value($node, 'TYPE'));
            if ($type !== '' && strcasecmp($type, 'Occupation') !== 0) { $value = $type; }
        }
        return $value === 'Y' ? '' : $value;
    }
    public static function date_parts($date) {
        $date = strtoupper(preg_replace('/\s+/', ' ', trim($date)));
        // Numeric exports use British day/month/year order, never month/day/year.
        if (preg_match('/^(?:(?:ABT|CAL|EST|BEF|AFT) )?(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $numeric)) {
            return checkdate((int)$numeric[2], (int)$numeric[1], (int)$numeric[3]) ? [(int)$numeric[3], (int)$numeric[2], (int)$numeric[1]] : null;
        }
        if (!preg_match('/^(?:(?:ABT|CAL|EST|BEF|AFT|BET|FROM|TO) )?(?:(\d{1,2}) )?(?:(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC) )?(\d{3,4})(?:(?: AND | TO )\d.*)?$/', $date, $m)) { return null; }
        $months = array_flip(['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC']);
        $parts = [(int)$m[3], !empty($m[2]) ? $months[$m[2]] + 1 : 0, !empty($m[1]) ? (int)$m[1] : 0];
        if ($parts[2] && (!$parts[1] || !checkdate($parts[1], $parts[2], $parts[0]))) { return null; }
        return $parts;
    }
    private static function exact_date($date) {
        $date = strtoupper(preg_replace('/\s+/', ' ', trim($date)));
        if (!preg_match('/^(?:\d{1,2} [A-Z]{3} \d{4}|\d{1,2}\/\d{1,2}\/\d{4})$/', $date)) { return null; }
        $p = self::date_parts($date);
        return $p && checkdate($p[1], $p[2], $p[0]) ? new DateTimeImmutable(sprintf('%04d-%02d-%02d', $p[0], $p[1], $p[2])) : null;
    }
    public static function age_at_death($person) {
        if (GFH_Gedcom::living($person)) { return null; }
        $births = GFH_Gedcom::nodes($person['record'], 'BIRT');
        $deaths = GFH_Gedcom::nodes($person['record'], 'DEAT');
        // Conflicting or alternative vital events are not a reliable lifespan sample.
        if (count($births) !== 1 || count($deaths) !== 1) { return null; }
        $birth = self::exact_date(GFH_Gedcom::value($births[0], 'DATE'));
        $death = self::exact_date(GFH_Gedcom::value($deaths[0], 'DATE'));
        if (!$birth || !$death || $death < $birth) { return null; }
        $age = $birth->diff($death)->y;
        return $age <= 120 ? $age : null;
    }
    public static function citations($data, $node) {
        $citations = GFH_Gedcom::nodes($node, 'SOUR');
        if (!$citations) { return ''; }
        $out = '<details class="gfh-sources"><summary>Sources (' . count($citations) . ')</summary><ul>';
        foreach ($citations as $citation) {
            $value = GFH_Gedcom::text($citation);
            $pointer = preg_match('/^@([^@]+)@$/', $value, $match);
            $source = $pointer ? ($data['sources'][$match[1]] ?? null) : null;
            $title = $source ? (GFH_Gedcom::value($source, 'TITL') ?: GFH_Gedcom::value($source, 'ABBR')) : ($pointer ? 'Source record unavailable; re-import the GEDCOM to load source records.' : $value);
            $out .= '<li><strong>' . esc_html($title ?: 'Untitled source') . '</strong>';
            if ($source) {
                foreach (['AUTH'=>'Author', 'PUBL'=>'Publication'] as $tag=>$label) {
                    $text = GFH_Gedcom::value($source, $tag);
                    if ($text !== '') { $out .= '<p>' . $label . ': ' . nl2br(esc_html($text)) . '</p>'; }
                }
            }
            $page = GFH_Gedcom::value($citation, 'PAGE');
            if ($page !== '') { $out .= '<p>Reference: ' . esc_html($page) . '</p>'; }
            foreach (GFH_Gedcom::nodes($citation, 'DATA') as $item) {
                foreach (GFH_Gedcom::nodes($item, 'TEXT') as $text) { $out .= '<blockquote>' . nl2br(esc_html(GFH_Gedcom::text($text))) . '</blockquote>'; }
            }
            $quality = GFH_Gedcom::value($citation, 'QUAY');
            $qualities = ['0'=>'Unreliable or estimated', '1'=>'Questionable', '2'=>'Secondary evidence', '3'=>'Direct evidence'];
            if (isset($qualities[$quality])) { $out .= '<p>Recorded evidence assessment: ' . esc_html($qualities[$quality]) . '</p>'; }
            $out .= '</li>';
        }
        return $out . '</ul></details>';
    }
    public static function events($data, $id, $dated_only = true) {
        $person = $data['people'][$id];
        if (GFH_Gedcom::living($person)) { return []; }
        $labels = ['BIRT'=>'Birth', 'CHR'=>'Christening', 'BAPM'=>'Baptism', 'DEAT'=>'Death', 'BURI'=>'Burial', 'CREM'=>'Cremation', 'OCCU'=>'Occupation', 'EDUC'=>'Education', 'RESI'=>'Residence', 'NATI'=>'Nationality', 'EVEN'=>'Event', 'FACT'=>'Fact', 'CENS'=>'Census', 'IMMI'=>'Immigration', 'EMIG'=>'Emigration', 'RETI'=>'Retirement', 'PROB'=>'Probate', 'WILL'=>'Will'];
        $events = [];
        foreach ($person['record']['children'] as $node) {
            if (isset($labels[$node['tag']])) { $events[] = ['node'=>$node, 'label'=>$node['tag'] === 'OCCU' ? 'Occupation' : (GFH_Gedcom::value($node, 'TYPE') ?: $labels[$node['tag']])]; }
        }
        foreach ($data['families'] as $family) {
            if (!in_array($id, $family['parents'], true) || count($family['parents']) < 2) { continue; }
            $private = false;
            foreach ($family['parents'] as $parent) { if (!isset($data['people'][$parent]) || GFH_Gedcom::living($data['people'][$parent])) { $private = true; } }
            if ($private) { continue; }
            $partners = [];
            foreach (array_diff($family['parents'], [$id]) as $partner) { $partners[] = $data['people'][$partner]['name']; }
            foreach (['MARR'=>'Marriage', 'DIV'=>'Divorce'] as $tag=>$label) {
                foreach (GFH_Gedcom::nodes($family['record'], $tag) as $node) { $events[] = ['node'=>$node, 'label'=>$label . ($partners ? ' · ' . implode(', ', $partners) : '')]; }
            }
        }
        if ($dated_only) { $events = array_values(array_filter($events, function($event) {
            return trim(GFH_Gedcom::value($event['node'], 'DATE')) !== '';
        })); }
        foreach ($events as $i=>&$event) { $event['order'] = $i; $event['sort'] = self::date_parts(GFH_Gedcom::value($event['node'], 'DATE')) ?: [PHP_INT_MAX,0,0]; } unset($event);
        usort($events, function($a,$b) { return ($a['sort'] <=> $b['sort']) ?: ($a['order'] <=> $b['order']); });
        return $events;
    }
    public static function timeline($data, $id) {
        $person = $data['people'][$id];
        if (GFH_Gedcom::living($person)) { return ''; }
        $events = self::events($data, $id);
        $birth = self::exact_date(GFH_Gedcom::value(GFH_Gedcom::nodes($person['record'], 'BIRT')[0] ?? ['children'=>[]], 'DATE'));
        $out = '<h3>Life timeline</h3>';
        if (!$events) { $out .= '<p>No dated life events recorded.</p>'; }
        else {
            $out .= '<ol class="gfh-timeline">'; $undated = false;
            foreach ($events as $event) {
                $node = $event['node']; $date = GFH_Gedcom::value($node, 'DATE'); $location = self::location($node);
                if ($event['sort'][0] === PHP_INT_MAX && !$undated) { $out .= '<li class="gfh-undated"><h4>Other recorded dates</h4><p>Dates that cannot be placed chronologically.</p></li>'; $undated = true; }
                $out .= '<li><p class="gfh-event-date">' . esc_html($date ?: 'Date not recorded') . '</p><h4>' . esc_html($event['label']) . '</h4>';
                $exact = self::exact_date($date);
                // Do not infer an age from qualified, partial or inconsistent dates.
                if ($birth && $exact && $exact >= $birth && !in_array($node['tag'], ['BIRT','BURI','CREM','PROB','WILL'], true)) {
                    $age = $birth->diff($exact)->y;
                    if ($age <= 120) { $out .= '<p class="gfh-age">Age ' . $age . '</p>'; }
                }
                $value = self::event_value($node);
                if ($value !== '') { $out .= '<p>' . nl2br(esc_html($value)) . '</p>'; }
                if ($location !== '') { $out .= '<p class="gfh-location">' . $location . '</p>'; }
                $out .= self::citations($data, $node) . '</li>';
            }
            $out .= '</ol>';
        }
        $sources = self::citations($data, $person['record']);
        if ($sources) { $out .= '<h3>Profile sources</h3>' . $sources; }
        return $out;
    }
}
