<?php
/**
 * User: Jerry
 * Date: 6/20/2017
 * Time: 12:47 PM
 */

declare(strict_types = 1);

//Load CFG file (basic function script is loaded in the cfg file as also).
require __DIR__ . '/core/_inc/cfg.php';


\core\db\redis::$redis_db = 1;
$db_redis = \core\db\redis::connect();
$db_redis->set('aaaaaa', 'bbbbbbbbbb', 500);
