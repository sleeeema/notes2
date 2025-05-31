<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'database.php';

$error = "";

// Auto-login from cookie if no session
if (!isset($_SESSION['user']) && isset($_COOKIE['user']) && isset($_COOKIE['role'])) {
    $_SESSION['user'] = $_COOKIE['user'];
    $_SESSION['role'] = $_COOKIE['role'];
    
    // Load user data based on role
    if ($_COOKIE['role'] === 'admin') {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->bind_param("s", $_COOKIE['user']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $_SESSION['id'] = $row['id'];
            header("Location: index_admin.php");
            exit;
        }
        $stmt->close();
    } elseif ($_COOKIE['role'] === 'etudiant') {
        $stmt = $conn->prepare("SELECT id FROM etudiants WHERE email = ?");
        $stmt->bind_param("s", $_COOKIE['user']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $_SESSION['id_etudiant'] = $row['id'];
            header("Location: index_etudiant.php");
            exit;
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Check admin first
    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($admin = $result->fetch_assoc()) {
        if ($password === $admin['mot_de_passe']) {
            session_regenerate_id(true);
            $_SESSION['user'] = $admin['email'];
            $_SESSION['role'] = 'admin';
            $_SESSION['id'] = $admin['id'];

            if ($remember) {
                setcookie('user', $email, time() + (86400 * 30), "/");
                setcookie('role', 'admin', time() + (86400 * 30), "/");
            }

            header("Location: index_admin.php");
            exit;
        } else {
            $error = "Mot de passe incorrect.";
        }
    } else {
        // Check student table
        $stmt = $conn->prepare("SELECT * FROM etudiants WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($etudiant = $result->fetch_assoc()) {
            if ($password === $etudiant['mot_de_passe']) {
                session_regenerate_id(true);
                $_SESSION['user'] = $etudiant['email'];
                $_SESSION['role'] = 'etudiant';
                $_SESSION['id_etudiant'] = $etudiant['id'];

                if ($remember) {
                    setcookie('user', $email, time() + (86400 * 30), "/");
                    setcookie('role', 'etudiant', time() + (86400 * 30), "/");
                }

                header("Location: index_etudiant.php");
                exit;
            } else {
                $error = "Mot de passe incorrect.";
            }
        } else {
            $error = "Aucun compte trouvé avec cet e-mail.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Login Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        .title-font { font-family: 'Playfair Display', serif; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex">
<div class="w-1/2 hidden md:block">
    <img alt="External Image" src="https://i.pinimg.com/736x/0c/da/5d/0cda5dfd800186df464653691b5d75a9.jpg" class="w-full h-full object-cover" height="800" width="600"/>
</div>
<div class="w-full md:w-1/2 bg-[#e7dfd8] flex flex-col justify-center items-center px-8 md:px-20">
    <h1 class="title-font text-6xl mb-20 font-bold leading-tight text-center">Bienvenu</h1>

    <?php if ($error): ?>
        <div class="bg-red-500 text-white p-3 rounded-lg mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form class="w-full max-w-lg space-y-8" method="POST" action="">
        <div class="bg-white rounded-full flex items-center px-6 py-4">
            <i class="fas fa-user text-black text-lg mr-4"></i>
            <input class="w-full bg-transparent outline-none text-black text-sm font-normal" placeholder="Votre e-mail" type="email" name="email" required/>
        </div>

        <div class="bg-white rounded-full flex items-center px-6 py-4">
            <i class="fas fa-lock text-black text-lg mr-4"></i>
            <input class="w-full bg-transparent outline-none text-black text-sm font-normal" placeholder="Votre mot de passe" type="password" name="password" required/>
        </div>

        <div class="flex items-center">
            <input type="checkbox" name="remember" id="remember" class="mr-2"/>
            <label for="remember" class="text-sm">Se souvenir de moi</label>
        </div>

        <button class="w-full bg-black text-white text-2xl font-bold rounded-full py-5 mt-10" type="submit">
            Connexion
        </button>
    </form>
</div>
</body>
</html>
