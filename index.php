<?php


use core\lib\std\os;

require __DIR__ . '/core/ns.php';
$os = new os();
$res = $os->php_path();
var_dump($res);