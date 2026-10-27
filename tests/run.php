<?php
// Lightweight rendering harness; no WordPress installation required.
define('ABSPATH', __DIR__);
function add_action(...$args) {} function add_shortcode(...$args) {}
function get_option($key, $default) { return $key === 'gfh_places' ? ($GLOBALS['places'] ?? $default) : ($GLOBALS['dataset'] ?? $default); }
function get_permalink($id = null) { return 'https://example.test/family/'; }
function add_query_arg($args, $url) { return $url . ($args ? '?' . http_build_query($args) : ''); }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return esc_html($s); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($s)); }
function sanitize_text_field($s) { return strip_tags($s); } function wp_unslash($s) { return stripslashes($s); }
require __DIR__ . '/../gedcom-family-history/gedcom-family-history.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "PASS: $message\n"; }
$ged = "0 HEAD\n1 CHAR UTF-8\n0 @I1@ INDI\n1 NAME Alice /Example/\n1 BIRT\n2 DATE 1 JAN 1980\n2 PLAC SecretTown\n1 NOTE Secret note\n0 @I2@ INDI\n1 NAME Bob /Example/\n1 DEAT Y\n1 BIRT\n2 DATE ABT 1900\n2 PLAC London\n1 OCCU Carpenter\n1 NOTE First line\n2 CONT Second line\n0 @I3@ INDI\n1 NAME <script>alert(1)</script> /Other/\n1 DEAT\n2 DATE 1950\n0 @F1@ FAM\n1 HUSB @I2@\n1 WIFE @I1@\n1 CHIL @I3@\n1 MARR\n2 DATE 1999\n2 PLAC SecretWedding\n0 TRLR\n";
$data = GFH_Gedcom::parse($ged);
check(count($data['people']) === 3, 'Parses individuals');
check($data['families']['F1']['children'] === ['I3'], 'Resolves family pointers');
check(GFH_Gedcom::living($data['people']['I1']), 'Unknown death status defaults to living');
check(!GFH_Gedcom::living($data['people']['I2']), 'Explicit death flag establishes deceased status');
$GLOBALS['dataset'] = array_merge($data, ['root'=>'I1', 'page'=>1]);
$html = GFH_Plugin::render();
check(strpos($html, 'SecretTown') === false && strpos($html, '1980') === false && strpos($html, 'Secret note') === false, 'Living profile suppresses dates, places and notes');
check(strpos($html, 'SecretWedding') === false && strpos($html, '1999') === false, 'Marriage involving a living person stays private');
check(strpos($html, '<script>') === false, 'Escapes imported names');
$_GET = ['gfh_person'=>'I2']; $html = GFH_Plugin::render();
check(strpos($html, 'London') !== false && strpos($html, 'Carpenter') === false, 'Deceased timeline includes dated events and excludes undated occupations');
check(strpos($html, 'Second line') !== false, 'Preserves continued notes');
check(strpos($html, 'SecretWedding') === false, 'Deceased spouse cannot expose living partner marriage details');
$_GET = ['gfh_view'=>'surnames'];
check(strpos(GFH_Plugin::render(), 'Example') !== false, 'Renders surname index');
$_GET = ['gfh_view'=>'names'];
check(strpos(GFH_Plugin::render(), 'Alice') !== false, 'Renders names index');
foreach (["0 HEAD\n0 TRLR", "0 HEAD\n2 NAME Invalid\n0 TRLR", "0 HEAD\n0 @I1@ INDI\n0 @I1@ INDI\n0 TRLR", "0 HEAD\n0 @I1@ INDI"] as $bad) {
    $rejected = false; try { GFH_Gedcom::parse($bad); } catch (RuntimeException $e) { $rejected = true; }
    check($rejected, 'Rejects incomplete or invalid input');
}
// Cycles must terminate, and grandchildren tree links remain keyboard-accessible anchors.
$GLOBALS['dataset']['families']['F1']['children'][] = 'I2'; $_GET = ['gfh_person'=>'I2'];
check(strlen(GFH_Plugin::render()) < 20000, 'Cyclic ancestry terminates');
$custom = str_replace("0 TRLR", "0 @TF0@ _PTF\n1 NAME Private extension\n0 @TF0@ _STF\n1 NAME Other extension\n0 @I1@ _CUSTOM\n0 TRLR", $ged);
$parsed = GFH_Gedcom::parse($custom);
check(count($parsed['people']) === 3 && count($parsed['families']) === 1, 'Repeated proprietary IDs do not overwrite people or families');
check($parsed['people']['I1']['name'] === 'Alice Example', 'Custom record sharing a person ID does not alter the person');
foreach (["0 HEAD\n0 @I1@ INDI\n0 @I1@ FAM\n0 TRLR", "0 HEAD\n0 INDI\n0 TRLR"] as $bad) {
    $rejected = false; try { GFH_Gedcom::parse($bad); } catch (RuntimeException $e) { $rejected = true; }
    check($rejected, 'Still rejects ambiguous or missing person/family identifiers');
}
if (isset($argv[1])) {
    $actual = GFH_Gedcom::parse(file_get_contents($argv[1]));
    check(count($actual['people']) === 607 && count($actual['families']) === 212, 'User GEDCOM imports all 607 people and 212 families');
    $GLOBALS['dataset'] = array_merge($actual, ['root'=>array_key_first($actual['people']), 'page'=>1]);
    foreach (array_keys($actual['people']) as $id) {
        $_GET = ['gfh_person'=>$id];
        $html = GFH_Plugin::render();
        if (strpos($html, 'Family tree') === false) { throw new RuntimeException('Profile rendering failed'); }
    }
    check(true, 'All imported person profiles render');
}
$GLOBALS['dataset'] = array_merge(GFH_Gedcom::parse($ged), ['root'=>'I1', 'page'=>1]);
$_GET = ['gfh_person'=>'I2'];
$html = GFH_Plugin::render();
check(strpos($html, 'gfh-grandparents') !== false && strpos($html, 'gfh-children') !== false, 'Chart includes ancestor and descendant rows');
check(strpos($html, 'aria-current="page"') !== false && strpos($html, '<em>Example</em>') !== false, 'Chart marks selected person and italicizes surnames');
$evidence = "0 HEAD\n0 @P1@ INDI\n1 NAME Historical /Person/\n1 BIRT\n2 DATE 10 JAN 1900\n2 SOUR @S1@\n3 PAGE Folio 12\n3 DATA\n4 TEXT <script>evidence</script>\n1 DEAT\n2 DATE 9 JAN 1980\n1 RESI\n2 DATE ABT 1925\n2 PLAC Historic Town\n1 OCCU Gardener\n0 @P2@ INDI\n1 NAME Living /Person/\n1 BIRT\n2 DATE 1 JAN 1990\n2 SOUR @S2@\n0 @S1@ SOUR\n1 TITL Birth register\n1 AUTH Archive\n1 PUBL Collection\n0 @S2@ SOUR\n1 TITL Hidden living source\n0 @F1@ FAM\n1 HUSB @P1@\n1 WIFE @P2@\n1 MARR\n2 DATE 1930\n2 SOUR @S2@\n0 TRLR";
$parsed = GFH_Gedcom::parse($evidence);
check(count($parsed['sources']) === 2, 'Imports shared source records');
$GLOBALS['dataset'] = array_merge($parsed, ['root'=>'P1','page'=>1]); $_GET=[];
$html = GFH_Plugin::render();
check(strpos($html, '10 JAN 1900') < strpos($html, 'ABT 1925') && strpos($html, 'ABT 1925') < strpos($html, '9 JAN 1980'), 'Timeline sorts partial dates chronologically');
check(strpos($html, 'Age 79') !== false && strpos($html, 'Age 25') === false, 'Computes exact ages without inferring ages from qualified dates');
check(strpos($html, 'Birth register') !== false && strpos($html, 'Folio 12') !== false && strpos($html, '<script>') === false, 'Resolves citations and escapes source text');
check(strpos($html, 'Hidden living source') === false, 'Marriage timeline and citations protect living partners');
check(strpos($html, 'Gardener') === false && strpos($html, 'Date not recorded') === false, 'Undated facts are excluded from the life timeline');
$_GET=['gfh_person'=>'P2']; $html=GFH_Plugin::render();
check(strpos($html, 'Life timeline') === false && strpos($html, 'Hidden living source') === false && strpos($html, '1990') === false, 'Living profiles reveal neither timelines nor sources');
unset($GLOBALS['dataset']['sources']); $_GET=['gfh_person'=>'P1'];
check(strpos(GFH_Plugin::render(), 're-import') !== false, 'Old datasets display useful missing-source guidance');
$locationNode = ['children'=>[
    ['tag'=>'PLAC','value'=>'Southwark,,Greater London,England,United Kingdom','children'=>[]],
    ['tag'=>'ADDR','value'=>"St. Paul's Church",'children'=>[]]
]];
check(GFH_Profile::location($locationNode) === "St. Paul&#039;s Church<br>Southwark, Greater London, England, United Kingdom", 'Address appears above place with empty place components removed');
$locationNode['children'][1]['value'] = '<script>church</script>';
check(strpos(GFH_Profile::location($locationNode), '<script>') === false, 'Address text is escaped');
$privateGed = str_replace('2 PLAC SecretTown', "2 PLAC SecretTown\n2 ADDR SecretAddress", $ged);
$privateGed = str_replace('2 PLAC SecretWedding', "2 PLAC SecretWedding\n2 ADDR SecretMarriageAddress", $privateGed);
$GLOBALS['dataset'] = array_merge(GFH_Gedcom::parse($privateGed), ['root'=>'I1','page'=>1]); $_GET=[];
check(strpos(GFH_Plugin::render(), 'SecretAddress') === false, 'Living addresses remain private');
$_GET=['gfh_person'=>'I2'];
check(strpos(GFH_Plugin::render(), 'SecretMarriageAddress') === false, 'Marriage addresses with living partners remain private');
check(GFH_Profile::date_parts('08/03/1908') === [1908,3,8], 'Numeric dates use day/month/year order');
check(GFH_Profile::date_parts('19 jun 1921') === [1921,6,19], 'Textual dates accept mixed case month names');
check(GFH_Profile::date_parts('  19   JUN  1921  ') === [1921,6,19], 'Textual dates tolerate extra whitespace');
check(GFH_Profile::date_parts('31/02/1921') === null && GFH_Profile::date_parts('31 FEB 1921') === null, 'Invalid calendar dates are not silently normalized');
check(GFH_Profile::date_parts('ABT 1908') === [1908,0,0] && GFH_Profile::date_parts('1933') === [1933,0,0], 'Qualified dates and years still sort');
$mixed = "0 HEAD\n0 @P@ INDI\n1 NAME Test /Person/\n1 RESI\n2 DATE 1933\n1 BIRT\n2 DATE 08/03/1908\n1 CENS\n2 DATE 19/06/1921\n1 EVEN\n2 DATE 01 jan 1930\n1 DEAT\n2 DATE 08/03/1980\n0 TRLR";
$GLOBALS['dataset'] = array_merge(GFH_Gedcom::parse($mixed), ['root'=>'P','page'=>1]); $_GET=[];
$html = GFH_Plugin::render();
check(strpos($html, '08/03/1908') < strpos($html, '19/06/1921') && strpos($html, '19/06/1921') < strpos($html, '01 jan 1930') && strpos($html, '01 jan 1930') < strpos($html, '1933'), 'Mixed numeric and textual dates render in chronological order');
check(strpos($html, 'Other recorded dates') === false && strpos($html, 'Age 72') !== false, 'Recognized numeric dates leave the fallback section and support exact ages');
$relations = "0 HEAD\n0 @A@ INDI\n1 NAME Alex /Example/\n1 FAMC @F1@\n2 PEDI birth\n0 @B@ INDI\n1 NAME Full /Sibling/\n0 @C@ INDI\n1 NAME Half /Sibling/\n0 @D@ INDI\n1 NAME Adopted /Sibling/\n1 FAMC @F1@\n2 PEDI adopted\n0 @E@ INDI\n1 NAME Partial /Sibling/\n0 @P1@ INDI\n1 NAME Father /Example/\n0 @P2@ INDI\n1 NAME Mother /Example/\n0 @P3@ INDI\n1 NAME Other /Parent/\n0 @F1@ FAM\n1 HUSB @P1@\n1 WIFE @P2@\n1 CHIL @A@\n1 CHIL @B@\n0 @F2@ FAM\n1 HUSB @P1@\n1 WIFE @P3@\n1 CHIL @C@\n0 @F3@ FAM\n1 HUSB @P1@\n1 CHIL @E@\n0 TRLR";
$rel = GFH_Gedcom::parse($relations);
$groups = GFH_Relatives::siblings($rel, 'A', GFH_Relatives::families($rel, 'A'));
check($groups['Siblings'] === ['B'] && $groups['Half-siblings'] === ['C'], 'Distinguishes siblings and half-siblings from parent links');
check($groups['Siblings through an adoptive or foster family'] === ['D'], 'Finds adopted siblings through FAMC even without reciprocal CHIL');
check($groups['Siblings sharing a recorded parent'] === ['E'], 'Incomplete parentage is not asserted to be half-sibling parentage');
$GLOBALS['dataset'] = array_merge($rel, ['root'=>'A','page'=>1]); $_GET=[];
$html=GFH_Plugin::render();
check(strpos($html, 'Father:') !== false && strpos($html, 'Mother:') !== false && strpos($html, 'Half-siblings') !== false, 'Profiles show labelled linked relatives');
$_GET=['gfh_person'=>'D'];
check(strpos(GFH_Plugin::render(), 'Adoptive father') !== false, 'Adoptive parent labels follow PEDI');
$_GET=['gfh_view'=>'names','gfh_search'=>'EXAMPLE alex'];
$html=GFH_Plugin::render();
check(strpos($html, '1 matching people') !== false && strpos($html, 'Alex Example') !== false && strpos($html, 'Full Sibling') === false, 'Search matches given names and surnames in either order without case sensitivity');
$_GET=['gfh_search'=>'no such name'];
check(strpos(GFH_Plugin::render(), 'No names matched') !== false, 'Empty searches provide useful feedback');
$_GET=['gfh_search'=>'<script>test</script>'];
check(strpos(GFH_Plugin::render(), '<script>') === false, 'Search input is escaped');
$mapGed = "0 HEAD\n0 @A@ INDI\n1 NAME Historical /Map/\n1 DEAT Y\n1 BIRT\n2 DATE 1900\n2 PLAC Oxford,,England\n3 MAP\n4 LATI N51.752\n4 LONG W1.258\n1 RESI\n2 DATE 1920\n2 PLAC Oxford, England\n3 MAP\n4 LATI 51.752\n4 LONG -1.258\n1 EVEN\n2 DATE 1930\n2 PLAC Unlocated, England\n0 @B@ INDI\n1 NAME Living /Map/\n1 RESI\n2 DATE 1990\n2 PLAC Private Place\n3 MAP\n4 LATI N52\n4 LONG E1\n0 @F@ FAM\n1 HUSB @A@\n1 WIFE @B@\n1 MARR\n2 DATE 1950\n2 PLAC Private Wedding\n3 MAP\n4 LATI N53\n4 LONG E2\n0 TRLR";
$mapData = GFH_Gedcom::parse($mapGed);
$result = GFH_Maps::points($mapData, 'A', []);
check(count($result['points']) === 1 && count($result['points'][0]['events']) === 2, 'Groups events at identical map coordinates');
check($result['points'][0]['lng'] === -1.258 && $result['missing'] === ['Unlocated, England'], 'Reads hemisphere coordinates and reports missing locations');
check(GFH_Maps::points($mapData, 'B', []) === ['points'=>[], 'missing'=>[]], 'Living profiles emit no map data');
check(GFH_Maps::render($mapData, 'B') === '', 'Living profiles emit no map markup');
$catalog = GFH_Maps::catalog($mapData);
check(count($catalog) === 2 && !isset($catalog[GFH_Maps::key('Private Wedding')]), 'Admin place catalog excludes living-person and private marriage locations');
$key = GFH_Maps::key('Oxford, England');
$saved = [$key=>['lat'=>50,'lng'=>-1,'accuracy'=>'Town/parish (approximate)']];
check(GFH_Maps::points($mapData, 'A', $saved)['points'][0]['lat'] === 50.0, 'Saved place overrides apply across events');
check(GFH_Maps::coordinate('0', true) === 0.0 && GFH_Maps::coordinate('E180', false) === 180.0, 'Accepts zero coordinates and valid boundaries');
check(GFH_Maps::coordinate('N91', true) === null && GFH_Maps::coordinate('W181', false) === null && GFH_Maps::coordinate('E12', true) === null && GFH_Maps::coordinate('junk', false) === null, 'Rejects invalid map coordinates');
$GLOBALS['places'] = $saved;
$html = GFH_Maps::render($mapData, 'A');
check(strpos($html, 'Load map') !== false && strpos($html, 'Mapped places and events') !== false && strpos($html, 'Private Wedding') === false, 'Map provides load control and accessible fallback without private events');
foreach ($mapData['people']['A']['record']['children'] as &$node) { if ($node['tag'] === 'DEAT') { $node = ['tag'=>'DEAT','value'=>'N','children'=>[]]; } } unset($node);
check(GFH_Maps::points($mapData, 'A', $saved)['points'] === [], 'Saved coordinates cannot expose a profile whose status changes to living');
unset($GLOBALS['places']);
$venueGed = str_replace('2 PLAC Oxford,,England', "2 ADDR St Pauls Church\n2 PLAC Oxford,,England", $mapGed);
$venueGed = str_replace('0 @B@ INDI', "1 OCCU Carpenter\n2 DATE 1925\n2 PLAC Oxford,England\n3 MAP\n4 LATI N51.752\n4 LONG W1.258\n1 OCCU\n2 TYPE Blacksmith\n2 DATE 1935\n0 @B@ INDI", $venueGed);
$venueData = GFH_Gedcom::parse($venueGed);
$points = GFH_Maps::points($venueData, 'A', []);
check($points['points'][0]['events'][0]['location'] === ['St Pauls Church', 'Oxford, England'], 'Map popup data includes venue above cleaned place');
check(strpos(GFH_Maps::render($venueData, 'A'), 'St Pauls Church<br>Oxford, England') !== false, 'Map list includes venue above place');
check(strpos(GFH_Maps::render($venueData, 'A'), 'Carpenter') !== false, 'Map occupation entries contain actual occupation');
$timeline = GFH_Profile::timeline($venueData, 'A');
check(strpos($timeline, 'Carpenter') !== false && strpos($timeline, 'Blacksmith') !== false, 'Timeline occupations display values and TYPE fallback');
$statsData = GFH_Gedcom::parse($evidence);
$stats = GFH_Statistics::collect($statsData);
check($stats['people'] === 2 && $stats['deceased'] === 1 && $stats['living'] === 1, 'Statistics report people and conservative living status counts');
check($stats['ages'] === [79] && $stats['birth_years'] === [1900], 'Lifespan and birth statistics exclude living people');
check(!isset($stats['events']['Marriage']) && !isset($stats['places']['Private Wedding']), 'Statistics exclude marriages involving living partners');
check(isset($stats['events']['Occupation']) && $stats['places']['Historic Town'] === 1, 'Statistics count eligible undated facts and public places');
$statsData['page'] = 1; $GLOBALS['dataset']=$statsData; $_GET=['gfh_view'=>'statistics'];
$html=GFH_Plugin::render();
check(strpos($html, 'Family statistics') !== false && strpos($html, '79.0 years') !== false && strpos($html, '1990') === false, 'Statistics page renders without living birth years');
check(strpos(GFH_Plugin::statistics(), 'Family statistics') !== false, 'Standalone statistics shortcode renders');
$publicFamily = str_replace('0 @P2@ INDI', "0 @P2@ INDI\n1 DEAT Y", $evidence);
$stats=GFH_Statistics::collect(GFH_Gedcom::parse($publicFamily));
check($stats['events']['Marriage'] === 1, 'Shared marriage events counted once per family');
$empty=GFH_Statistics::collect(['people'=>[], 'families'=>[]]);
check($empty['people'] === 0 && $empty['ages'] === [], 'Empty datasets do not produce invalid averages');
if (isset($argv[1])) {
    $realStats = GFH_Statistics::collect(GFH_Gedcom::parse(file_get_contents($argv[1])));
    check($realStats['people'] === 607 && $realStats['families'] === 212, 'Actual GEDCOM statistics include all people and family records');
}
echo "All tests passed.\n";
