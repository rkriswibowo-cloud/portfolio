<?php
declare(strict_types=1);
require_once __DIR__ . '/config/content.php';
clear_user_session();
redirect('user_login.php');
