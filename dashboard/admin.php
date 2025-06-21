<?php
declare(strict_types=1);

// Activation du débogage
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/../logs/php_errors.log');

// Chemins absolus
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__));
}
require_once BASE_DIR.'/includes/config.php';

// Démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification stricte de la connexion PDO
if (!($conn instanceof PDO)) {
    error_log("Connexion PDO invalide dans admin.php");
    header('Location: /erreur.php?code=500');
    exit();
}

// Vérification du rôle administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Récupération des statistiques admin
try {
    $stats = [];
    $queries = [
        'concours_actifs' => ["SELECT COUNT(*) FROM concours WHERE date_fin >= CURDATE()", []],
        'utilisateurs' => ["SELECT COUNT(*) FROM utilisateurs", []],
        'admins' => ["SELECT COUNT(*) FROM utilisateurs WHERE role = ?", ['admin']],
        'correcteurs' => ["SELECT COUNT(*) FROM utilisateurs WHERE role = ?", ['correcteur']],
        'candidats' => ["SELECT COUNT(*) FROM utilisateurs WHERE role = ?", ['candidat']],
        'corrections_en_attente' => ["SELECT COUNT(*) FROM copies WHERE statut = ?", ['correction_soumise']]
    ];

    foreach ($queries as $key => $query) {
        $stmt = $conn->prepare($query[0]);
        $stmt->execute($query[1]);
        $stats[$key] = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Erreur SQL: " . $e->getMessage());
    header('Location: /erreur.php?code=500');
    exit();
}

// Ajouter en haut du fichier
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin - Concours Anonyme</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1 class="dashboard-title">
                        Tableau de bord
                        <?php if ($_SESSION['user_id'] == 1): ?>
                            <span class="admin-badge super-admin">👑</span>
                        <?php else: ?>
                            <span class="admin-badge admin">⭐</span>
                        <?php endif; ?>
                    </h1>

                    <!-- Statistiques -->
                    <section class="dashboard-section">
                        <h2>Statistiques</h2>
                        <div class="stats-grid">                           
                            <div class="stat-card">
                                <div class="stat-number"><?= $stats['utilisateurs'] ?></div>
                                <div class="stat-label">Utilisateurs</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?= $stats['admins'] ?></div>
                                <div class="stat-label">Administrateurs</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?= $stats['correcteurs'] ?></div>
                                <div class="stat-label">Correcteurs</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?= $stats['candidats'] ?></div>
                                <div class="stat-label">Candidats</div>
                            </div>
                             <div class="stat-card">
                                <div class="stat-number"><?= $stats['concours_actifs'] ?></div>
                                <div class="stat-label">Concours Actifs</div>
                            </div>
                            <div class="stat-card <?= $stats['corrections_en_attente'] > 0 ? 'stat-alert' : '' ?>">
                                <div class="stat-number"><?= $stats['corrections_en_attente'] ?></div>
                                <div class="stat-label">Corrections en attente</div>
                                <?php if ($stats['corrections_en_attente'] > 0): ?>
                                <a href="<?php echo APP_URL; ?>/admin/valider_corrections.php" class="stat-action">Valider →</a>
                                <?php endif; ?>
                            </div>                            
                        </div>
                    </section>

                    <!-- Actions rapides -->
                    <section class="dashboard-section">
                        <h2>Actions rapides</h2>
                        <div class="action-grid">
                            <a href="<?php echo APP_URL; ?>/concours/creer.php" class="action-card">
                                <div class="action-icon">📝</div>
                                <div class="action-text">Créer un concours</div>
                            </a>
                            <a href="<?php echo APP_URL; ?>/concours/liste.php" class="action-card">
                                <div class="action-icon">📋</div>
                                <div class="action-text">Gérer les concours</div>
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/gestion_concours.php" class="action-card">
                                <div class="action-icon">⏰</div>
                                <div class="action-text">Phases de soumission</div>
                            </a>
                            <a href="<?php echo APP_URL; ?>/utilisateurs/gerer.php" class="action-card">
                                <div class="action-icon">👥</div>
                                <div class="action-text">Gérer les utilisateurs</div>
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/attribuer_copies.php" class="action-card">
                                <div class="action-icon">📄</div>
                                <div class="action-text">Gérer les copies</div>
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/valider_corrections.php" class="action-card <?= $stats['corrections_en_attente'] > 0 ? 'action-alert' : '' ?>">
                                <div class="action-icon">✅</div>
                                <div class="action-text">Valider les corrections</div>
                                <?php if ($stats['corrections_en_attente'] > 0): ?>
                                <div class="action-badge"><?= $stats['corrections_en_attente'] ?></div>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/statistiques_globales.php" class="action-card">
                                <div class="action-icon">📈</div>
                                <div class="action-text">Statistiques</div>
                            </a>
                            <a href="<?php echo APP_URL; ?>/exports/resultats.php" class="action-card">
                                <div class="action-icon">📊</div>
                                <div class="action-text">Exporter les résultats</div>
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <script>
        // Vérification que le JS est chargé
        console.log('Admin dashboard loaded');
        
        document.addEventListener('DOMContentLoaded', function() {
            // Vérification des éléments spécifiques à admin
            if (!document.querySelector('.stats-grid')) {
                console.error('Élément stats-grid introuvable');
            }
        });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
