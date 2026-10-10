<?php
/**
 * CLI regression test for AppMedia::sanitizeSvg().
 *   php tests/svg_sanitizer_test.php        (exit code 0 = all passed)
 *
 * Why it exists: the sanitiser once deleted the whole <style> block whenever it
 * contained a single url(...), which silently turned CorelDRAW/Inkscape logos
 * (colours live in CSS classes, gradients in fill:url(#id)) into black shapes,
 * while the upload still reported success. This pins that behaviour, plus the
 * hostile-input and compatibility cases found while fixing it.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$src = (string) file_get_contents(__DIR__ . '/../app/bootstrap.php');
$s = strpos($src, 'public static function sanitizeSvg');
$e = strpos($src, 'public static function imageSpec');
if ($s === false || $e === false) { fwrite(STDERR, "sanitizeSvg() not found in app/bootstrap.php\n"); exit(2); }
$tmp = tempnam(sys_get_temp_dir(), 'svgt') . '.php';
file_put_contents($tmp, "<?php\nclass AppMediaNext {\n" . substr($src, $s, $e - $s) . "\n}\n");
require $tmp;
@unlink($tmp);

$S = fn(string $x) => AppMediaNext::sanitizeSvg($x);
$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = '') { global $pass,$fail; if ($ok) $pass++; else $fail++; printf("%s  %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $detail ? "  [$detail]" : ''); }
function wf(string $x): bool { libxml_use_internal_errors(true); $d = new DOMDocument(); $r = $x !== '' && $d->loadXML($x, LIBXML_NONET); libxml_clear_errors(); return (bool)$r; }
$NS = 'xmlns="http://www.w3.org/2000/svg"';

// ---- fidelity: CorelDRAW-style export (colours in CSS classes + one gradient url) ----
$corel = '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n" .
  '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\r\n" .
  '<!-- Creator: CorelDRAW -->' . "\r\n" .
  '<svg ' . $NS . ' xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xodm="http://www.corel.com/coreldraw/odm/2003" width="85.9mm" height="52mm" viewBox="0 0 100 60">' .
  '<defs><style type="text/css"><![CDATA[ .fil0{fill:#B72928} .fil1{fill:#C8C8C8} .fil2{fill:url(#id0)} ]]></style>' .
  '<linearGradient id="id0"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#999"/></linearGradient></defs>' .
  '<rect class="fil0" width="50" height="30"/><rect class="fil1" x="50" width="50" height="30"/><rect class="fil2" y="30" width="100" height="30"/></svg>';
$o = $S($corel);
check('Corel-style: accepted + well-formed', $o !== '' && wf($o));
check('Corel-style: <style> block kept', stripos($o,'<style') !== false);
check('Corel-style: every colour rule kept', preg_match_all('/\.fil\d\{fill:/', $o) === 3);
check('Corel-style: same-document gradient url(#id0) kept', strpos($o,'url(#id0)') !== false);
check('Corel-style: DOCTYPE removed', stripos($o,'<!DOCTYPE') === false);

// ---- compatibility ----
$ai = '<?xml version="1.0"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" [<!ENTITY ns_svg "http://www.w3.org/2000/svg"><!ENTITY ns_xlink "http://www.w3.org/1999/xlink">]><svg xmlns="&ns_svg;" xmlns:xlink="&ns_xlink;" width="10" height="10"><rect width="10" height="10" fill="#c00"/></svg>';
$o = $S($ai); file_put_contents('/tmp/svgtest/ai_NEXT.svg', $o);
check('Illustrator DOCTYPE+ENTITY: accepted + well-formed', $o !== '' && wf($o));
check('Illustrator: entity expanded to its literal value', strpos($o,'xmlns="http://www.w3.org/2000/svg"') !== false);
check('entity value containing a quote is escaped, still well-formed', wf($S('<!DOCTYPE svg [<!ENTITY q \'a"b\'>]><svg '.$NS.' title="&q;"><rect width="1" height="1"/></svg>')));
check('Inkscape-style namespaces preserved', wf($S('<svg '.$NS.' xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" inkscape:version="1.2"><sodipodi:namedview id="n"/><rect width="1" height="1"/></svg>')));
check('internal <use href="#a"> preserved', strpos($S('<svg '.$NS.' xmlns:xlink="http://www.w3.org/1999/xlink"><defs><rect id="a" width="1" height="1"/></defs><use xlink:href="#a"/></svg>'), 'xlink:href="#a"') !== false);
check('embedded data:image/png <image> preserved', strpos($S('<svg '.$NS.' xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="data:image/png;base64,iVBORw0KGgo=" width="1" height="1"/></svg>'), 'data:image/png') !== false);
check('@import without semicolon does not eat following rules', strpos($S('<svg '.$NS.'><style>@import url(x.css)'."\n".'.a{fill:red}</style><rect class="a" width="1" height="1"/></svg>'), '.a{fill:red}') !== false);
check('non-SVG input rejected', $S('<html><body>x</body></html>') === '' && $S('') === '');

// ---- hostile input ----
$o = $S('<svg '.$NS.'><script>alert(1)</script><rect width="1" height="1"/></svg>');
check('<script> removed', $o !== '' && !preg_match('/<script/i',$o));
$o = $S('<svg '.$NS.'><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject><rect width="1" height="1"/></svg>');
check('<foreignObject> removed', !preg_match('/foreignObject/i',$o));
$o = $S('<svg '.$NS."\n\t".'onload="alert(1)"><rect width="1" height="1" onclick = \'x()\'/></svg>');
check('on* handlers removed (newline/tab/spaced/single-quoted)', $o !== '' && !preg_match('/\son[a-z]+\s*=/i',$o));
$o = $S('<svg '.$NS.'><scr<script></script>ipt>alert(1)</scr<script></script>ipt><rect width="1" height="1"/></svg>');
check('nested <script> reassembly cannot produce a live tag', !preg_match('/<script/i',$o));
$o = $S('<svg '.$NS.' xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(1)"><rect width="1" height="1"/></a></svg>');
check('javascript: href neutralised', !preg_match('/javascript:/i',$o));
$o = $S('<svg '.$NS.'><a><animate attributeName="href" values="javascript:alert(1)"/><rect width="1" height="1"/></a></svg>');
check('javascript: inside SMIL values neutralised', $o !== '' && !preg_match('/javascript:/i',$o));
$o = $S('<svg '.$NS.'><style>@import url(http://evil.example/x.css);.a{fill:red}</style><rect class="a" width="1" height="1"/></svg>');
check('external @import removed, local rule kept', $o !== '' && !preg_match('/@import/i',$o) && strpos($o,'.a{fill:red}') !== false);
$o = $S('<svg '.$NS.'><style>.a{fill:url(//evil.example/x)}.b{fill:url(https://evil.example/x)}.c{fill:url(#g)}</style><rect class="a" width="1" height="1"/></svg>');
check('proto-relative + https url() in <style> neutralised', !preg_match('#url\(\s*[\'"]?\s*(https?:|//)#i',$o), '');
check('same-document url(#g) kept', strpos($o,'url(#g)') !== false);
$o = $S('<svg '.$NS.'><rect fill="url(http://evil.example/x)" style="fill:url(//evil.example/y)" width="1" height="1"/></svg>');
check('external url() in attributes neutralised', $o !== '' && !preg_match('#url\(\s*[\'"]?\s*(https?:|//)#i',$o));
$o = $S('<?xml version="1.0"?><?xml-stylesheet href="http://evil.example/x.css" type="text/css"?><svg '.$NS.'><rect width="1" height="1"/></svg>');
check('xml-stylesheet PI removed', $o !== '' && stripos($o,'xml-stylesheet') === false);
check('billion-laughs / nested entities rejected', $S('<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "aaaa"><!ENTITY b "&a;&a;&a;&a;">]><svg '.$NS.'><text>&b;</text></svg>') === '');
check('external (SYSTEM) entity never resolved', $S('<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg '.$NS.'><text>&x;</text></svg>') === '');
check('undefined HTML entity (&nbsp;) rejected', $S('<svg '.$NS.'><text>&nbsp;</text></svg>') === '');
check('UTF-8 BOM tolerated', wf($S("\xEF\xBB\xBF".'<svg '.$NS.'><rect width="1" height="1"/></svg>')));
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
