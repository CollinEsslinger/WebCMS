<?php
require __DIR__.'/core/bootstrap.php'; require __DIR__.'/core/render.php';
$result=$_SESSION['public_success']??['message'=>'Es liegt keine neue Bestätigung vor.','back'=>'/']; unset($_SESSION['public_success']);
echo cms_public_document('Vielen Dank','<div class="alert alert-success" role="status">'.e($result['message']).'</div><p><a href="'.e(site_url($result['back'])).'">Zurück zur Website →</a></p>');
