<?php
define('WEBCMS_TEST_MODE',true);
require __DIR__.'/config.php';
require __DIR__.'/../core/db.php'; require __DIR__.'/../core/functions.php'; require __DIR__.'/../core/auth.php'; require __DIR__.'/../core/cms.php'; require __DIR__.'/../core/render.php'; require __DIR__.'/../core/feeds.php';
date_default_timezone_set('Europe/Berlin');
$_SERVER['HTTP_HOST']='localhost:8099'; $_SERVER['SCRIPT_NAME']='/index.php'; $_SERVER['REQUEST_METHOD']='GET';
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
run_install(); cms_migrate();
$_SESSION['user_id']=1;
