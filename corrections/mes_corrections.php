<?php
/**
 * Interface de gestion des corrections pour les correcteurs
 * Permet aux correcteurs de voir toutes leurs corrections
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/note_calculator.php';

// Vérification de l'authentification et du rôle correcteur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'correcteur') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$correcteur_id = $_SESSION['user_id'];

// Filtres
$filtre_concours = $_GET['concours'] ?? '';
$filtre_statut = $_GET['statut'] ?? '';

try {
    // Construction de la requête avec filtres
    $sql = "SELECT 
                cp.id as copie_id,
                cp.identifiant_anonyme,
                cp.statut as statut_copie,
                co.titre as concours_titre,
                ac.date_attribution,
                cr.id as correction_id,
                cr.date_correction,
                cr.evaluation_data_json
            FROM attributions_copies ac
            JOIN copies cp ON ac.copie_id = cp.id
            JOIN concours co ON cp.concours_id = co.id
            LEFT JOIN corrections cr ON cp.id = cr.copie_id AND ac.correcteur_id = cr.correcteur_id
            WHERE ac.correcteur_id = ?";

    $params = [$correcteur_id];

    if ($filtre_concours) {
        $sql .= " AND cp.concours_id = ?";
        $params[] = $filtre_concours;
    }

    // Le filtrage par statut se fera en PHP après la récupération
    $sql .= " ORDER BY ac.date_attribution DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $all_copies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filtrage par statut en PHP
    $corrections = [];
    if ($filtre_statut) {
        foreach ($all_copies as $copie) {
            $is_corrected = !empty($copie['correction_id']);
            if ($filtre_statut === 'en_cours' && !$is_corrected) {
                $corrections[] = $copie;
            } elseif ($filtre_statut === 'corrigee' && $is_corrected) {
                $corrections[] = $copie;
            }
        }
    } else {
        $corrections = $all_copies;
    }

    // Recalculer les notes finales avec la classe unifiée
    $total_notes = 0;
    $count_notes = 0;
    foreach ($corrections as &$correction) {
        if (!empty($correction['evaluation_data_json'])) {
            $evaluation_data = json_decode($correction['evaluation_data_json'], true);
            $correction['note_finale'] = NoteCalculator::calculerNoteFinale($evaluation_data);
            if ($correction['note_finale'] > 0) {
                $total_notes += $correction['note_finale'];
                $count_notes++;
            }
        } else {
            $correction['note_finale'] = null;
        }
    }
    unset($correction); 

    // Statistiques
    $stats['total_attribuees'] = count($all_copies);
    $stats['total_corrigees'] = 0;
    foreach ($all_copies as $copie) {
        if (!empty($copie['correction_id'])) {
            $stats['total_corrigees']++;
        }
    }
    $stats['en_cours'] = $stats['total_attribuees'] - $stats['total_corrigees'];
    $stats['note_moyenne'] = $count_notes > 0 ? round($total_notes / $count_notes, 2) : 0;
    
    // Récupération des listes pour les filtres
    $sql_concours = "SELECT DISTINCT co.id, co.titre
                     FROM concours co
                     INNER JOIN copies c ON co.id = c.concours_id
                     INNER JOIN attributions_copies ac ON c.id = ac.copie_id
                     WHERE ac.correcteur_id = ?
                     ORDER BY co.titre";
    $stmt_concours = $conn->prepare($sql_concours);
    $stmt_concours->execute([$correcteur_id]);
    $concours_list = $stmt_concours->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Gestion des erreurs
    echo "Erreur lors de la récupération des corrections: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/concours_anonyme/">
    <title>Mes corrections - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .corrections-management { margin: 20px 0; }
        .filters-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .stats-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; font-size: 0.9rem; }
        .corrections-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { background: #f8f9fa; font-weight: 600; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-en_correction { background: #cce5ff; color: #004085; }
        .status-corrigee { background: #d4edda; color: #155724; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn-small { padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; text-decoration: none; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .note-display { font-weight: bold; color: #28a745; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>

                <div class="dashboard-content">
                    <h1 class="dashboard-title">
                        📝 Mes corrections
                        <span class="admin-badge">📝</span>
                    </h1>

                    <!-- Statistiques rapides -->
                    <section class="stats-overview">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total_attribuees']; ?></div>
                            <div class="stat-label">Total attribuées</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['en_cours']; ?></div>
                            <div class="stat-label">En cours</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total_corrigees']; ?></div>
                            <div class="stat-label">Corrigées</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['note_moyenne']; ?>/20</div>
                            <div class="stat-label">Note moyenne</div>
                        </div>
                    </section>

                    <!-- Filtres -->
                    <section class="filters-section">
                        <h3>🔍 Filtres</h3>
                        <form method="GET" class="filters-grid">
                            <div class="form-group">
                                <label for="concours">Concours :</label>
                                <select name="concours" id="concours">
                                    <option value="">Tous les concours</option>
                                    <?php foreach ($concours_list as $concours): ?>
                                        <option value="<?php echo $concours['id']; ?>"
                                                <?php echo ($filtre_concours == $concours['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($concours['titre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="statut">Statut :</label>
                                <select name="statut" id="statut">
                                    <option value="">Tous les statuts</option>
                                    <option value="en_cours" <?php echo ($filtre_statut == 'en_cours') ? 'selected' : ''; ?>>En cours de correction</option>
                                    <option value="corrigee" <?php echo ($filtre_statut == 'corrigee') ? 'selected' : ''; ?>>Corrigées</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                <a href="corrections/mes_corrections.php" class="btn btn-secondary">Réinitialiser</a>
                            </div>
                        </form>
                    </section>

                    <!-- Tableau des corrections -->
                    <section class="corrections-table">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Identifiant</th>
                                        <th>Concours</th>
                                        <th>Date attribution</th>
                                        <th>Statut</th>
                                        <th>Note</th>
                                        <th>Date correction</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($corrections)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                                                Aucune copie trouvée avec les filtres sélectionnés.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($corrections as $copie): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($copie['concours_titre']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($copie['date_attribution'])); ?></td>
                                                <td>
                                                    <?php if (!empty($copie['correction_id'])): ?>
                                                        <span class="status-badge status-corrigee">✅ Corrigée</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-en_correction">📝 À corriger</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($copie['note_finale'])): ?>
                                                        <span class="note-display"><?php echo number_format($copie['note_finale'], 1); ?>/20</span>
                                                    <?php else: ?>
                                                        <em>Non notée</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($copie['date_correction'])): ?>
                                                        <?php echo date('d/m/Y H:i', strtotime($copie['date_correction'])); ?>
                                                    <?php else: ?>
                                                        <em>En attente</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="corrections/voir_copie.php?id=<?php echo $copie['copie_id']; ?>" class="btn-small btn-info">
                                                            👁️ Voir
                                                        </a>
                                                        <?php if (!empty($copie['correction_id'])): ?>
                                                            <a href="resultats/voir.php?copie_id=<?php echo $copie['copie_id']; ?>" class="btn-small btn-success">
                                                                📊 Détails
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="corrections/evaluer_moderne.php?copie_id=<?php echo $copie['copie_id']; ?>" class="btn-small btn-primary">
                                                                📝 Corriger
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        // Auto-submit des filtres
        document.querySelectorAll('#concours, #statut').forEach(function(select) {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>