<?php
require_once __DIR__ . '/../config/bootstrap.php';

$_SESSION = [];
session_destroy();
redirect('/login.php');
