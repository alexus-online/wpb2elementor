<?php

if (!function_exists('esc_html')) {
    function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
}

require_once __DIR__ . '/../includes/class-parser.php';
require_once __DIR__ . '/../includes/class-mapper.php';
require_once __DIR__ . '/../includes/class-converter.php';

function assert_equal($a, $b, $msg = '') {
    if ($a !== $b) {
        echo "FAIL: $msg — expected " . json_encode($b) . " got " . json_encode($a) . "\n";
    } else {
        echo "PASS: $msg\n";
    }
}
function assert_not_empty($a, $msg = '') {
    if (empty($a)) { echo "FAIL: $msg is empty\n"; } else { echo "PASS: $msg\n"; }
}

$parser    = new WPB2EL_Parser();
$mapper    = new WPB2EL_Mapper();
$converter = new WPB2EL_Converter( $mapper );

// Test 1: outer section
$nodes  = $parser->parse('[vc_row][vc_column][vc_custom_heading text="Hello"][/vc_custom_heading][/vc_column][/vc_row]');
$result = $converter->convert( $nodes );
assert_not_empty($result, 'result not empty');
assert_equal($result[0]['elType'], 'section', 'outer section');
assert_equal($result[0]['elements'][0]['elType'], 'column', 'inner column');

// Test 2: IDs are 8 chars
assert_equal(strlen($result[0]['id']), 8, 'ID is 8 chars');

// Test 3: text-editor widget content
$nodes  = $parser->parse('[vc_row][vc_column][vc_column_text]Some text[/vc_column_text][/vc_column][/vc_row]');
$result = $converter->convert( $nodes );
$widget = $result[0]['elements'][0]['elements'][0];
assert_equal($widget['widgetType'], 'text-editor', 'text widget type');
assert_equal($widget['settings']['editor'], 'Some text', 'text content in settings');

// Test 4: unknown widget → html placeholder
$nodes  = $parser->parse('[vc_row][vc_column][mkdf_weird_thing param="x"][/mkdf_weird_thing][/vc_column][/vc_row]');
$result = $converter->convert( $nodes );
$widget = $result[0]['elements'][0]['elements'][0];
assert_equal($widget['widgetType'], 'html', 'unknown → html');
assert_not_empty($widget['settings']['html'], 'placeholder has html');
