<?php
/**
 * Page de statistiques globales par concours
 * Affiche des graphiques et métriques détaillées
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/note_calculator.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Récupération des concours pour le filtre
try {
    $sql = "SELECT id, titre, date_debut, date_fin FROM concours ORDER BY date_debut DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $concours_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des concours : " . $e->getMessage();
    $concours_list = [];
}

// Filtre par concours
$concours_id = (int)($_GET['concours_id'] ?? 0);

// Récupération des statistiques globales
try {
    // Statistiques globales - Utiliser la classe unifiée
    $sql = "SELECT evaluation_data_json FROM corrections WHERE evaluation_data_json IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
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
    
    $stats_globales = NoteCalculator::calculerStatistiques($notes_calculees);
    $stats_globales['note_moyenne_globale'] = $stats_globales['moyenne'];
    
    // Ajouter les statistiques de base manquantes
    $sql_base = "SELECT 
                    (SELECT COUNT(*) FROM concours) as total_concours,
                    (SELECT COUNT(*) FROM copies WHERE statut IN ('corrigee', 'correction_soumise')) as total_copies,
                    (SELECT COUNT(*) FROM corrections) as total_corrections,
                    (SELECT COUNT(DISTINCT id) FROM utilisateurs WHERE role = 'candidat') as total_candidats,
                    (SELECT COUNT(DISTINCT id) FROM utilisateurs WHERE role = 'correcteur') as total_correcteurs";
    $stmt = $conn->prepare($sql_base);
    $stmt->execute();
    $stats_base = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats_globales['total_concours'] = $stats_base['total_concours'] ?? 0;
    $stats_globales['total_copies'] = $stats_base['total_copies'] ?? 0;
    $stats_globales['total_corrections'] = $stats_base['total_corrections'] ?? 0;
    $stats_globales['total_candidats'] = $stats_base['total_candidats'] ?? 0;
    $stats_globales['total_correcteurs'] = $stats_base['total_correcteurs'] ?? 0;
    
    // Statistiques par concours - Utiliser la classe unifiée
    $sql = "SELECT 
                MIN(co.id) as id,
                TRIM(co.titre) as titre,
                MIN(co.date_debut) as date_debut,
                MAX(co.date_fin) as date_fin,
                SUM(stats.nb_copies) as nb_copies,
                SUM(stats.nb_corrections) as nb_corrections,
                SUM(stats.nb_candidats) as nb_candidats
            FROM concours co
            JOIN (
                SELECT 
                    c.id as concours_id,
                    COUNT(cp.id) as nb_copies,
                    COUNT(cor.id) as nb_corrections,
                    COUNT(DISTINCT cp.candidat_id) as nb_candidats
                FROM concours c
                LEFT JOIN copies cp ON c.id = cp.concours_id AND cp.statut IN ('corrigee', 'correction_soumise')
                LEFT JOIN corrections cor ON cp.id = cor.copie_id
                GROUP BY c.id
            ) as stats ON co.id = stats.concours_id
            WHERE stats.nb_copies > 0";
    
    if ($concours_id > 0) {
        $sql .= " AND co.id = ?";
    }
    
    $sql .= " GROUP BY TRIM(co.titre)
              ORDER BY date_debut DESC";
    
    $stmt = $conn->prepare($sql);
    if ($concours_id > 0) {
        $stmt->execute([$concours_id]);
    } else {
        $stmt->execute();
    }
    $stats_par_concours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul des notes par concours avec la classe unifiée
    foreach ($stats_par_concours as &$stat) {
        $sql_notes_concours = "SELECT evaluation_data_json FROM corrections cor
                               INNER JOIN copies cp ON cor.copie_id = cp.id
                               WHERE cp.concours_id = ? 
                               AND cp.statut IN ('corrigee', 'correction_soumise')
                               AND cor.evaluation_data_json IS NOT NULL";
        
        $stmt = $conn->prepare($sql_notes_concours);
        $stmt->execute([$stat['id']]);
        $corrections_concours = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $notes_concours = [];
        foreach ($corrections_concours as $json_data) {
            $data = json_decode($json_data, true);
            if ($data) {
                $note_calculee = NoteCalculator::calculerNoteFinale($data);
                if ($note_calculee > 0) {
                    $notes_concours[] = $note_calculee;
                }
            }
        }
        
        $stats_concours = NoteCalculator::calculerStatistiques($notes_concours);
        $stat['note_moyenne'] = $stats_concours['moyenne'];
        $stat['note_min'] = $stats_concours['min'];
        $stat['note_max'] = $stats_concours['max'];
        $stat['ecart_type'] = $stats_concours['ecart_type'];
    }
    
    // Répartition des notes (pour graphique) - Utiliser la classe unifiée
    $sql = "SELECT evaluation_data_json FROM corrections cor
            INNER JOIN copies cp ON cor.copie_id = cp.id
            WHERE cp.statut IN ('corrigee', 'correction_soumise')";
    
    if ($concours_id > 0) {
        $sql .= " AND cp.concours_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    if ($concours_id > 0) {
        $stmt->execute([$concours_id]);
    } else {
        $stmt->execute();
    }
    $corrections_repartition = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $repartition_notes = [];
    $tranches = ['0-4' => 0, '5-7' => 0, '8-9' => 0, '10-11' => 0, '12-13' => 0, '14-15' => 0, '16-17' => 0, '18-20' => 0];
    
    foreach ($corrections_repartition as $json_data) {
        $data = json_decode($json_data, true);
        if ($data) {
            $note_finale = NoteCalculator::calculerNoteFinale($data);
            
            if ($note_finale < 5) $tranches['0-4']++;
            elseif ($note_finale < 8) $tranches['5-7']++;
            elseif ($note_finale < 10) $tranches['8-9']++;
            elseif ($note_finale < 12) $tranches['10-11']++;
            elseif ($note_finale < 14) $tranches['12-13']++;
            elseif ($note_finale < 16) $tranches['14-15']++;
            elseif ($note_finale < 18) $tranches['16-17']++;
            else $tranches['18-20']++;
        }
    }
    
    foreach ($tranches as $tranche => $count) {
        if ($count > 0) {
            $repartition_notes[] = ['tranche_note' => $tranche, 'nb_copies' => $count];
        }
    }
    
    // Performance des correcteurs - Utiliser la classe unifiée
    $sql = "SELECT 
                u.id as correcteur_id,
                CONCAT(u.prenom, ' ', u.nom) as correcteur_nom,
                COUNT(cor.id) as nb_corrections,
                AVG(TIMESTAMPDIFF(HOUR, cp.date_depot, cor.date_correction)) as temps_moyen_correction
            FROM utilisateurs u
            INNER JOIN corrections cor ON u.id = cor.correcteur_id
            INNER JOIN copies cp ON cor.copie_id = cp.id
            WHERE u.role = 'correcteur'";
    
    if ($concours_id > 0) {
        $sql .= " AND cp.concours_id = ?";
    }
    
    $sql .= " GROUP BY u.id, u.prenom, u.nom
              HAVING nb_corrections > 0
              ORDER BY nb_corrections DESC";
    
    $stmt = $conn->prepare($sql);
    if ($concours_id > 0) {
        $stmt->execute([$concours_id]);
    } else {
        $stmt->execute();
    }
    $performance_correcteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul des notes moyennes par correcteur avec la classe unifiée
    foreach ($performance_correcteurs as &$correcteur) {
        $sql_notes_correcteur = "SELECT evaluation_data_json FROM corrections cor
                                 INNER JOIN copies cp ON cor.copie_id = cp.id
                                 WHERE cor.correcteur_id = ? 
                                 AND cp.statut IN ('corrigee', 'correction_soumise')
                                 AND cor.evaluation_data_json IS NOT NULL";
        
        $stmt = $conn->prepare($sql_notes_correcteur);
        $stmt->execute([$correcteur['correcteur_id']]);
        $corrections_correcteur = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $notes_correcteur = [];
        foreach ($corrections_correcteur as $json_data) {
            $data = json_decode($json_data, true);
            if ($data) {
                $note_calculee = NoteCalculator::calculerNoteFinale($data);
                if ($note_calculee > 0) {
                    $notes_correcteur[] = $note_calculee;
                }
            }
        }
        
        $correcteur['note_moyenne'] = !empty($notes_correcteur) ? round(array_sum($notes_correcteur) / count($notes_correcteur), 2) : 0;
        
        // Déterminer le niveau de performance
        if ($correcteur['note_moyenne'] >= 15) {
            $correcteur['performance'] = 'Excellent';
        } elseif ($correcteur['note_moyenne'] >= 12) {
            $correcteur['performance'] = 'Bon';
        } elseif ($correcteur['note_moyenne'] >= 10) {
            $correcteur['performance'] = 'Moyen';
        } else {
            $correcteur['performance'] = 'À améliorer';
        }
    }
    
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
    $stats_globales = [];
    $stats_par_concours = [];
    $repartition_notes = [];
    $performance_correcteurs = [];
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques globales - Concours Anonyme</title>
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
                        <h1>📈 Statistiques globales</h1>
                        <div class="header-actions">
                            <a href="<?php echo APP_URL; ?>/dashboard/admin.php" class="btn btn-secondary">← Retour au dashboard</a>
                            <a href="<?php echo APP_URL; ?>/admin/comparaison_concours.php" class="btn btn-warning">🔍 Comparer</a>
                            <a href="<?php echo APP_URL; ?>/exports/resultats.php" class="btn btn-info">📊 Exporter</a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <!-- Filtre par concours -->
                    <section class="filter-section">
                        <form method="GET" class="filter-form">
                            <div class="form-group">
                                <label for="concours_id">Filtrer par concours :</label>
                                <select name="concours_id" id="concours_id" onchange="this.form.submit()">
                                    <option value="0">Tous les concours</option>
                                    <?php foreach ($concours_list as $concours): ?>
                                    <option value="<?php echo $concours['id']; ?>" <?php echo $concours_id == $concours['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($concours['titre']); ?>
                                        (<?php echo date('d/m/Y', strtotime($concours['date_debut'])); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </section>
                    
                    <!-- Statistiques globales -->
                    <section class="stats-overview">
                        <h2>📊 Vue d'ensemble</h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats_globales['total_concours'] ?? 0; ?></div>
                                <div class="stat-label">Concours</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats_globales['total_copies'] ?? 0; ?></div>
                                <div class="stat-label">Copies déposées</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats_globales['total_corrections'] ?? 0; ?></div>
                                <div class="stat-label">Corrections effectuées</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo number_format($stats_globales['note_moyenne_globale'] ?? 0, 1); ?></div>
                                <div class="stat-label">Note moyenne</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats_globales['total_candidats'] ?? 0; ?></div>
                                <div class="stat-label">Candidats</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats_globales['total_correcteurs'] ?? 0; ?></div>
                                <div class="stat-label">Correcteurs</div>
                            </div>
                        </div>
                    </section>

                    <!-- Graphiques -->
                    <section class="charts-section">
                        <div class="charts-grid">
                            <!-- Répartition des notes -->
                            <div class="chart-card">
                                <h3>📊 Répartition des notes</h3>
                                <div class="chart-container">
                                    <canvas id="notesChart"></canvas>
                                </div>
                            </div>

                            <!-- Performance des correcteurs -->
                            <div class="chart-card">
                                <h3>👥 Performance des correcteurs</h3>
                                <div class="chart-container">
                                    <canvas id="correcteursChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Tableaux détaillés -->
                    <?php if (!empty($stats_par_concours)): ?>
                    <section class="tables-section">
                        <h2>📋 Statistiques détaillées par concours</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Concours</th>
                                        <th>Période</th>
                                        <th>Copies</th>
                                        <th>Corrections</th>
                                        <th>Candidats</th>
                                        <th>Note moyenne</th>
                                        <th>Note min/max</th>
                                        <th>Écart-type</th>
                                        <th>Taux correction</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats_par_concours as $stat): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($stat['titre']); ?></strong></td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($stat['date_debut'])); ?> -
                                            <?php echo date('d/m/Y', strtotime($stat['date_fin'])); ?>
                                        </td>
                                        <td><?php echo $stat['nb_copies']; ?></td>
                                        <td><?php echo $stat['nb_corrections']; ?></td>
                                        <td><?php echo $stat['nb_candidats']; ?></td>
                                        <td>
                                            <span class="note-moyenne"><?php echo number_format($stat['note_moyenne'] ?? 0, 1); ?>/20</span>
                                        </td>
                                        <td>
                                            <?php echo number_format($stat['note_min'] ?? 0, 1); ?> /
                                            <?php echo number_format($stat['note_max'] ?? 0, 1); ?>
                                        </td>
                                        <td><?php echo number_format($stat['ecart_type'] ?? 0, 1); ?></td>
                                        <td>
                                            <?php
                                            $taux = $stat['nb_copies'] > 0 ? ($stat['nb_corrections'] / $stat['nb_copies']) * 100 : 0;
                                            $taux_class = $taux >= 100 ? 'taux-complet' : ($taux >= 80 ? 'taux-bon' : 'taux-faible');
                                            ?>
                                            <span class="<?php echo $taux_class; ?>"><?php echo number_format($taux, 1); ?>%</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Performance des correcteurs -->
                    <?php if (!empty($performance_correcteurs)): ?>
                    <section class="tables-section">
                        <h2>👥 Performance des correcteurs</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Correcteur</th>
                                        <th>Nb corrections</th>
                                        <th>Note moyenne attribuée</th>
                                        <th>Temps moyen de correction</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($performance_correcteurs as $perf): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($perf['correcteur_nom']); ?></strong></td>
                                        <td><?php echo $perf['nb_corrections']; ?></td>
                                        <td><?php echo number_format($perf['note_moyenne'] ?? 0, 1); ?>/20</td>
                                        <td><?php echo number_format($perf['temps_moyen_correction'] ?? 0, 1); ?>h</td>
                                        <td>
                                            <?php
                                            $nb_corrections = $perf['nb_corrections'];
                                            if ($nb_corrections >= 10) {
                                                echo '<span class="performance-excellente">Excellente</span>';
                                            } elseif ($nb_corrections >= 5) {
                                                echo '<span class="performance-bonne">Bonne</span>';
                                            } else {
                                                echo '<span class="performance-faible">Débutant</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>

    <!-- Scripts pour les graphiques -->
    <script>
        // Données pour le graphique de répartition des notes
        const notesData = <?php echo json_encode($repartition_notes); ?>;
        const notesLabels = notesData.map(item => item.tranche_note);
        const notesValues = notesData.map(item => parseInt(item.nb_copies));

        // Graphique répartition des notes
        const notesCtx = document.getElementById('notesChart').getContext('2d');
        new Chart(notesCtx, {
            type: 'bar',
            data: {
                labels: notesLabels,
                datasets: [{
                    label: 'Nombre de copies',
                    data: notesValues,
                    backgroundColor: [
                        '#dc3545', '#fd7e14', '#ffc107', '#28a745',
                        '#20c997', '#17a2b8', '#6f42c1', '#e83e8c'
                    ],
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 10
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // Données pour le graphique des correcteurs
        const correcteursData = <?php echo json_encode($performance_correcteurs); ?>;
        const correcteursLabels = correcteursData.map(item => item.correcteur_nom);
        const correcteursValues = correcteursData.map(item => parseInt(item.nb_corrections));

        // Graphique performance correcteurs
        const correcteursCtx = document.getElementById('correcteursChart').getContext('2d');
        new Chart(correcteursCtx, {
            type: 'doughnut',
            data: {
                labels: correcteursLabels,
                datasets: [{
                    data: correcteursValues,
                    backgroundColor: [
                        '#007bff', '#28a745', '#ffc107', '#dc3545',
                        '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 10
                            },
                            boxWidth: 12,
                            padding: 8
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
