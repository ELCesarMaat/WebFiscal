<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}
// simple CSRF token helper
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
function check_csrf($token) {
  return isset($token) && hash_equals($_SESSION['csrf_token'], $token);
}