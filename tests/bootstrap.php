<?php

declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin(): void
{
    require dirname(__DIR__) . '/speedmate.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';
