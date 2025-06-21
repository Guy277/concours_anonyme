<?php
/**
 * Page des copies à corriger
 * Permet aux correcteurs de voir les copies qui leur sont attribuées et pas encore corrigées
 */

session_start();
require_once '../includes/config.php';

// Vérification de l'authentification et du rôle correcteur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'correcteur') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;
$copies_a_corriger = [];

// Récupération des copies attribuées au correcteur mais pas encore corrigées
try {
    // Méthode 1: Copies attribuées via la table attributions_copies
    $sql1 = "SELECT c.*, co.titre as concours_titre, co.date_debut, co.date_fin,
                    c.date_depot, ac.date_attribution
             FROM attributions_copies ac
             INNER JOIN copies c ON ac.copie_id = c.id
             INNER JOIN concours co ON c.concours_id = co.id
             WHERE ac.correcteur_id = ?
             AND c.id NOT IN (SELECT copie_id FROM corrections WHERE copie_id IS NOT NULL)
             ORDER BY ac.date_attribution ASC";

    $stmt1 = $conn->prepare($sql1);
    $stmt1->execute([$user_id]);
    $copies_a_corriger = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // Méthode 2: Si aucune copie trouvée, vérifier les copies avec correcteur_id direct
    if (empty($copies_a_corriger)) {
        $sql2 = "SELECT c.*, co.titre as concours_titre, co.date_debut, co.date_fin,
                        c.date_depot, c.date_depot as date_attribution
                 FROM copies c
                 INNER JOIN concours co ON c.concours_id = co.id
                 WHERE c.correcteur_id = ?
                 AND c.id NOT IN (SELECT copie_id FROM corrections WHERE copie_id IS NOT NULL)
                 ORDER BY c.date_depot ASC";

        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([$user_id]);
        $copies_a_corriger = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    // Méthode 3: Si toujours aucune copie, vérifier via assignations_correcteurs
    if (empty($copies_a_corriger)) {
        $sql3 = "SELECT c.*, co.titre as concours_titre, co.date_debut, co.date_fin,
                        c.date_depot, ac.date_assignation as date_attribution
                 FROM assignations_correcteurs ac
                 INNER JOIN copies c ON c.concours_id = ac.concours_id
                 INNER JOIN concours co ON c.concours_id = co.id
                 WHERE ac.correcteur_id = ?
                 AND c.id NOT IN (SELECT copie_id FROM corrections WHERE copie_id IS NOT NULL)
                 ORDER BY c.date_depot ASC";

        $stmt3 = $conn->prepare($sql3);
        $stmt3->execute([$user_id]);
        $copies_a_corriger = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    if (defined('DEBUG') && DEBUG) {
        $error = "Erreur lors de la récupération des copies à corriger : " . $e->getMessage();
    } else {
        $error = "Erreur lors de la récupération des copies à corriger.";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Copies à corriger - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>📝 Copies à corriger</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <section class="copies-section">
                        <?php if (empty($copies_a_corriger)): ?>
                            <div class="empty-state">
                                <p>🎉 Aucune copie à corriger pour le moment.</p>
                                <p>Toutes vos copies attribuées ont été corrigées ou aucune copie ne vous a encore été attribuée.</p>
                            </div>
                        <?php else: ?>
                            <div class="section-header">
                                <h2>Copies en attente de correction</h2>
                                <span class="badge"><?php echo count($copies_a_corriger); ?> copie(s)</span>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID Anonyme</th>
                                            <th>Concours</th>
                                            <th>Date d'attribution</th>
                                            <th>Date de dépôt</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($copies_a_corriger as $copie): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($copie['concours_titre']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($copie['date_attribution'])); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="<?php echo APP_URL; ?>/copies/voir.php?id=<?php echo $copie['id']; ?>" class="btn btn-small btn-outline">
                                                        👁️ Consulter
                                                    </a>
                                                    <a href="<?php echo APP_URL; ?>/corrections/evaluer_moderne.php?copie_id=<?php echo $copie['id']; ?>" class="btn btn-small btn-primary">
                                                        📝 Corriger
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-actions">
                            <a href="<?php echo APP_URL; ?>/dashboard/correcteur.php" class="btn btn-secondary">← Retour au tableau de bord</a>
                            <a href="<?php echo APP_URL; ?>/corrections/mes_corrections.php" class="btn btn-outline">Voir mes corrections</a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>
