<?php
/**
 * Interface de gestion globale des copies pour les administrateurs
 * Permet de voir toutes les copies, les attribuer aux correcteurs et gérer les réattributions
 */

session_start();
require_once '../includes/config.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = null;
$success = null;
$user_id = $_SESSION['user_id'];
$is_super_admin = ($user_id == 1);

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'attribuer':
            $copie_id = (int)($_POST['copie_id'] ?? 0);
            $correcteur_id = (int)($_POST['correcteur_id'] ?? 0);

            if ($copie_id && $correcteur_id) {
                try {
                    // Vérifier que le correcteur existe et a le bon rôle
                    $sql = "SELECT id FROM utilisateurs WHERE id = ? AND role = 'correcteur'";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$correcteur_id]);

                    if ($stmt->fetch()) {
                        // Vérifier si une attribution existe déjà
                        $sql = "SELECT id FROM attributions_copies WHERE copie_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$copie_id]);

                        if ($stmt->fetch()) {
                            // Mise à jour de l'attribution existante
                            $sql = "UPDATE attributions_copies SET correcteur_id = ?, date_attribution = NOW() WHERE copie_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$correcteur_id, $copie_id]);
                            $success = "Attribution mise à jour avec succès.";
                        } else {
                            // Nouvelle attribution
                            $sql = "INSERT INTO attributions_copies (copie_id, correcteur_id, date_attribution) VALUES (?, ?, NOW())";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$copie_id, $correcteur_id]);
                            $success = "Copie attribuée avec succès.";
                        }

                        // Mettre à jour le statut de la copie
                        $sql = "UPDATE copies SET statut = 'en_correction' WHERE id = ? AND statut = 'en_attente'";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$copie_id]);

                    } else {
                        $error = "Le correcteur sélectionné n'existe pas ou n'a pas le bon rôle.";
                    }
                } catch (PDOException $e) {
                    $error = "Erreur lors de l'attribution : " . $e->getMessage();
                }
            } else {
                $error = "Paramètres invalides pour l'attribution.";
            }
            break;

        case 'retirer_attribution':
            $copie_id = (int)($_POST['copie_id'] ?? 0);

            if ($copie_id) {
                try {
                    // Supprimer l'attribution
                    $sql = "DELETE FROM attributions_copies WHERE copie_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$copie_id]);

                    // Remettre le statut en attente si pas encore corrigée
                    $sql = "UPDATE copies SET statut = 'en_attente' WHERE id = ? AND statut = 'en_correction'";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$copie_id]);

                    $success = "Attribution retirée avec succès.";
                } catch (PDOException $e) {
                    $error = "Erreur lors du retrait de l'attribution : " . $e->getMessage();
                }
            }
            break;

        case 'supprimer_copie':
            $copie_id = (int)($_POST['copie_id'] ?? 0);

            if ($copie_id) {
                try {
                    // Récupérer les informations de la copie avant suppression
                    $sql = "SELECT cp.*, co.titre as concours_titre,
                                   CONCAT(u.prenom, ' ', u.nom) as candidat_nom
                            FROM copies cp
                            INNER JOIN concours co ON cp.concours_id = co.id
                            LEFT JOIN utilisateurs u ON cp.candidat_id = u.id
                            WHERE cp.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$copie_id]);
                    $copie_info = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($copie_info) {
                        // Supprimer d'abord les corrections associées
                        $sql = "DELETE FROM corrections WHERE copie_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$copie_id]);

                        // Supprimer les attributions
                        $sql = "DELETE FROM attributions_copies WHERE copie_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$copie_id]);

                        // Supprimer le fichier physique si possible
                        if (!empty($copie_info['fichier_path'])) {
                            require_once '../includes/anonymisation.php';
                            $anonymisation = new Anonymisation($conn);
                            $fichier_path = $anonymisation->dechiffrerChemin($copie_info['fichier_path']);
                            if ($fichier_path && file_exists($fichier_path)) {
                                unlink($fichier_path);
                            }
                        }

                        // Supprimer la copie
                        $sql = "DELETE FROM copies WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$copie_id]);

                        $success = "Copie supprimée avec succès : " . $copie_info['identifiant_anonyme'] .
                                  " (" . $copie_info['concours_titre'] . ")";
                    } else {
                        $error = "Copie introuvable.";
                    }
                } catch (PDOException $e) {
                    $error = "Erreur lors de la suppression de la copie : " . $e->getMessage();
                }
            }
            break;
    }
}

