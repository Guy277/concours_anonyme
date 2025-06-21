<?php
/**
 * Dashboard candidat - Interface principale pour les candidats
 */

session_start();
require_once "../includes/config.php";
require_once "../includes/notifications.php";

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidat') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialiser le gestionnaire de notifications
$notificationManager = new NotificationManager($conn);

// Récupération des informations utilisateur depuis la base
$sql = "SELECT nom, prenom FROM utilisateurs WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);
$nom = $user_info['nom'] ?? 'Utilisateur';
$prenom = $user_info['prenom'] ?? '';

// Vérification de l'existence de la colonne statut dans la table concours
$check_column_sql = "SHOW COLUMNS FROM concours LIKE 'statut'";
$check_stmt = $conn->prepare($check_column_sql);
$check_stmt->execute();
$has_statut_column = $check_stmt->rowCount() > 0;

// Récupération des statistiques du candidat
$where_clause = $has_statut_column ? "WHERE c.statut = 'actif'" : "";
$sql = "SELECT
            COUNT(DISTINCT c.id) as concours_inscrits,
            COUNT(DISTINCT co.id) as copies_deposees,
            COUNT(DISTINCT cor.id) as resultats_disponibles,
            COUNT(DISTINCT CASE WHEN cor.evaluation_data_json IS NOT NULL THEN cor.id END) as copies_evaluees
        FROM concours c
        LEFT JOIN copies co ON c.id = co.concours_id AND co.candidat_id = ?
        LEFT JOIN corrections cor ON co.id = cor.copie_id
        $where_clause";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupération des concours disponibles
