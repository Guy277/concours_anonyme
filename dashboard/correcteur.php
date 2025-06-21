<?php
/**
 * Dashboard correcteur
 * Interface pour les correcteurs avec leurs copies à corriger
 */

// Désactiver l'affichage des erreurs pour éviter les messages de debug
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../includes/config.php';
require_once '../includes/notifications.php';
require_once '../includes/note_calculator.php';

// Vérification de l'authentification et du rôle correcteur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'correcteur') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialiser le gestionnaire de notifications
$notificationManager = new NotificationManager($conn);

// Récupération des copies attribuées au correcteur (à corriger)
$sql = "SELECT c.*, co.titre as concours_titre, co.date_fin
        FROM attributions_copies ac
        INNER JOIN copies c ON ac.copie_id = c.id
        INNER JOIN concours co ON c.concours_id = co.id
        LEFT JOIN corrections cor ON c.id = cor.copie_id AND cor.correcteur_id = ac.correcteur_id
        WHERE ac.correcteur_id = ? AND cor.id IS NULL
        ORDER BY co.date_fin ASC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$copies_a_corriger = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des statistiques
$stats = [];

// Nombre total de copies attribuées
$sql = "SELECT COUNT(*) as total FROM attributions_copies WHERE correcteur_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$stats['total_attribuees'] = $stmt->fetchColumn();

// Nombre de copies corrigées
$sql = "SELECT COUNT(*) as total FROM corrections WHERE correcteur_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$stats['corrigees'] = $stmt->fetchColumn();

// Nombre de copies à corriger
$stats['a_corriger'] = $stats['total_attribuees'] - $stats['corrigees'];

// Note moyenne - Utiliser la classe unifiée
$sql = "SELECT evaluation_data_json FROM corrections WHERE correcteur_id = ? AND evaluation_data_json IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$corrections_data = $stmt->fetchAll(PDO::FETCH_COLUMN);

$notes_calculees = [];
foreach ($corrections_data as $json_data) {
    $data = json_decode($json_data, true);
    if ($data) {
        $note_calculee = NoteCalculator::calculerNoteFinale($data);
        if ($note_calculee > 0) {
            $notes_calculees[] = $note_calculee;
        }
    }
}

$stats['note_moyenne'] = !empty($notes_calculees) ? round(array_sum($notes_calculees) / count($notes_calculees), 2) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/concours_anonyme/">
    <title>Dashboard Correcteur - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>

                <div class="dashboard-content">
                    <h1>📝 Dashboard Correcteur</h1>

                    <!-- Notifications -->
                    <?php
                    $notifications = $notificationManager->getUnreadNotifications($user_id);
                    if (!empty($notifications)):
                    ?>
                    <section class="notifications-section">
                        <?php echo $notificationManager->renderNotifications($user_id); ?>
                    </section>
                    <?php endif; ?>

                    <!-- Statistiques -->
                    <section class="stats-section" style="margin-bottom: 25px;">
                        <h2 style="margin-bottom: 15px; font-size: 1.3em;">📊 Mes statistiques</h2>
                        <div class="stats-grid" style="gap: 15px;">
                            <div class="stat-card" style="padding: 15px; min-height: auto;">
                                <div class="stat-icon" style="font-size: 1.5em;">📋</div>
                                <div class="stat-content">
                                    <div class="stat-number" style="font-size: 1.8em; margin-bottom: 5px;"><?php echo $stats['total_attribuees']; ?></div>
                                    <div class="stat-label" style="font-size: 0.9em;">Total attribuées</div>
                                </div>
                            </div>

                            <div class="stat-card" style="padding: 15px; min-height: auto;">
                                <div class="stat-icon" style="font-size: 1.5em;">⏳</div>
                                <div class="stat-content">
                                    <div class="stat-number" style="font-size: 1.8em; margin-bottom: 5px;"><?php echo $stats['a_corriger']; ?></div>
                                    <div class="stat-label" style="font-size: 0.9em;">À corriger</div>
                                </div>
                            </div>

                            <div class="stat-card" style="padding: 15px; min-height: auto;">
                                <div class="stat-icon" style="font-size: 1.5em;">✅</div>
                                <div class="stat-content">
                                    <div class="stat-number" style="font-size: 1.8em; margin-bottom: 5px;"><?php echo $stats['corrigees']; ?></div>
                                    <div class="stat-label" style="font-size: 0.9em;">Corrigées</div>
                                </div>
                            </div>

                            <div class="stat-card" style="padding: 15px; min-height: auto;">
                                <div class="stat-icon" style="font-size: 1.5em;">📊</div>
                                <div class="stat-content">
                                    <div class="stat-number" style="font-size: 1.8em; margin-bottom: 5px;"><?php echo number_format($stats['note_moyenne'], 1); ?></div>
                                    <div class="stat-label" style="font-size: 0.9em;">Note moyenne</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Copies à corriger -->
                    <section class="copies-section" style="margin-bottom: 25px;">
                        <h2 style="margin-bottom: 15px; font-size: 1.3em;">⏳ Copies à corriger</h2>
                        <?php if (!empty($copies_a_corriger)): ?>
                            <div class="copies-grid">
                                <?php foreach ($copies_a_corriger as $copie): ?>
                                    <div class="copie-card">
                                        <div class="copie-header">
                                            <h3><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></h3>
                                            <span class="concours-badge"><?php echo htmlspecialchars($copie['concours_titre']); ?></span>
                                        </div>

                                        <div class="copie-info">
                                            <p><strong>Date de dépôt :</strong> <?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></p>
                                            <p><strong>Date limite concours :</strong> <?php echo date('d/m/Y', strtotime($copie['date_fin'])); ?></p>
                                        </div>

                                        <div class="copie-actions">
                                            <a href="corrections/voir_copie.php?id=<?php echo $copie['id']; ?>" class="btn-small">Voir la copie</a>
                                            <a href="corrections/evaluer_moderne.php?copie_id=<?php echo $copie['id']; ?>" class="btn-small">Corriger</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="section-footer">
                                <a href="corrections/mes_corrections.php" class="btn btn-primary">Voir toutes mes corrections</a>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>Aucune copie à corriger pour le moment.</p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Actions rapides -->
                    <section class="actions-section" style="margin-bottom: 25px;">
                        <h2 style="margin-bottom: 15px; font-size: 1.3em;">⚡ Actions rapides</h2>
                        <div class="actions-grid">
                            <a href="corrections/mes_corrections.php" class="action-card">
                                <div class="action-icon">📝</div>
                                <div class="action-title">Mes corrections</div>
                                <div class="action-desc">Voir toutes mes corrections</div>
                            </a>

                            <a href="corrections/liste.php" class="action-card">
                                <div class="action-icon">📋</div>
                                <div class="action-title">Liste des corrections</div>
                                <div class="action-desc">Historique complet</div>
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>