// Filtres
$filtre_concours = $_GET['concours'] ?? '';
$filtre_statut = $_GET['statut'] ?? '';
$filtre_correcteur = $_GET['correcteur'] ?? '';
$filtre_candidat = $_GET['candidat_id'] ?? '';

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];

if ($filtre_concours) {
    $where_conditions[] = "co.id = ?";
    $params[] = $filtre_concours;
}

if ($filtre_statut) {
    $where_conditions[] = "cp.statut = ?";
    $params[] = $filtre_statut;
}

if ($filtre_correcteur) {
    if ($filtre_correcteur === 'non_attribue') {
        $where_conditions[] = "ac.correcteur_id IS NULL";
    } else {
        $where_conditions[] = "ac.correcteur_id = ?";
        $params[] = $filtre_correcteur;
    }
}

if ($filtre_candidat) {
    $where_conditions[] = "cp.candidat_id = ?";
    $params[] = $filtre_candidat;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Récupération des copies avec informations complètes
$sql = "SELECT
            cp.id,
            cp.identifiant_anonyme,
            cp.date_depot,
            cp.statut,
            co.titre as concours_titre,
            co.id as concours_id,
            u_candidat.nom as candidat_nom,
            u_candidat.prenom as candidat_prenom,
            u_candidat.email as candidat_email,
            ac.correcteur_id,
            u_correcteur.nom as correcteur_nom,
            u_correcteur.prenom as correcteur_prenom,
            ac.date_attribution,
            corr.date_correction,
            JSON_UNQUOTE(JSON_EXTRACT(corr.evaluation_data_json, '$.note_totale')) as note_finale
        FROM copies cp
        INNER JOIN concours co ON cp.concours_id = co.id
        LEFT JOIN utilisateurs u_candidat ON cp.candidat_id = u_candidat.id
        LEFT JOIN attributions_copies ac ON cp.id = ac.copie_id
        LEFT JOIN utilisateurs u_correcteur ON ac.correcteur_id = u_correcteur.id
        LEFT JOIN corrections corr ON cp.id = corr.copie_id
        $where_clause
        ORDER BY cp.date_depot DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$copies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des listes pour les filtres
$sql_concours = "SELECT id, titre FROM concours ORDER BY date_creation DESC";
$stmt_concours = $conn->prepare($sql_concours);
$stmt_concours->execute();
$concours_list = $stmt_concours->fetchAll(PDO::FETCH_ASSOC);

$sql_correcteurs = "SELECT id, nom, prenom FROM utilisateurs WHERE role = 'correcteur' ORDER BY nom, prenom";
$stmt_correcteurs = $conn->prepare($sql_correcteurs);
$stmt_correcteurs->execute();
$correcteurs_list = $stmt_correcteurs->fetchAll(PDO::FETCH_ASSOC);

// Statistiques rapides
$sql_stats = "SELECT
                COUNT(*) as total,
                COUNT(CASE WHEN statut = 'en_attente' THEN 1 END) as en_attente,
                COUNT(CASE WHEN statut = 'en_correction' THEN 1 END) as en_correction,
                COUNT(CASE WHEN statut = 'correction_soumise' THEN 1 END) as correction_soumise,
                COUNT(CASE WHEN statut = 'corrigee' THEN 1 END) as corrigees
              FROM copies";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/concours_anonyme/">
    <title>Gestion des copies - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .copies-management { margin: 20px 0; }
        .filters-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .stats-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; font-size: 0.9rem; }
        .copies-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
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
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .attribution-form { display: inline-flex; gap: 5px; align-items: center; }
        .attribution-form select { padding: 4px; font-size: 0.8rem; }
        .attribution-form button { padding: 4px 8px; font-size: 0.8rem; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 15% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 500px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
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
                        📄 Gestion des copies
                        <?php if ($is_super_admin): ?>
                            <span class="admin-badge super-admin">👑</span>
                        <?php else: ?>
                            <span class="admin-badge admin">⭐</span>
                        <?php endif; ?>
                    </h1>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <?php if ($filtre_candidat): ?>
                        <?php
                        // Récupérer le nom du candidat pour l'affichage
                        $sql_candidat = "SELECT prenom, nom, email FROM utilisateurs WHERE id = ?";
                        $stmt_candidat = $conn->prepare($sql_candidat);
                        $stmt_candidat->execute([$filtre_candidat]);
                        $candidat_info = $stmt_candidat->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <div class="alert alert-info">
                            <strong>📄 Filtrage par candidat :</strong>
                            <?php echo htmlspecialchars($candidat_info['prenom'] . ' ' . $candidat_info['nom']); ?>
                            (<?php echo htmlspecialchars($candidat_info['email']); ?>)
                            <br>
                            <small>Vous pouvez supprimer les copies de ce candidat pour permettre sa suppression du système.</small>
                        </div>
                    <?php endif; ?>

                    <!-- Statistiques rapides -->
                    <section class="stats-overview">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Total copies</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['en_attente']; ?></div>
                            <div class="stat-label">En attente</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['en_correction']; ?></div>
                            <div class="stat-label">En correction</div>
                        </div>
                        <div class="stat-card <?php echo $stats['correction_soumise'] > 0 ? 'stat-alert' : ''; ?>">
                            <div class="stat-number"><?php echo $stats['correction_soumise']; ?></div>
                            <div class="stat-label">En attente validation</div>
                            <?php if ($stats['correction_soumise'] > 0): ?>
                            <a href="<?php echo APP_URL; ?>/admin/valider_corrections.php" class="stat-action">Valider →</a>
                            <?php endif; ?>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['corrigees']; ?></div>
                            <div class="stat-label">Corrigées</div>
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
                                    <option value="en_attente" <?php echo ($filtre_statut == 'en_attente') ? 'selected' : ''; ?>>En attente</option>
                                    <option value="en_correction" <?php echo ($filtre_statut == 'en_correction') ? 'selected' : ''; ?>>En correction</option>
                                    <option value="corrigee" <?php echo ($filtre_statut == 'corrigee') ? 'selected' : ''; ?>>Corrigée</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="correcteur">Correcteur :</label>
                                <select name="correcteur" id="correcteur">
                                    <option value="">Tous les correcteurs</option>
                                    <option value="non_attribue" <?php echo ($filtre_correcteur == 'non_attribue') ? 'selected' : ''; ?>>Non attribuées</option>
                                    <?php foreach ($correcteurs_list as $correcteur): ?>
                                        <option value="<?php echo $correcteur['id']; ?>"
                                                <?php echo ($filtre_correcteur == $correcteur['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($correcteur['prenom'] . ' ' . $correcteur['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                <a href="admin/attribuer_copies.php" class="btn btn-secondary">Réinitialiser</a>
                            </div>
                        </form>
                    </section>

                    <!-- Tableau des copies -->
                    <section class="copies-table">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Identifiant</th>
                                        <th>Concours</th>
                                        <th>Candidat</th>
                                        <th>Date dépôt</th>
                                        <th>Statut</th>
                                        <th>Correcteur</th>
                                        <th>Note</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($copies)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                                Aucune copie trouvée avec les filtres sélectionnés.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($copies as $copie): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($copie['concours_titre']); ?></td>
                                            <td>
                                                <?php if ($copie['candidat_nom']): ?>
                                                    <?php echo htmlspecialchars($copie['candidat_prenom'] . ' ' . $copie['candidat_nom']); ?>
                                                    <br><small><?php echo htmlspecialchars($copie['candidat_email']); ?></small>
                                                <?php else: ?>
                                                    <em>Candidat supprimé</em>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $copie['statut']; ?>">
                                                    <?php
                                                    switch($copie['statut']) {
                                                        case 'en_attente': echo '⏳ En attente'; break;
                                                        case 'en_correction': echo '📝 En correction'; break;
                                                        case 'corrigee': echo '✅ Corrigée'; break;
                                                        default: echo $copie['statut'];
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($copie['correcteur_nom']): ?>
                                                    <?php echo htmlspecialchars($copie['correcteur_prenom'] . ' ' . $copie['correcteur_nom']); ?>
                                                    <?php if ($copie['date_attribution']): ?>
                                                        <br><small>Attribué le <?php echo date('d/m/Y', strtotime($copie['date_attribution'])); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <em>Non attribuée</em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($copie['note_finale']): ?>
                                                    <strong><?php echo number_format($copie['note_finale'], 1); ?>/20</strong>
                                                    <?php if ($copie['date_correction']): ?>
                                                        <br><small>Corrigé le <?php echo date('d/m/Y', strtotime($copie['date_correction'])); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <em>Non notée</em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($copie['statut'] !== 'corrigee'): ?>
                                                        <!-- Attribution/Réattribution -->
                                                        <form method="POST" class="attribution-form">
                                                            <input type="hidden" name="action" value="attribuer">
                                                            <input type="hidden" name="copie_id" value="<?php echo $copie['id']; ?>">
                                                            <select name="correcteur_id" required>
                                                                <option value="">Choisir...</option>
                                                                <?php foreach ($correcteurs_list as $correcteur): ?>
                                                                    <option value="<?php echo $correcteur['id']; ?>"
                                                                            <?php echo ($copie['correcteur_id'] == $correcteur['id']) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($correcteur['prenom'] . ' ' . $correcteur['nom']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" class="btn-small btn-primary">
                                                                <?php echo $copie['correcteur_id'] ? 'Réattribuer' : 'Attribuer'; ?>
                                                            </button>
                                                        </form>

                                                        <?php if ($copie['correcteur_id']): ?>
                                                            <!-- Retirer attribution -->
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="retirer_attribution">
                                                                <input type="hidden" name="copie_id" value="<?php echo $copie['id']; ?>">
                                                                <button type="submit" class="btn-small btn-danger"
                                                                        onclick="return confirm('Êtes-vous sûr de vouloir retirer cette attribution ?')">
                                                                    Retirer
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <!-- Voir la copie -->
                                                    <a href="<?php echo APP_URL; ?>/admin/copies/voir.php?id=<?php echo $copie['id']; ?>" class="btn-small btn-success">
                                                        👁️ Voir
                                                    </a>

                                                    <!-- Supprimer la copie -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="supprimer_copie">
                                                        <input type="hidden" name="copie_id" value="<?php echo $copie['id']; ?>">
                                                        <button type="submit" class="btn-small btn-danger"
                                                                onclick="return confirmerSuppressionCopie('<?php echo htmlspecialchars($copie['identifiant_anonyme']); ?>', '<?php echo htmlspecialchars($copie['concours_titre']); ?>', '<?php echo htmlspecialchars($copie['candidat_prenom'] . ' ' . $copie['candidat_nom']); ?>')">
                                                            🗑️ Supprimer
                                                        </button>
                                                    </form>
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
        document.querySelectorAll('#concours, #statut, #correcteur').forEach(function(select) {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Fonction de confirmation pour la suppression de copies
        function confirmerSuppressionCopie(identifiant, concours, candidat) {
            return confirm('⚠️ ATTENTION - SUPPRESSION DÉFINITIVE ⚠️\n\n' +
                          'Vous êtes sur le point de supprimer la copie :\n\n' +
                          '• Identifiant : ' + identifiant + '\n' +
                          '• Concours : ' + concours + '\n' +
                          '• Candidat : ' + candidat + '\n\n' +
                          'Cette action supprimera DÉFINITIVEMENT :\n' +
                          '✗ La copie et son fichier\n' +
                          '✗ Toutes les corrections associées\n' +
                          '✗ Toutes les attributions\n' +
                          '✗ Toutes les données liées\n\n' +
                          'Cette action est IRRÉVERSIBLE !\n\n' +
                          'Tapez "SUPPRIMER" pour confirmer ou cliquez sur Annuler.');
        }

        // Confirmation pour les actions sensibles
        document.querySelectorAll('form[method="POST"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const action = form.querySelector('input[name="action"]').value;
                if (action === 'retirer_attribution') {
                    if (!confirm('Êtes-vous sûr de vouloir retirer cette attribution ?')) {
                        e.preventDefault();
                    }
                }
            });
        });


    </script>


</body>
</html>