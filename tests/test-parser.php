<?php
require_once __DIR__ . '/../includes/class-parser.php';

function assert_equal($a, $b, $msg = '') {
    if ($a !== $b) {
        echo "FAIL: $msg\n";
        echo "  Expected: " . print_r($b, true) . "\n";
        echo "  Got:      " . print_r($a, true) . "\n";
    } else {
        echo "PASS: $msg\n";
    }
}

$parser = new WPB2EL_Parser();

$result = $parser->parse('[vc_column_text]Hello World[/vc_column_text]');
assert_equal(count($result), 1, 'single node count');
assert_equal($result[0]['tag'], 'vc_column_text', 'tag name');
assert_equal($result[0]['content'], 'Hello World', 'text content');
assert_equal($result[0]['children'], [], 'no children');

$result = $parser->parse('[vc_row][vc_column][vc_column_text]Hi[/vc_column_text][/vc_column][/vc_row]');
assert_equal($result[0]['tag'], 'vc_row', 'outer tag');
assert_equal($result[0]['children'][0]['tag'], 'vc_column', 'middle tag');
assert_equal($result[0]['children'][0]['children'][0]['tag'], 'vc_column_text', 'inner tag');
assert_equal($result[0]['children'][0]['children'][0]['content'], 'Hi', 'inner content');

$result = $parser->parse('[vc_column width="1/2"][/vc_column]');
assert_equal($result[0]['attrs']['width'], '1/2', 'width attribute');

$result = $parser->parse('');
assert_equal($result, [], 'empty input');

$result = $parser->parse('Just some text');
assert_equal($result[0]['tag'], '__text__', 'plain text tag');
assert_equal($result[0]['content'], 'Just some text', 'plain text content');
