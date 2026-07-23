<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /nail_salon/auth/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /nail_salon/customer/dashboard.php');
        exit;
    }
}

function requireCustomer() {
    requireLogin();
    if (!isCustomer()) {
        header('Location: /nail_salon/admin/dashboard.php');
        exit;
    }
}
