<?php
/**
 * Configuration de la base de données
 * Ce fichier établit la connexion avec la base de données MySQL de XAMPP
 * en utilisant PDO pour une meilleure sécurité et gestion des erreurs
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');  // Port MySQL de XAMPP
define('DB_NAME', 'concours_anonyme');
define('DB_USER', 'root');
define('DB_PASS', '');  // Mot de passe vide par défaut dans XAMPP

// Configuration de l'application
define('APP_NAME', 'Système de Concours Anonyme');
define('APP_URL', 'http://localhost/concours_anonyme');

// Configuration de la sécurité (NE PAS MODIFIER APRÈS LA MISE EN PRODUCTION)
define('ENCRYPTION_KEY', 'def0000068f27f7d8c63a4e313519b9c7a11330191e017d719947226e5b3703127b37341631a05b401532b1901519b9c7a11330191e017d719947226e5b37031');
define('ENCRYPTION_IV', 'def000008b3919d75a71293617365e6a');

// Mode debug (à désactiver en production)
define('DEBUG', true);

// Inclusion du gestionnaire d'erreurs
require_once __DIR__ . '/error_handler.php';

// Configuration des sessions sécurisées
if (session_status() === PHP_SESSION_NONE) {
    // Configuration sécurisée des sessions
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');

    session_start();

    // Régénération de l'ID de session pour éviter la fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }

    // Timeout de session (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

// Connexion à la base de données avec PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // En cas d'erreur, affichage du message et arrêt du script
    die("Erreur de connexion : " . $e->getMessage());
}
?>