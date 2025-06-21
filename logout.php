<?php
// Inclure le fichier de configuration pour accéder à APP_URL
require_once __DIR__ . '/includes/config.php';

// Démarrer la session
session_start();

// Journalisation avant déconnexion (pour debug en environnement dev)
if (isset($_SESSION['user_id'])) {
    error_log("User ".$_SESSION['user_id']." logging out at ".date('Y-m-d H:i:s'));
}

// Nettoyage complet de la session
$_SESSION = [];

// Détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Redirection avec message de confirmation
$_SESSION['logout_message'] = "Vous avez été déconnecté avec succès.";
header('Location: ' . APP_URL . '/index.php');
exit;
?>