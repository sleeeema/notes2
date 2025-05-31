<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';

// Clean up duplicate module assignments
$conn->query("
    DELETE em1 FROM etudiant_module em1 
    INNER JOIN etudiant_module em2 
    WHERE em1.id_etudiant = em2.id_etudiant 
    AND em1.id_module = em2.id_module 
    AND em1.id > em2.id
");

// Clean up duplicate notes
$conn->query("
    DELETE n1 FROM notes n1 
    INNER JOIN notes n2 
    WHERE n1.id_etudiant = n2.id_etudiant 
    AND n1.id_module = n2.id_module 
    AND n1.id > n2.id
");

echo "Nettoyage terminé. Vous pouvez retourner à la page principale.";
?> 