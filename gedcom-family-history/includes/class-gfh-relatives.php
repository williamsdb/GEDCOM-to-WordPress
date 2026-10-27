<?php
/** Relationships are derived from family links, never from names. */
class GFH_Relatives {
    public static function families($data, $id) {
        $result = [];
        foreach ($data['families'] as $fid=>$family) {
            $pedigree = ''; $adopted = [];
            foreach (GFH_Gedcom::nodes($data['people'][$id]['record'], 'FAMC') as $link) {
                if (trim($link['value'], '@') === (string)$fid) { $result[$fid] = $family; $pedigree = strtolower(trim(GFH_Gedcom::value($link, 'PEDI'))); }
            }
            foreach (GFH_Gedcom::nodes($data['people'][$id]['record'], 'ADOP') as $event) {
                foreach (GFH_Gedcom::nodes($event, 'FAMC') as $link) {
                    if (trim($link['value'], '@') === (string)$fid) {
                        $result[$fid] = $family;
                        $who = strtoupper(trim(GFH_Gedcom::value($link, 'ADOP')));
                        foreach (['HUSB','WIFE'] as $role) {
                            if ($who === $role || $who === 'BOTH' || $who === '') { $adopted = array_merge($adopted, GFH_Gedcom::refs($family['record'], $role)); }
                        }
                    }
                }
            }
            if (in_array($id, $family['children'], true)) { $result[$fid] = $family; }
            if (isset($result[$fid])) { $result[$fid]['pedigree'] = $pedigree; $result[$fid]['adopted'] = $adopted; }
        }
        return $result;
    }
    public static function siblings($data, $id, $families) {
        $groups = ['Siblings'=>[], 'Half-siblings'=>[], 'Siblings sharing a recorded parent'=>[], 'Siblings through an adoptive or foster family'=>[]];
        $own = [];
        foreach ($families as $family) { $own = array_merge($own, $family['parents']); }
        $relatedFamilies = []; $candidates = [];
        foreach ($data['families'] as $fid=>$family) {
            if (isset($families[$fid]) || array_intersect($own, $family['parents'])) {
                $relatedFamilies[$fid] = true;
                foreach ($family['children'] as $child) { $candidates[$child] = true; }
            }
        }
        foreach ($data['people'] as $other=>$person) {
            // Include one-way child-to-family links without scanning every family per person.
            $links = GFH_Gedcom::nodes($person['record'], 'FAMC');
            foreach (GFH_Gedcom::nodes($person['record'], 'ADOP') as $adoption) { $links = array_merge($links, GFH_Gedcom::nodes($adoption, 'FAMC')); }
            foreach ($links as $link) { if (isset($relatedFamilies[trim($link['value'], '@')])) { $candidates[$other] = true; } }
        }
        foreach (array_keys($candidates) as $other) {
            if (!isset($data['people'][$other])) { continue; }
            $person = $data['people'][$other];
            if ((string)$other === (string)$id) { continue; }
            $best = null;
            foreach (self::families($data, $other) as $fid=>$candidate) {
                foreach ($families as $ownFid=>$family) {
                    $shared = array_intersect($family['parents'], $candidate['parents']);
                    if ((string)$fid !== (string)$ownFid && !$shared) { continue; }
                    $special = in_array($family['pedigree'], ['adopted','foster'], true) || in_array($candidate['pedigree'], ['adopted','foster'], true) || $family['adopted'] || $candidate['adopted'];
                    if ($special) { $rank=3; }
                    elseif ((string)$fid === (string)$ownFid || (count($shared) === 2 && count($family['parents']) === 2 && count($candidate['parents']) === 2)) { $rank=0; }
                    elseif (count($shared) === 1 && count($family['parents']) === 2 && count($candidate['parents']) === 2) { $rank=1; }
                    else { $rank=2; }
                    $best = $best === null ? $rank : min($best, $rank);
                }
            }
            if ($best !== null) { $keys = array_keys($groups); $groups[$keys[$best]][] = $other; }
        }
        return array_filter($groups);
    }
}
