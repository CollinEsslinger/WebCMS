<?php
declare(strict_types=1);
require __DIR__ . '/../core/bootstrap.php';
logout_user();
redirect('/admin/login.php');
