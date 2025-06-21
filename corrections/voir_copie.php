<?php
/**
 * Page de consultation d'une copie pour les correcteurs
 * Permet aux correcteurs de visualiser une copie qui leur est attribuée
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/anonymisation.php';
require_once '../includes/note_calculator.php';

// Vérification de l'authentification et du rôle correcteur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'correcteur') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;

// Vérification du paramètre ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . APP_URL . '/dashboard/correcteur.php');
    exit();
}

$copie_id = (int)$_GET['id'];

// Vérification que le correcteur a accès à cette copie
try {
    // Récupération des informations de la copie
    $sql = "SELECT 
                cp.*,
                co.titre as concours_titre,
                ac.date_attribution,
                cr.id as correction_id,
                cr.evaluation_data_json,
                cr.date_correction
            FROM copies cp
            INNER JOIN concours co ON cp.concours_id = co.id
            LEFT JOIN attributions_copies ac ON cp.id = ac.copie_id
            LEFT JOIN corrections cr ON cp.id = cr.copie_id AND ac.correcteur_id = cr.correcteur_id
            WHERE cp.id = ? AND ac.correcteur_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$copie_id, $user_id]);
    $copie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$copie) {
        $error = "Vous n'avez pas l'autorisation de consulter cette copie ou elle n'existe pas.";
    } else {
        // Recalculer la note finale avec la classe unifiée
        if (!empty($copie['evaluation_data_json'])) {
            $evaluation_data = json_decode($copie['evaluation_data_json'], true);
            $copie['note_finale'] = NoteCalculator::calculerNoteFinale($evaluation_data);
        } else {
            $copie['note_finale'] = null;
        }

        // Utiliser le statut calculé si le statut original est vide
        if (empty($copie['statut']) && isset($copie['statut_reel'])) {
            $copie['statut'] = $copie['statut_reel'];
        }
    }
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération de la copie.";
}

// Récupération du fichier de la copie (anonymisé)
$anonymisation = new Anonymisation($conn);
$copie_anonyme = null;
$fichier_path = null;

if ($copie && !$error) {
    $copie_anonyme = $anonymisation->getCopieAnonyme($copie_id);
    $fichier_path = $copie_anonyme['fichier_path'] ?? null;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulter la copie - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <div class="page-header">
                        <h1>👁️ Consulter la copie</h1>
                        <div class="header-actions">
                            <a href="<?php echo APP_URL; ?>/dashboard/correcteur.php" class="btn btn-secondary">← Retour au dashboard</a>
                            <a href="<?php echo APP_URL; ?>/corrections/copies_a_corriger.php" class="btn btn-outline">📋 Copies à corriger</a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                        <div class="form-actions">
                            <a href="<?php echo APP_URL; ?>/dashboard/correcteur.php" class="btn">Retour au dashboard</a>
                        </div>
                    <?php else: ?>
                        
                        <!-- Informations de la copie -->
                        <section class="copie-info-section">
                            <div class="copie-card">
                                <h2>📄 Informations de la copie</h2>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <strong>Identifiant anonyme :</strong>
                                        <span class="identifiant-anonyme"><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Concours :</strong>
                                        <span><?php echo htmlspecialchars($copie['concours_titre']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Date de dépôt :</strong>
                                        <span><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Date d'attribution :</strong>
                                        <span><?php echo $copie['date_attribution'] ? date('d/m/Y H:i', strtotime($copie['date_attribution'])) : 'Non spécifiée'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Statut :</strong>
                                        <span class="status-badge status-<?php echo $copie['statut']; ?>">
                                            <?php
                                            $statuts = [
                                                'en_attente' => '⏳ En attente',
                                                'en_correction' => '📝 En correction',
                                                'correction_soumise' => '📤 Correction soumise',
                                                'corrigee' => '✅ Corrigée'
                                            ];
                                            echo $statuts[$copie['statut']] ?? ucfirst(str_replace('_', ' ', $copie['statut']));
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($copie['correction_id']): ?>
                                    <div class="info-item">
                                        <strong>Note finale :</strong>
                                        <span class="note-finale"><?php echo $copie['note_finale'] ? $copie['note_finale'] . '/20' : 'Non calculée'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Date de correction :</strong>
                                        <span><?php echo date('d/m/Y H:i', strtotime($copie['date_correction'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>

                        <!-- Fichier de la copie -->
                        <section class="fichier-section">
                            <div class="fichier-card">
                                <h2>📎 Fichier de la copie</h2>
                                <?php if ($fichier_path && file_exists($fichier_path)): ?>
                                    <div class="fichier-info">
                                        <p><strong>Fichier :</strong> <?php echo basename($fichier_path); ?></p>
                                        <p><strong>Taille :</strong> <?php echo round(filesize($fichier_path) / 1024, 2); ?> KB</p>
                                    </div>
                                    <div class="fichier-actions">
                                        <a href="<?php echo APP_URL; ?>/corrections/telecharger_copie.php?copie_id=<?php echo $copie['id']; ?>&action=download"
                                           class="btn btn-primary">
                                            📥 Télécharger la copie
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/corrections/telecharger_copie.php?copie_id=<?php echo $copie['id']; ?>&action=view"
                                           class="btn btn-outline" target="_blank">
                                            👁️ Ouvrir dans un nouvel onglet
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <p>⚠️ Fichier de copie non disponible ou introuvable.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Actions -->
                        <section class="actions-section">
                            <div class="actions-grid">
                                <?php if ($copie['statut'] !== 'corrigee'): ?>
                                <a href="<?php echo APP_URL; ?>/corrections/evaluer_moderne.php?copie_id=<?php echo $copie['id']; ?>" 
                                   class="action-card action-primary">
                                    <div class="action-icon">📝</div>
                                    <div class="action-content">
                                        <h3>Corriger cette copie</h3>
                                        <p>Commencer l'évaluation</p>
                                    </div>
                                </a>
                                <?php else: ?>
                                <div class="action-card action-success">
                                    <div class="action-icon">✅</div>
                                    <div class="action-content">
                                        <h3>Copie corrigée</h3>
                                        <p>Note : <?php echo $copie['note_finale'] ? $copie['note_finale'] . '/20' : 'Non calculée'; ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <a href="<?php echo APP_URL; ?>/corrections/copies_a_corriger.php" class="action-card">
                                    <div class="action-icon">📋</div>
                                    <div class="action-content">
                                        <h3>Autres copies</h3>
                                        <p>Voir toutes les copies à corriger</p>
                                    </div>
                                </a>
                                
                                <a href="<?php echo APP_URL; ?>/corrections/mes_corrections.php" class="action-card">
                                    <div class="action-icon">📊</div>
                                    <div class="action-content">
                                        <h3>Mes corrections</h3>
                                        <p>Historique de mes évaluations</p>
                                    </div>
                                </a>
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
