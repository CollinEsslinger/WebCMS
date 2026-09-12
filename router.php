<?php
/** Router for PHP's development server. Apache uses .htaccess. */
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if (str_contains($path,'..') || str_contains($path,'\\') || str_contains($path,"\0") || preg_match('#/(?:\.[^/]*|core|tests|docs|templates)(?:/|$)#i',$path) || preg_match('#^/(?:config(?:\.example)?\.php|router\.php)$#i',$path) || (str_starts_with($path,'/storage/') && !str_starts_with($path,'/storage/uploads/')) || preg_match('#^/storage/.*\.(php|phtml|phar)$#i',$path)) {
    http_response_code(404); exit('Nicht gefunden.');
}
$file=__DIR__.$path;
if (is_dir($file)) $file=rtrim($file,'/').'/index.php';
if (is_file($file)) {
    if (strtolower(pathinfo($file,PATHINFO_EXTENSION))!=='php') return false;
    $_SERVER['SCRIPT_NAME']=str_replace('\\','/',substr($file,strlen(__DIR__)));
    require $file; return true;
}
$_GET['slug']=trim($path,'/'); $_SERVER['SCRIPT_NAME']='/page.php'; require __DIR__.'/page.php';
