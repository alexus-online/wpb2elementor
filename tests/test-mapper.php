<?php
require_once __DIR__ . '/../includes/class-mapper.php';

function assert_equal($a, $b, $msg = '') {
    if ($a !== $b) {
        echo "FAIL: $msg — expected " . json_encode($b) . " got " . json_encode($a) . "\n";
    } else {
        echo "PASS: $msg\n";
    }
}

$mapper = new WPB2EL_Mapper();

$r = $mapper->map('vc_row');
assert_equal($r['elType'], 'section', 'vc_row → section');
assert_equal($r['known'], true, 'vc_row known');

$r = $mapper->map('vc_column');
assert_equal($r['elType'], 'column', 'vc_column → column');

$r = $mapper->map('vc_column_text');
assert_equal($r['elType'], 'widget', 'vc_column_text elType');
assert_equal($r['widgetType'], 'text-editor', 'vc_column_text widgetType');

$r = $mapper->map('vc_custom_heading');
assert_equal($r['widgetType'], 'heading', 'heading widget');

$r = $mapper->map('vc_single_image');
assert_equal($r['widgetType'], 'image', 'image widget');

$r = $mapper->map('vc_btn');
assert_equal($r['widgetType'], 'button', 'button widget');

$r = $mapper->map('vc_empty_space');
assert_equal($r['widgetType'], 'spacer', 'spacer widget');

$r = $mapper->map('mkdf_something_unknown');
assert_equal($r['known'], false, 'unknown widget');
assert_equal($r['elType'], 'widget', 'unknown fallback elType');
assert_equal($r['widgetType'], 'html', 'unknown fallback widgetType');
