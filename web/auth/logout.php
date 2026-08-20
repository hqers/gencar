<?php
require_once __DIR__ . '/../config.php';
session_name(SESSION_NAME);
session_start();
session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
