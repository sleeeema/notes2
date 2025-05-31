<?php
// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'database.php';

$admin_id = $_SESSION['id'];

// Get admin name
$stmt = $conn->prepare("SELECT nom FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($adminNom);
$stmt->fetch();
$stmt->close();

$success = "";
$error = "";

// Handle Add Student form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['form_type'] === 'add_student') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($nom && $prenom && $email && $password) {
        $check_stmt = $conn->prepare("SELECT id FROM etudiants WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO etudiants (nom, prenom, email, mot_de_passe, id_admin) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $nom, $prenom, $email, $password, $admin_id);
            if ($stmt->execute()) {
                $success = "Étudiant ajouté avec succès.";
            } else {
                $error = "Erreur: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Email déjà utilisé.";
        }
        $check_stmt->close();
    } else {
        $error = "Tous les champs sont requis.";
    }
}

// Handle Delete Student
if (isset($_GET['delete_id'])) {
    $stmt = $conn->prepare("DELETE FROM etudiants WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete_id']);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get modules (not directly used here but good to have)
$modules_result = $conn->query("SELECT id, nom_module FROM modules");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Modules & Étudiants</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        .module-title { font-family: 'Playfair Display', serif; }
        body { font-family: 'Inter', sans-serif; }
        .smooth-shape {
            border-radius: 30px;
            position: relative;
            overflow: hidden;
        }
        .smooth-shape::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(
                circle at center,
                rgba(255,255,255,0.1) 0%,
                rgba(255,255,255,0) 70%
            );
            transform: rotate(15deg);
        }
        .bg-custom {
            background: linear-gradient(135deg, #f5f1ee 0%, #e7dfd8 100%);
        }
    </style>
</head>
<body class="bg-custom min-h-screen p-8">

<main class="max-w-7xl mx-auto">
    <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 smooth-shape"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 smooth-shape"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <header class="flex justify-between items-center mb-12 bg-white smooth-shape p-6">
        <h1 class="module-title text-4xl">Bonjour, <?= htmlspecialchars($adminNom); ?></h1>
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

    <section class="bg-white rounded-xl p-6 shadow-lg mb-8">
        <h2 class="module-title text-2xl mb-6">Liste des étudiants</h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prénom</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modules</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    $students_result = $conn->query("SELECT * FROM etudiants WHERE id_admin = $admin_id");
                    while ($student = $students_result->fetch_assoc()):
                        // Get modules for this student
                        $student_id = $student['id'];
                        $modules_for_student = [];
                        $modules_stmt = $conn->prepare("
        SELECT m.nom_module 
        FROM modules m
        INNER JOIN etudiant_module em ON m.id = em.id_module
        WHERE em.id_etudiant = ?
    ");

                        $modules_stmt->bind_param("i", $student_id);
                        $modules_stmt->execute();
                        $modules_result = $modules_stmt->get_result();
                        while ($mod = $modules_result->fetch_assoc()) {
                            $modules_for_student[] = $mod['nom_module'];
                        }
                        $modules_stmt->close();
                    ?>
                    <tr data-id="<?= $student['id'] ?>" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="static"><?= htmlspecialchars($student['nom']) ?></span>
                            <input type="text" class="edit-field hidden px-3 py-1 border rounded-lg" name="nom" value="<?= htmlspecialchars($student['nom']) ?>">
                        </td>
                        <td class="px-6 py-4">
                            <span class="static"><?= htmlspecialchars($student['prenom']) ?></span>
                            <input type="text" class="edit-field hidden px-3 py-1 border rounded-lg" name="prenom" value="<?= htmlspecialchars($student['prenom']) ?>">
                        </td>
                        <td class="px-6 py-4">
                            <span class="static"><?= htmlspecialchars($student['email']) ?></span>
                            <input type="email" class="edit-field hidden px-3 py-1 border rounded-lg" name="email" value="<?= htmlspecialchars($student['email']) ?>">
                        </td>
                        <td class="px-6 py-4">
                            <?= htmlspecialchars(implode(", ", $modules_for_student)) ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex gap-2">
                                <button class="edit-btn bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button class="save-btn hidden bg-green-500 text-white p-2 rounded-full hover:bg-green-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                                <a href="?delete_id=<?= $student['id'] ?>" onclick="return confirm('Supprimer cet étudiant ?');" 
                                   class="bg-red-500 text-white p-2 rounded-full hover:bg-red-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bg-white rounded-xl p-6 shadow-lg">
        <h2 class="module-title text-2xl mb-6">Ajouter Un Étudiant</h2>
        <form method="post" class="space-y-4">
            <input type="hidden" name="form_type" value="add_student">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="text" name="nom" placeholder="Nom" required
                    class="px-4 py-2 rounded-full bg-[#f5f1ee] border-0 focus:ring-2 focus:ring-black">
                <input type="text" name="prenom" placeholder="Prénom" required
                    class="px-4 py-2 rounded-full bg-[#f5f1ee] border-0 focus:ring-2 focus:ring-black">
                <input type="email" name="email" placeholder="Email" required
                    class="px-4 py-2 rounded-full bg-[#f5f1ee] border-0 focus:ring-2 focus:ring-black">
                <input type="password" name="password" placeholder="Mot de passe" required
                    class="px-4 py-2 rounded-full bg-[#f5f1ee] border-0 focus:ring-2 focus:ring-black">
            </div>
            <button type="submit" class="w-full bg-black text-white px-6 py-2 rounded-full hover:bg-gray-800 flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter l'étudiant
            </button>
        </form>
    </section>
</main>

<footer>
    <button onclick="window.location.href='login.php';">⎋</button>
</footer>

<script>
// Handle Edit button click
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const row = btn.closest('tr');
        row.querySelectorAll('.static').forEach(el => el.classList.add('hidden'));
        row.querySelectorAll('.edit-field').forEach(el => el.classList.remove('hidden'));
        btn.classList.add('hidden');
        row.querySelector('.save-btn').classList.remove('hidden');
    });
});

// Handle Save button click
document.querySelectorAll('.save-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const row = btn.closest('tr');
        const id = row.dataset.id;
        const nom = row.querySelector('input[name="nom"]').value;
        const prenom = row.querySelector('input[name="prenom"]').value;
        const email = row.querySelector('input[name="email"]').value;

        fetch('update_student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&nom=${encodeURIComponent(nom)}&prenom=${encodeURIComponent(prenom)}&email=${encodeURIComponent(email)}`
        })
        .then(res => res.text())
        .then(res => {
            if (res.trim() === 'success') {
                row.querySelectorAll('.static')[0].textContent = nom;
                row.querySelectorAll('.static')[1].textContent = prenom;
                row.querySelectorAll('.static')[2].textContent = email;
                row.querySelectorAll('.static').forEach(el => el.classList.remove('hidden'));
                row.querySelectorAll('.edit-field').forEach(el => el.classList.add('hidden'));
                row.querySelector('.edit-btn').classList.remove('hidden');
                btn.classList.add('hidden');
            } else {
                alert('Erreur : ' + res);
            }
        });
    });
});
</script>

</body>
</html>
