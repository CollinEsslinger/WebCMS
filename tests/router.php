<?php
if (PHP_SAPI!=='cli-server') exit;
define('WEBCMS_TEST_MODE',true); define('WEBCMS_TEST_SERVER',true); define('WEBCMS_TEST_CONFIG',__DIR__.'/config.php');
ini_set('session.save_path',__DIR__.'/.runtime/sessions');
return require __DIR__.'/../router.php';
