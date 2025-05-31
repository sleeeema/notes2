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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etudiant_id = $_SESSION['id_etudiant'];
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;

    if ($module_id > 0) {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Delete notes first
            $notes_stmt = $conn->prepare("DELETE FROM notes WHERE id_etudiant = ? AND id_module = ?");
            if (!$notes_stmt) {
                throw new Exception('Erreur de préparation de la suppression des notes: ' . $conn->error);
            }
            $notes_stmt->bind_param("ii", $etudiant_id, $module_id);
            $notes_stmt->execute();
            $notes_stmt->close();

            // Then remove the module from student's list
            $module_stmt = $conn->prepare("DELETE FROM etudiant_module WHERE id_etudiant = ? AND id_module = ?");
            if (!$module_stmt) {
                throw new Exception('Erreur de préparation de la suppression du module: ' . $conn->error);
            }
            $module_stmt->bind_param("ii", $etudiant_id, $module_id);
            $module_stmt->execute();
            
            if ($module_stmt->affected_rows === 0) {
                throw new Exception('Module non trouvé ou déjà supprimé');
            }
            
            $module_stmt->close();

            // Commit transaction
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => 'Module supprimé avec succès']);
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'ID du module invalide']);
    }
} else {
    echo json_encode(['error' => 'Méthode non autorisée']);
} 