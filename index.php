<?php
/**
 * YBT Management Software — Entry Router
 * Redirects authenticated users to dashboard, others to login
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . url('dashboard.php'));
    exit;
} else {
    header('Location: ' . url('login.php'));
    exit;
}
