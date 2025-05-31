<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Set JSON content type header
header('Content-Type: application/json');

// Check if user is logged in and is student
if (!isset($_SESSION['id_etudiant']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

require_once 'database.php';

// Verify database connection
if ($conn->connect_error) {
    echo json_encode(['error' => 'Erreur de connexion à la base de données: ' . $conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etudiant_id = $_SESSION['id_etudiant'];
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;

    if ($module_id > 0) {
        // Instead of deleting, just clear the content
        $stmt = $conn->prepare("UPDATE notes SET contenu = '' WHERE id_etudiant = ? AND id_module = ?");
        if (!$stmt) {
            echo json_encode(['error' => 'Erreur de préparation de la requête: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param("ii", $etudiant_id, $module_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Notes effacées avec succès']);
            } else {
                // If no row was affected, maybe we need to create an empty note
                $insert_stmt = $conn->prepare("INSERT IGNORE INTO notes (id_etudiant, id_module, contenu) VALUES (?, ?, '')");
                $insert_stmt->bind_param("ii", $etudiant_id, $module_id);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                echo json_encode(['success' => true, 'message' => 'Notes effacées avec succès']);
            }
        } else {
            echo json_encode(['error' => 'Erreur lors de l\'effacement: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['error' => 'ID du module invalide']);
    }
} else {
    echo json_encode(['error' => 'Méthode non autorisée']);
} 