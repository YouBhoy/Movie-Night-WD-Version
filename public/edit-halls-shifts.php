<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
requireAdminLogin();
header('Location: manage-halls.php');
exit;