$where_clause_concours = $has_statut_column ? "WHERE c.statut = 'actif'" : "";
$sql = "SELECT c.*,
               CASE
                   WHEN NOW() < c.date_debut THEN 'pending'
                   WHEN NOW() <= c.date_fin THEN 'active'
                   ELSE 'finished'
               END as status,
               co.id as copie_id,
               co.date_depot,
               co.statut as copie_statut
        FROM concours c
        LEFT JOIN copies co ON c.id = co.concours_id AND co.candidat_id = ?
        $where_clause_concours
        ORDER BY c.date_debut ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$concours = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Dashboard Candidat";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo APP_URL; ?>/">
    <title><?php echo $page_title; ?> - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include "../includes/header.php"; ?>

    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include "../includes/dashboard_nav.php"; ?>
                
                <div class="dashboard-content">
                    <div class="dashboard-header">
                        <h1>📚 Mon Espace</h1>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($prenom . ' ' . $nom); ?></span>
                            <span class="user-role">📚 Candidat</span>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <?php
                    $notifications = $notificationManager->getUnreadNotifications($user_id);
                    if (!empty($notifications)):
                    ?>
                    <section class="notifications-section" style="margin-bottom: 30px;">
                        <?php echo $notificationManager->renderNotifications($user_id); ?>
                    </section>
                    <?php endif; ?>

                    <!-- Statistiques -->
                    <section class="stats-section" style="margin-bottom: 30px;">
                        <h2 style="color: #2c3e50; margin-bottom: 20px; font-size: 1.5em;">📊 Mes statistiques</h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number" style="color: #4a90e2;"><?php echo $stats['concours_inscrits']; ?></div>
                                <div class="stat-label">Concours disponibles</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" style="color: #4a90e2;"><?php echo $stats['copies_deposees']; ?></div>
                                <div class="stat-label">Copies déposées</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" style="color: #4a90e2;"><?php echo $stats['resultats_disponibles']; ?></div>
                                <div class="stat-label">Résultats disponibles</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" style="color: #4a90e2;"><?php echo $stats['copies_evaluees']; ?></div>
                                <div class="stat-label">Copies évaluées</div>
                            </div>
                        </div>
                    </section>

                    <!-- Concours disponibles -->
                    <section class="concours-section" style="margin-bottom: 30px;">
                        <h2 style="color: #2c3e50; margin-bottom: 25px; font-size: 1.5em;">🎯 Concours disponibles</h2>

                        <?php if (empty($concours)): ?>
                            <div class="empty-state">
                                <p>Aucun concours disponible pour le moment.</p>
                            </div>
                        <?php else: ?>
                            <div class="concours-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                                <?php foreach ($concours as $c): ?>
                                    <div class="concours-card status-<?php echo $c['status']; ?>" style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 3px solid <?php echo ($c['status'] === 'active') ? '#28a745' : (($c['status'] === 'pending') ? '#ffc107' : '#dc3545'); ?>;">
                                        <div class="concours-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                            <h3 style="color: #2c3e50; font-size: 1.1em; font-weight: 600; margin: 0; line-height: 1.2;"><?php echo htmlspecialchars($c['titre']); ?></h3>
                                            <span class="status-badge status-<?php echo $c['status']; ?>" style="padding: 4px 8px; border-radius: 12px; font-size: 0.75em; font-weight: 600; <?php echo ($c['status'] === 'active') ? 'background: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : (($c['status'] === 'pending') ? 'background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;' : 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'); ?>">
                                                <?php
                                                switch($c['status']) {
                                                    case 'pending': echo '⏳ En attente'; break;
                                                    case 'active': echo '🟢 En cours'; break;
                                                    case 'finished': echo '🔴 Terminé'; break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <div class="concours-details" style="margin-bottom: 12px;">
                                            <div style="display: flex; gap: 10px; margin-bottom: 8px; font-size: 0.85em;">
                                                <div style="background: #f8f9fa; padding: 6px 8px; border-radius: 4px; flex: 1;">
                                                    <span style="color: #6c757d;">🚀</span> <?php echo date('d/m/Y', strtotime($c['date_debut'])); ?>
                                                </div>
                                                <div style="background: #f8f9fa; padding: 6px 8px; border-radius: 4px; flex: 1;">
                                                    <span style="color: #6c757d;">🏁</span> <?php echo date('d/m/Y', strtotime($c['date_fin'])); ?>
                                                </div>
                                            </div>

                                            <?php if ($c['copie_id']): ?>
                                                <div style="background: #e3f2fd; padding: 6px 8px; border-radius: 4px; font-size: 0.8em; color: #1976d2;">
                                                    📄 Copie déposée le <?php echo date('d/m/Y', strtotime($c['date_depot'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="concours-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <?php if ($c['status'] === 'active'): ?>
                                                <?php if ($c['copie_id']): ?>
                                                    <a href="copies/voir.php?id=<?php echo $c['copie_id']; ?>" class="btn btn-secondary" style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-weight: 500; border: none; font-size: 0.85em;">👁️ Voir</a>
                                                    <a href="copies/modifier.php?copie_id=<?php echo $c['copie_id']; ?>" class="btn btn-warning" style="background: #ffc107; color: #212529; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-weight: 500; border: none; font-size: 0.85em;">✏️ Modifier</a>
                                                <?php else: ?>
                                                    <a href="copies/deposer.php?concours_id=<?php echo $c['id']; ?>" class="btn btn-primary" style="background: #007bff; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.9em; border: none;">🚀 Déposer</a>
                                                <?php endif; ?>
                                            <?php elseif ($c['status'] === 'finished' && $c['copie_id']): ?>
                                                <a href="resultats/voir.php?copie_id=<?php echo $c['copie_id']; ?>" class="btn btn-info" style="background: #17a2b8; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-weight: 500; border: none; font-size: 0.85em;">📊 Résultat</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Actions rapides -->
                    <section class="actions-section">
                        <h2 style="color: #2c3e50; margin-bottom: 25px; font-size: 1.5em;">⚡ Actions rapides</h2>
                        <div class="action-grid">
                            <a href="copies/mes_copies.php" class="action-card">
                                <div class="action-icon">📄</div>
                                <div class="action-text">Mes copies</div>
                            </a>

                            <a href="resultats/mes_resultats.php" class="action-card">
                                <div class="action-icon">📊</div>
                                <div class="action-text">Mes résultats</div>
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include "../includes/footer.php"; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>