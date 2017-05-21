<?php
/**
 * run with command
 * php ss.php start
 */

ini_set('display_errors', 'on');
require_once __DIR__ . '/vendor/autoload.php';

$ss = new \SS\ShadowServer();
$ss->start();