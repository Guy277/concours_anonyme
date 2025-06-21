<?php
/**
 * Interface d'export des résultats
 * Permet d'exporter les résultats en Excel, PDF et CSV
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

// Traitement de l'export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $format = $_POST['format'] ?? '';
    $concours_id = (int)($_POST['concours_id'] ?? 0);
    $include_personal_data = isset($_POST['include_personal_data']);
    $include_comments = isset($_POST['include_comments']);

    if (!in_array($format, ['excel', 'pdf', 'csv'])) {
        $error = "Format d'export non valide.";
    } else {
        // Redirection vers le script d'export
        $params = http_build_query([
            'format' => $format,
            'concours_id' => $concours_id,
            'include_personal_data' => $include_personal_data ? 1 : 0,
            'include_comments' => $include_comments ? 1 : 0
        ]);

        header("Location: export_process.php?" . $params);
        exit();
    }
}

// Récupération des statistiques pour l'aperçu
$stats = [];
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
    
    $stats['note_moyenne'] = !empty($notes_calculees) ? round(array_sum($notes_calculees) / count($notes_calculees), 2) : 0;

    // Statistiques globales des copies et corrections
    $sql_global = "SELECT 
                        COUNT(DISTINCT cp.id) as total_copies,
                        COUNT(DISTINCT cor.id) as total_corrections,
                        COUNT(DISTINCT co.id) as total_concours
                    FROM concours co
                    LEFT JOIN copies cp ON co.id = cp.concours_id
                    LEFT JOIN corrections cor ON cp.id = cor.copie_id";
    
    $stmt = $conn->prepare($sql_global);
    $stmt->execute();
    $stats_global = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total_copies'] = $stats_global['total_copies'] ?? 0;
    $stats['total_corrections'] = $stats_global['total_corrections'] ?? 0;
    $stats['total_concours'] = $stats_global['total_concours'] ?? 0;

    // Statistiques par concours
    $sql = "SELECT
                TRIM(co.titre) as titre,
                SUM(stats.nb_copies) as nb_copies,
                SUM(stats.nb_corrections) as nb_corrections,
                MIN(co.id) as concours_id
            FROM concours co
            JOIN (
                SELECT 
                    c.id as concours_id,
                    COUNT(cp.id) as nb_copies,
                    COUNT(cor.id) as nb_corrections
                FROM concours c
                LEFT JOIN copies cp ON c.id = cp.concours_id AND cp.statut IN ('corrigee', 'correction_soumise')
                LEFT JOIN corrections cor ON cp.id = cor.copie_id
                GROUP BY c.id
            ) as stats ON co.id = stats.concours_id
            WHERE stats.nb_copies > 0
            GROUP BY TRIM(co.titre)
            ORDER BY MIN(co.date_debut) DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stats_par_concours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcul des notes par concours avec la classe unifiée
    foreach ($stats_par_concours as &$stat) {
        $sql_notes_concours = "SELECT evaluation_data_json FROM corrections cor
                               INNER JOIN copies cp ON cor.copie_id = cp.id
                               WHERE cp.concours_id = ?
                               AND cp.statut IN ('corrigee', 'correction_soumise')
                               AND cor.evaluation_data_json IS NOT NULL";
        
        $stmt = $conn->prepare($sql_notes_concours);
        $stmt->execute([$stat['concours_id']]);
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
        
        $stat['note_moyenne'] = !empty($notes_concours) ? round(array_sum($notes_concours) / count($notes_concours), 2) : 0;
        $stat['taux_correction'] = $stat['nb_copies'] > 0 ? round(($stat['nb_corrections'] / $stat['nb_copies']) * 100, 1) : 0;
    }

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
    $stats = ['total_copies' => 0, 'total_corrections' => 0, 'total_concours' => 0, 'note_moyenne' => 0];
    $stats_par_concours = [];
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export des résultats - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .format-info {
            display: block;
            margin-top: 8px;
            padding: 8px 12px;
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #1976d2;
        }
        .export-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-options {
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
        }
        .checkbox-label input[type="checkbox"] {
            margin-top: 2px;
        }
        .checkbox-text {
            font-weight: 500;
        }
        .checkbox-label small {
            display: block;
            margin-top: 4px;
            color: #d32f2f;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>

                <div class="dashboard-content">
                    <div class="page-header">
                        <h1>📊 Export des résultats</h1>
                        <div class="header-actions">
                            <a href="<?php echo APP_URL; ?>/dashboard/admin.php" class="btn btn-secondary">← Retour au dashboard</a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <!-- Statistiques d'aperçu -->
                    <section class="stats-overview">
                        <h2>📈 Aperçu des données</h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_copies'] ?? 0; ?></div>
                                <div class="stat-label">Copies déposées</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_corrections'] ?? 0; ?></div>
                                <div class="stat-label">Corrections effectuées</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_concours'] ?? 0; ?></div>
                                <div class="stat-label">Concours actifs</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo number_format($stats['note_moyenne'] ?? 0, 1); ?></div>
                                <div class="stat-label">Note moyenne</div>
                            </div>
                        </div>
                    </section>

                    <!-- Formulaire d'export -->
                    <section class="export-section">
                        <h2>⬇️ Exporter les résultats</h2>

                        <form method="POST" class="export-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="format">Format d'export :</label>
                                    <select name="format" id="format" required>
                                        <option value="">Choisir un format...</option>
                                        <option value="excel">📊 Excel (CSV compatible)</option>
                                        <option value="pdf">📄 PDF (CSV compatible)</option>
                                        <option value="csv">📋 CSV (Format natif)</option>
                                    </select>
                                    <small class="format-info">
                                        💡 Tous les formats génèrent des fichiers CSV compatibles avec Excel, LibreOffice et Google Sheets
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="concours_id">Concours :</label>
                                    <select name="concours_id" id="concours_id">
                                        <option value="0">Tous les concours</option>
                                        <?php foreach ($concours_list as $concours): ?>
                                        <option value="<?php echo $concours['id']; ?>">
                                            <?php echo htmlspecialchars($concours['titre']); ?>
                                            (<?php echo date('d/m/Y', strtotime($concours['date_debut'])); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-options">
                                <h3>Options d'export :</h3>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="include_personal_data" value="1">
                                        <span class="checkbox-text">Inclure les données personnelles (noms, emails)</span>
                                        <small>⚠️ Attention : cela lève l'anonymat</small>
                                    </label>

                                    <label class="checkbox-label">
                                        <input type="checkbox" name="include_comments" value="1" checked>
                                        <span class="checkbox-text">Inclure les commentaires des correcteurs</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="export" class="btn btn-primary">
                                    📥 Générer l'export
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Statistiques par concours -->
                    <?php if (!empty($stats_par_concours)): ?>
                    <section class="concours-stats">
                        <h2>📊 Statistiques par concours</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Concours</th>
                                        <th>Copies</th>
                                        <th>Corrections</th>
                                        <th>Note moyenne</th>
                                        <th>Taux de correction</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats_par_concours as $stat): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($stat['titre']); ?></strong></td>
                                        <td><?php echo $stat['nb_copies']; ?></td>
                                        <td><?php echo $stat['nb_corrections']; ?></td>
                                        <td><?php echo number_format($stat['note_moyenne'] ?? 0, 1); ?>/20</td>
                                        <td>
                                            <?php
                                            echo number_format($stat['taux_correction'], 1) . '%';
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
</body>
</html>