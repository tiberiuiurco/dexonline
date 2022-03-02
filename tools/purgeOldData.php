<?php

require_once __DIR__ . '/../lib/Core.php';

Log::notice('started');

$now = time();

Log::info('removing old login cookies');
$thirtyOneDaysAgo = $now - 31 * 24 * 3600;
$cookies = Model::factory('Cookie')->where_lt('createDate', $thirtyOneDaysAgo)->find_many();
foreach ($cookies as $cookie) {
  $cookie->delete();
}

Log::info('removing old password tokens');
$yesterday = $now - 24 * 3600;
$pts = Model::factory('PasswordToken')->where_lt('createDate', $yesterday)->find_many();
foreach($pts as $pt) {
  $pt->delete();
}

Log::notice('finished');
