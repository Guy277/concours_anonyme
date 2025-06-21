<?php
/**
 * Page de comparaison entre concours
 * Permet de comparer les performances entre différents concours
 */

session_start();
require_once '../includes/config.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Récupération des concours pour la comparaison
try {
    $sql = "SELECT 
                co.id,
                co.titre,
                (SELECT COUNT(*) FROM copies WHERE concours_id = co.id) as nb_copies,
                (SELECT COUNT(*) FROM corrections cr INNER JOIN copies cp ON cr.copie_id = cp.id WHERE cp.concours_id = co.id) as nb_corrections,
                (SELECT COUNT(DISTINCT candidat_id) FROM copies WHERE concours_id = co.id) as nb_candidats,
                (SELECT AVG(TIMESTAMPDIFF(HOUR, cp.date_depot, cr.date_correction)) 
                 FROM corrections cr 
                 INNER JOIN copies cp ON cr.copie_id = cp.id 
                 WHERE cp.concours_id = co.id) as temps_moyen_correction,
                (SELECT COUNT(DISTINCT correcteur_id) 
                 FROM corrections cr 
                 INNER JOIN copies cp ON cr.copie_id = cp.id 
                 WHERE cp.concours_id = co.id) as nb_correcteurs_actifs
            FROM concours co
            WHERE co.id IN (" . implode(',', array_fill(0, count($concours_ids), '?')) . ")
            GROUP BY co.id, co.titre";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($concours_ids);
    $concours_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul des moyennes globales pour comparaison
    $moyennes_globales = [
        'note_moyenne' => 0,
        'nb_copies' => 0,
        'nb_candidats' => 0,
        'taux_correction' => 0
    ];
    
    if (!empty($concours_data)) {
        $total_notes = 0;
        $total_copies = 0;
        $total_candidats = 0;
        $total_corrections = 0;
        
        foreach ($concours_data as $concours) {
            if ($concours['note_moyenne']) {
                $total_notes += $concours['note_moyenne'];
            }
            $total_copies += $concours['nb_copies'];
            $total_candidats += $concours['nb_candidats'];
            $total_corrections += $concours['nb_corrections'];
        }
        
        $nb_concours = count($concours_data);
        $moyennes_globales['note_moyenne'] = $total_notes / $nb_concours;
        $moyennes_globales['nb_copies'] = $total_copies / $nb_concours;
        $moyennes_globales['nb_candidats'] = $total_candidats / $nb_concours;
        $moyennes_globales['taux_correction'] = $total_copies > 0 ? ($total_corrections / $total_copies) * 100 : 0;
    }
    
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
    $concours_data = [];
    $moyennes_globales = [];
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparaison concours - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <div class="page-header">
                        <h1>🔍 Comparaison entre concours</h1>
                        <div class="header-actions">
                            <a href="<?php echo APP_URL; ?>/admin/statistiques_globales.php" class="btn btn-secondary">← Statistiques globales</a>
                            <a href="<?php echo APP_URL; ?>/dashboard/admin.php" class="btn btn-info">🏠 Dashboard</a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($concours_data)): ?>
                    
                    <!-- Moyennes de référence -->
                    <section class="reference-section">
                        <h2>📊 Moyennes de référence</h2>
                        <div class="stats-grid">
                            <div class="stat-card reference">
                                <div class="stat-number"><?php echo number_format($moyennes_globales['note_moyenne'], 1); ?></div>
                                <div class="stat-label">Note moyenne globale</div>
                            </div>
                            <div class="stat-card reference">
                                <div class="stat-number"><?php echo number_format($moyennes_globales['nb_copies'], 0); ?></div>
                                <div class="stat-label">Copies par concours</div>
                            </div>
                            <div class="stat-card reference">
                                <div class="stat-number"><?php echo number_format($moyennes_globales['nb_candidats'], 0); ?></div>
                                <div class="stat-label">Candidats par concours</div>
                            </div>
                            <div class="stat-card reference">
                                <div class="stat-number"><?php echo number_format($moyennes_globales['taux_correction'], 1); ?>%</div>
                                <div class="stat-label">Taux de correction</div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Graphique de comparaison -->
                    <section class="comparison-charts">
                        <div class="chart-card large">
                            <h3>📈 Évolution des notes moyennes par concours</h3>
                            <canvas id="comparisonChart" width="800" height="300"></canvas>
                        </div>
                    </section>
                    
                    <!-- Tableau de comparaison détaillé -->
                    <section class="comparison-table">
                        <h2>📋 Comparaison détaillée</h2>
                        <div class="table-responsive">
                            <table class="table comparison">
                                <thead>
                                    <tr>
                                        <th>Concours</th>
                                        <th>Période</th>
                                        <th>Participation</th>
                                        <th>Performance</th>
                                        <th>Qualité</th>
                                        <th>Efficacité</th>
                                        <th>Tendance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($concours_data as $concours): ?>
                                    <?php
                                    $taux_correction = $concours['nb_copies'] > 0 ? ($concours['nb_corrections'] / $concours['nb_copies']) * 100 : 0;
                                    $note_moyenne = $concours['note_moyenne'] ?? 0;
                                    
                                    // Calcul des tendances par rapport aux moyennes
                                    $tendance_note = $note_moyenne - $moyennes_globales['note_moyenne'];
                                    $tendance_participation = $concours['nb_candidats'] - $moyennes_globales['nb_candidats'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($concours['titre']); ?></strong>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($concours['date_debut'])); ?><br>
                                                <?php echo date('d/m/Y', strtotime($concours['date_fin'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="metric-group">
                                                <span class="metric-value"><?php echo $concours['nb_candidats']; ?></span>
                                                <span class="metric-label">candidats</span>
                                                <?php if ($tendance_participation > 0): ?>
                                                    <span class="trend-up">↗ +<?php echo number_format($tendance_participation, 0); ?></span>
                                                <?php elseif ($tendance_participation < 0): ?>
                                                    <span class="trend-down">↘ <?php echo number_format($tendance_participation, 0); ?></span>
                                                <?php else: ?>
                                                    <span class="trend-stable">→</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="metric-group">
                                                <span class="metric-value"><?php echo number_format($note_moyenne, 1); ?>/20</span>
                                                <span class="metric-label">moyenne</span>
                                                <?php if ($tendance_note > 0.5): ?>
                                                    <span class="trend-up">↗ +<?php echo number_format($tendance_note, 1); ?></span>
                                                <?php elseif ($tendance_note < -0.5): ?>
                                                    <span class="trend-down">↘ <?php echo number_format($tendance_note, 1); ?></span>
                                                <?php else: ?>
                                                    <span class="trend-stable">→</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="metric-group">
                                                <span class="metric-value"><?php echo number_format($concours['ecart_type'] ?? 0, 1); ?></span>
                                                <span class="metric-label">écart-type</span>
                                                <span class="range-info">
                                                    <?php echo number_format($concours['note_min'] ?? 0, 1); ?> - 
                                                    <?php echo number_format($concours['note_max'] ?? 0, 1); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="metric-group">
                                                <span class="metric-value <?php echo $taux_correction >= 100 ? 'taux-complet' : ($taux_correction >= 80 ? 'taux-bon' : 'taux-faible'); ?>">
                                                    <?php echo number_format($taux_correction, 1); ?>%
                                                </span>
                                                <span class="metric-label">correction</span>
                                                <span class="correction-info">
                                                    <?php echo $concours['nb_corrections']; ?>/<?php echo $concours['nb_copies']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $score_global = 0;
                                            $score_global += ($note_moyenne / 20) * 40; // 40% pour la note
                                            $score_global += ($taux_correction / 100) * 30; // 30% pour le taux de correction
                                            $score_global += min(($concours['nb_candidats'] / 50), 1) * 20; // 20% pour la participation
                                            $score_global += (1 - min(($concours['ecart_type'] ?? 5) / 5, 1)) * 10; // 10% pour l'homogénéité
                                            
                                            if ($score_global >= 80) {
                                                echo '<span class="performance-excellente">Excellent</span>';
                                            } elseif ($score_global >= 60) {
                                                echo '<span class="performance-bonne">Bon</span>';
                                            } else {
                                                echo '<span class="performance-faible">À améliorer</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    
                    <?php else: ?>
                    <div class="empty-state">
                        <h3>📊 Aucune donnée disponible</h3>
                        <p>Aucun concours avec des corrections n'a été trouvé pour effectuer une comparaison.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
    
    <!-- Script pour le graphique de comparaison -->
    <script>
        <?php if (!empty($concours_data)): ?>
        // Données pour le graphique de comparaison
        const comparisonData = <?php echo json_encode($concours_data); ?>;
        const comparisonLabels = comparisonData.map(item => item.titre);
        const comparisonNotes = comparisonData.map(item => parseFloat(item.note_moyenne) || 0);
        const moyenneGlobale = <?php echo $moyennes_globales['note_moyenne']; ?>;
        
        // Graphique de comparaison
        const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
        new Chart(comparisonCtx, {
            type: 'line',
            data: {
                labels: comparisonLabels,
                datasets: [{
                    label: 'Note moyenne par concours',
                    data: comparisonNotes,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Moyenne globale',
                    data: new Array(comparisonLabels.length).fill(moyenneGlobale),
                    borderColor: '#dc3545',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 20,
                        ticks: {
                            stepSize: 2
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
