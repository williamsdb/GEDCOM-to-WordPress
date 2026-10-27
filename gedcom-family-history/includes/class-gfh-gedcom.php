<?php
/** GEDCOM reader. Raw uploads are never copied to the public uploads directory. */
class GFH_Gedcom {
    public static function parse($text) {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (!preg_match('//u', $text)) {
            throw new RuntimeException('Please export your GEDCOM as UTF-8 (GEDCOM 5.5.1 or 7).');
        }
        $records = []; $stack = []; $count = 0; $head = false; $trailer = false;
        foreach (preg_split('/\r\n|\n|\r/', $text) as $line) {
            if (trim($line) === '') { continue; }
            if (!preg_match('/^(\d+) (?:@([^@\s]+)@ )?([A-Za-z0-9_]+)(?: (.*))?$/', $line, $m)) {
                throw new RuntimeException('Invalid GEDCOM line near line ' . ($count + 1) . '.');
            }
            if (++$count > 250000) { throw new RuntimeException('This file exceeds the 250,000-line import limit.'); }
            $level = (int) $m[1]; $tag = strtoupper($m[3]);
            if ($level > 30 || ($level > 0 && !isset($stack[$level - 1]))) { throw new RuntimeException('Invalid GEDCOM nesting.'); }
            $node = ['xref' => $m[2] ?? '', 'tag' => $tag, 'value' => $m[4] ?? '', 'children' => []];
            if ($level === 0) {
                $stack = [];
                if ($tag === 'HEAD') { $head = true; }
                if ($tag === 'TRLR') { $trailer = true; }
                // Person/family IDs must remain unambiguous. Source IDs are indexed
                // separately below. Some
                // exporters reuse IDs across proprietary record types (e.g. _PTF/_STF).
                // Keep those records separate without weakening person/family checks.
                $supported = in_array($tag, ['INDI', 'FAM'], true);
                if ($supported && empty($m[2])) { throw new RuntimeException('A person or family record is missing its identifier.'); }
                $key = $supported ? $m[2] : '#' . $count;
                if (isset($records[$key])) { throw new RuntimeException('Duplicate record identifier: ' . $key); }
                $records[$key] = $node; $stack[0] =& $records[$key];
            } else {
                $stack[$level - 1]['children'][] = $node;
                $index = count($stack[$level - 1]['children']) - 1;
                $stack[$level] =& $stack[$level - 1]['children'][$index];
                foreach (array_keys($stack) as $depth) { if ($depth > $level) { unset($stack[$depth]); } }
            }
        }
        if (!$head || !$trailer) { throw new RuntimeException('A complete GEDCOM HEAD and TRLR are required.'); }
        $people = []; $families = []; $sources = [];
        foreach ($records as $id => $record) {
            if ($record['tag'] === 'INDI') {
                $raw = self::value($record, 'NAME');
                $nameNode = self::nodes($record, 'NAME')[0] ?? ['children' => []];
                $surname = self::value($nameNode, 'SURN');
                if (!$surname && preg_match('~/([^/]*)/~', $raw, $match)) { $surname = $match[1]; }
                $people[$id] = ['id' => $id, 'name' => trim(preg_replace('~/~', '', $raw)) ?: 'Unnamed person', 'surname' => trim($surname), 'record' => $record];
            } elseif ($record['tag'] === 'SOUR' && $record['xref'] !== '') {
                if (isset($sources[$record['xref']])) { throw new RuntimeException('Duplicate source identifier: ' . $record['xref']); }
                $sources[$record['xref']] = $record;
            } elseif ($record['tag'] === 'FAM') {
                $families[$id] = ['parents' => array_values(array_unique(array_merge(self::refs($record, 'HUSB'), self::refs($record, 'WIFE')))), 'children' => self::refs($record, 'CHIL'), 'record' => $record];
            }
        }
        if (!$people) { throw new RuntimeException('No people were found in this GEDCOM.'); }
        return ['people' => $people, 'families' => $families, 'sources' => $sources];
    }
    public static function nodes($node, $tag) { return array_values(array_filter($node['children'], function($child) use ($tag) { return $child['tag'] === $tag; })); }
    public static function value($node, $tag) { $nodes = self::nodes($node, $tag); return isset($nodes[0]) ? self::text($nodes[0]) : ''; }
    public static function text($node) {
        $value = $node['value'];
        foreach ($node['children'] as $child) {
            if ($child['tag'] === 'CONT') { $value .= "\n" . self::text($child); }
            if ($child['tag'] === 'CONC') { $value .= self::text($child); }
        }
        return $value;
    }
    public static function refs($node, $tag) { return array_map(function($n) { return trim($n['value'], '@'); }, self::nodes($node, $tag)); }
    public static function living($person) {
        foreach (self::nodes($person['record'], 'DEAT') as $death) {
            if (strtoupper(trim($death['value'])) === 'Y' || self::value($death, 'DATE') !== '' || self::value($death, 'PLAC') !== '') { return false; }
        }
        foreach (self::nodes($person['record'], 'BURI') as $burial) {
            if (strtoupper(trim($burial['value'])) === 'Y' || self::value($burial, 'DATE') !== '' || self::value($burial, 'PLAC') !== '') { return false; }
        }
        return true;
    }
}
