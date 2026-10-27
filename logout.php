<?php
/**
 * YBT Management Software — Logout
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::logout();
Flash::success('You have been logged out successfully.');
header('Location: ' . url('login.php'));
exit;
