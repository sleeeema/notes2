<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth.php';
require_once 'database.php';

// Require admin role for this page
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);

    if ($id && $nom && $prenom && $email) {
        // Optional: check if email is unique (except current student)
        $check_stmt = $conn->prepare("SELECT id FROM etudiants WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows === 0) {
            $stmt = $conn->prepare("UPDATE etudiants SET nom = ?, prenom = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nom, $prenom, $email, $id);
            if ($stmt->execute()) {
                echo "success";
            } else {
                echo "Erreur update: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Email déjà utilisé par un autre étudiant.";
        }
        $check_stmt->close();
    } else {
        echo "Tous les champs sont requis.";
    }
} else {
    echo "Méthode non autorisée";
}
