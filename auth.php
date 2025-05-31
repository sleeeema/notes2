<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user']) && isset($_SESSION['role']);
}

// Function to check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Function to require login for a page
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Function to require specific role for a page
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header("Location: login.php");
        exit;
    }
}

// Auto login from cookie if session not set
if (!isLoggedIn() && isset($_COOKIE['user']) && isset($_COOKIE['role'])) {
    $_SESSION['user'] = $_COOKIE['user'];
    $_SESSION['role'] = $_COOKIE['role'];
    
    // Load additional user data based on role
    include_once 'database.php';
    
    if ($_COOKIE['role'] === 'admin') {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->bind_param("s", $_COOKIE['user']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $_SESSION['id'] = $row['id'];
        }
        $stmt->close();
    } elseif ($_COOKIE['role'] === 'etudiant') {
        $stmt = $conn->prepare("SELECT id FROM etudiants WHERE email = ?");
        $stmt->bind_param("s", $_COOKIE['user']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $_SESSION['id_etudiant'] = $row['id'];
        }
        $stmt->close();
    }
} 