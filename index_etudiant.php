<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in and is student
if (!isset($_SESSION['id_etudiant']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: login.php");
    exit;
}

include 'database.php';

$etudiant_id = $_SESSION['id_etudiant'];
$error = "";
$success = "";

// Debugging function for prepare errors
function checkPrepare($conn, $stmt) {
    if (!$stmt) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
}

// Fetch student name
$stmt = $conn->prepare("SELECT nom, prenom FROM etudiants WHERE id = ?");
checkPrepare($conn, $stmt);
$stmt->bind_param("i", $etudiant_id);
$stmt->execute();
$stmt->bind_result($etudiantNom, $etudiantPrenom);
$stmt->fetch();
$stmt->close();

// Handle adding module
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['form_type']) && $_POST['form_type'] == "add_module") {
    $nom_module = trim($_POST['nom_module']);

    if (!empty($nom_module)) {
            // Check if student already has this module
            $check_stmt = $conn->prepare("
                SELECT em.id_module 
                FROM etudiant_module em 
                JOIN modules m ON em.id_module = m.id 
                WHERE em.id_etudiant = ? AND LOWER(m.nom_module) = LOWER(?)
            ");
        checkPrepare($conn, $check_stmt);
            $check_stmt->bind_param("is", $etudiant_id, $nom_module);
        $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $error = "Vous avez déjà ajouté ce module.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
                
                // Check if module exists
                $module_check = $conn->prepare("SELECT id FROM modules WHERE LOWER(nom_module) = LOWER(?)");
                checkPrepare($conn, $module_check);
                $module_check->bind_param("s", $nom_module);
                $module_check->execute();
                $module_check->store_result();
                
                if ($module_check->num_rows === 0) {
                    // Create new module
            $insert_stmt = $conn->prepare("INSERT INTO modules (nom_module) VALUES (?)");
            checkPrepare($conn, $insert_stmt);
            $insert_stmt->bind_param("s", $nom_module);
                    $insert_stmt->execute();
                $module_id = $insert_stmt->insert_id;
                    $insert_stmt->close();
            } else {
                    $module_check->bind_result($module_id);
                    $module_check->fetch();
            }
                $module_check->close();

        // Link student to module
            $link_stmt = $conn->prepare("INSERT IGNORE INTO etudiant_module (id_etudiant, id_module) VALUES (?, ?)");
            checkPrepare($conn, $link_stmt);
            $link_stmt->bind_param("ii", $etudiant_id, $module_id);
            if ($link_stmt->execute()) {
                    // Create empty note for this module
                    $note_stmt = $conn->prepare("INSERT IGNORE INTO notes (id_etudiant, id_module, contenu) VALUES (?, ?, '')");
                    $note_stmt->bind_param("ii", $etudiant_id, $module_id);
                    $note_stmt->execute();
                    $note_stmt->close();
                    
                $success = "Module ajouté avec succès.";
            } else {
                    $error = "Erreur lors de l'ajout du module.";
                }
                $link_stmt->close();
            }
        }
    } elseif (isset($_POST['form_type']) && $_POST['form_type'] == "save_notes") {
        $module_id = $_POST['module_id'];
        $notes = $_POST['notes'];

        // Save or update notes - using REPLACE INTO to ensure one note per module
        $notes_stmt = $conn->prepare("REPLACE INTO notes (id_etudiant, id_module, contenu) VALUES (?, ?, ?)");
        $notes_stmt->bind_param("iis", $etudiant_id, $module_id, $notes);
        if ($notes_stmt->execute()) {
            $success = "Notes sauvegardées avec succès.";
    } else {
            $error = "Erreur lors de la sauvegarde des notes.";
        }
        $notes_stmt->close();
    }
}

// Fetch student's modules and notes
$modules = [];
$stmt = $conn->prepare("
    SELECT DISTINCT m.id, m.nom_module, n.contenu
    FROM modules m
    INNER JOIN etudiant_module em ON m.id = em.id_module
    LEFT JOIN notes n ON m.id = n.id_module AND n.id_etudiant = ?
    WHERE em.id_etudiant = ?
    ORDER BY m.nom_module ASC
");
checkPrepare($conn, $stmt);
$stmt->bind_param("ii", $etudiant_id, $etudiant_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $modules[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Étudiant</title>
  <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
        .module-title { font-family: 'Playfair Display', serif; }
        body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-[#f5f1ee] min-h-screen">
    <header class="w-full py-6 px-8 flex justify-between items-center">
        <h1 class="module-title text-4xl">Bonjour, <?= htmlspecialchars($etudiantNom . " " . $etudiantPrenom) ?></h1>
        <div class="flex items-center gap-6">
            <div class="text-right"><?= date('d/m/Y') ?></div>
            <a href="logout.php" class="bg-black text-white px-4 py-2 rounded-full hover:bg-gray-800 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Déconnexion
            </a>
        </div>
    </header>

  <?php if ($error): ?>
        <div class="mx-8 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
        <div class="mx-8 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="p-8">
        <!-- Add Module Form -->
        <form method="POST" class="mb-12 bg-white rounded-xl p-6 shadow-lg max-w-2xl mx-auto">
            <h2 class="module-title text-2xl mb-6">Ajouter un module</h2>
            <div class="flex gap-4">
                <input type="hidden" name="form_type" value="add_module">
                <input type="text" name="nom_module" placeholder="Nom du module" required
                    class="flex-1 px-4 py-2 rounded-full bg-[#f5f1ee] border-0 focus:ring-2 focus:ring-black">
                <button type="submit" class="bg-black text-white rounded-full w-12 h-12 flex items-center justify-center hover:bg-gray-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </div>
        </form>

        <!-- Modules List -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <?php foreach ($modules as $module): ?>
            <div class="bg-white rounded-xl p-6 shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="module-title text-2xl"><?= htmlspecialchars($module['nom_module']) ?></h3>
                    <button type="button" onclick="deleteModule(<?= $module['id'] ?>, '<?= htmlspecialchars($module['nom_module']) ?>')" 
                        class="bg-red-600 text-white px-3 py-1 rounded-full hover:bg-red-700 flex items-center gap-2 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Supprimer le module
                    </button>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="form_type" value="save_notes">
                    <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                    <textarea name="notes" rows="6" placeholder="Veuillez enter votre notes"
                        class="w-full p-4 rounded-lg bg-[#f5f1ee] border-0 focus:ring-2 focus:ring-black"
                    ><?= htmlspecialchars($module['contenu'] ?? '') ?></textarea>
                    <div class="flex justify-between">
                        <div class="flex gap-2">
                            <button type="submit" class="bg-black text-white px-6 py-2 rounded-full hover:bg-gray-800 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                </svg>
                                Sauvegarder
                            </button>
                            <button type="button" onclick="deleteNote(<?= $module['id'] ?>)" class="bg-red-600 text-white px-4 py-2 rounded-full hover:bg-red-700 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Supprimer
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="getAISummary(<?= $module['id'] ?>)" 
                                class="bg-black text-white w-10 h-10 rounded-full hover:bg-gray-800 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </button>
                            <button type="button" onclick="getAIQuiz(<?= $module['id'] ?>)"
                                class="bg-black text-white w-10 h-10 rounded-full hover:bg-gray-800 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
      </button>
                        </div>
                    </div>
    </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    function getAISummary(moduleId) {
        const notesContent = document.querySelector(`input[name="module_id"][value="${moduleId}"]`)
            .closest('form')
            .querySelector('textarea[name="notes"]')
            .value;

        if (!notesContent.trim()) {
            alert('Veuillez d\'abord ajouter des notes avant de générer un résumé.');
            return;
        }

        // Show loading state
        const loadingModal = document.createElement('div');
        loadingModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
        loadingModal.innerHTML = `
            <div class="bg-white rounded-xl p-6 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-black mx-auto mb-4"></div>
                <p>Génération du résumé en cours...</p>
            </div>
        `;
        document.body.appendChild(loadingModal);

        fetch('summarize.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `input_text=${encodeURIComponent(notesContent)}`
        })
        .then(response => response.json())
        .then(data => {
            // Remove loading modal
            loadingModal.remove();

            if (data.error) {
                // Show error in a modal
                const errorModal = document.createElement('div');
                errorModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
                errorModal.innerHTML = `
                    <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-red-600">Erreur</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="text-red-600">
                            ${data.error}
                        </div>
                    </div>
                `;
                document.body.appendChild(errorModal);
                return;
            }

            // Show summary in a modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-xl p-6 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold">Résumé</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="prose max-w-none">
                        ${data.summary.split('\n').join('<br>')}
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        })
        .catch(error => {
            // Remove loading modal
            loadingModal.remove();
            
            // Show error in a modal
            const errorModal = document.createElement('div');
            errorModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
            errorModal.innerHTML = `
                <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-red-600">Erreur</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="text-red-600">
                        Une erreur est survenue lors de la génération du résumé. Veuillez réessayer plus tard.
                    </div>
                </div>
            `;
            document.body.appendChild(errorModal);
        });
    }

    function getAIQuiz(moduleId) {
        const notesContent = document.querySelector(`input[name="module_id"][value="${moduleId}"]`)
            .closest('form')
            .querySelector('textarea[name="notes"]')
            .value;

        if (!notesContent.trim()) {
            alert('Veuillez d\'abord ajouter des notes avant de générer un quiz.');
            return;
        }

        // Show loading state
        const loadingModal = document.createElement('div');
        loadingModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
        loadingModal.innerHTML = `
            <div class="bg-white rounded-xl p-6 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-black mx-auto mb-4"></div>
                <p>Génération du quiz en cours...</p>
            </div>
        `;
        document.body.appendChild(loadingModal);

        fetch('generate_quiz.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `notes=${encodeURIComponent(notesContent)}`
        })
        .then(response => response.json())
        .then(data => {
            // Remove loading modal
            loadingModal.remove();

            if (data.error) {
                // Show error in a modal
                const errorModal = document.createElement('div');
                errorModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
                errorModal.innerHTML = `
                    <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-red-600">Erreur</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="text-red-600">
                            ${data.error}
                        </div>
                    </div>
                `;
                document.body.appendChild(errorModal);
                return;
            }

            // Create a modal to display the quiz
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-xl p-6 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold">Quiz</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="prose max-w-none">
                        ${data.quiz.split('\n').join('<br>')}
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        })
        .catch(error => {
            // Remove loading modal
            loadingModal.remove();
            
            // Show error in a modal
            const errorModal = document.createElement('div');
            errorModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
            errorModal.innerHTML = `
                <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-red-600">Erreur</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="text-red-600">
                        Une erreur est survenue lors de la génération du quiz. Veuillez réessayer plus tard.
                    </div>
                </div>
            `;
            document.body.appendChild(errorModal);
        });
    }

    function deleteNote(moduleId) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette note ?')) {
            return;
        }

        // Show loading state
        const loadingModal = document.createElement('div');
        loadingModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
        loadingModal.innerHTML = `
            <div class="bg-white rounded-xl p-6 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-black mx-auto mb-4"></div>
                <p>Suppression de la note en cours...</p>
            </div>
        `;
        document.body.appendChild(loadingModal);

        fetch('delete_note.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `module_id=${moduleId}`,
            cache: 'no-store'
        })
        .then(response => {
            // Ensure we're getting fresh data
            const fresh = response.clone();
            return fresh.json();
        })
        .then(data => {
            console.log('Delete response:', data); // Debug log
            loadingModal.remove();
            
            if (data.error) {
                // Show error in a modal
                const errorModal = document.createElement('div');
                errorModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
                errorModal.innerHTML = `
                    <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-red-600">Erreur</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="text-red-600">
                            ${data.error}
                        </div>
                    </div>
                `;
                document.body.appendChild(errorModal);
            } else {
                // Show success message
                const successModal = document.createElement('div');
                successModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
                successModal.innerHTML = `
                    <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-green-600">Succès</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="text-green-600">
                            ${data.message}
                        </div>
                    </div>
                `;
                document.body.appendChild(successModal);
                
                // Force a hard refresh after a short delay
                setTimeout(() => {
                    window.location.href = window.location.href + '?t=' + new Date().getTime();
                }, 1500);
            }
        })
        .catch(error => {
            console.error('Delete error:', error); // Debug log
            loadingModal.remove();
            alert('Erreur lors de la suppression de la note: ' + error);
        });
    }

    function deleteModule(moduleId, moduleName) {
        if (!confirm(`Êtes-vous sûr de vouloir supprimer le module "${moduleName}" ? Cette action supprimera également toutes les notes associées.`)) {
            return;
        }

        // Show loading state
        const loadingModal = document.createElement('div');
        loadingModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
        loadingModal.innerHTML = `
            <div class="bg-white rounded-xl p-6 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-black mx-auto mb-4"></div>
                <p>Suppression du module en cours...</p>
            </div>
        `;
        document.body.appendChild(loadingModal);

        fetch('delete_module.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `module_id=${moduleId}`
        })
        .then(response => response.json())
        .then(data => {
            loadingModal.remove();
            
            if (data.error) {
                // Show error in a modal
                const errorModal = document.createElement('div');
                errorModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
                errorModal.innerHTML = `
                    <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-red-600">Erreur</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="text-red-600">
                            ${data.error}
                        </div>
                    </div>
                `;
                document.body.appendChild(errorModal);
            } else {
                // Show success message
                const successModal = document.createElement('div');
                successModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
                successModal.innerHTML = `
                    <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-green-600">Succès</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="text-green-600">
                            Module supprimé avec succès.
                        </div>
                    </div>
                `;
                document.body.appendChild(successModal);
                
                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        })
        .catch(error => {
            loadingModal.remove();
            alert('Erreur lors de la suppression du module: ' + error);
        });
    }
    </script>
</body>
</html>
