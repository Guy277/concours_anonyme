<?php
/**
 * Page de visualisation d'une copie pour les administrateurs
 * Permet de voir le contenu d'une copie et ses détails
 */

session_start();
require_once '../../includes/config.php';
require_once '../../includes/anonymisation.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Récupération de l'ID de la copie
$copie_id = (int)($_GET['id'] ?? 0);

if ($copie_id <= 0) {
    header('Location: ' . APP_URL . '/admin/attribuer_copies.php?error=copie_invalide');
    exit();
}

// Récupération des détails de la copie
try {
    // Récupération des informations de base de la copie
    $sql = "SELECT
                cp.id,
                cp.identifiant_anonyme,
                cp.fichier_path,
                cp.date_depot,
                cp.statut,
                co.titre as concours_titre,
                co.description as concours_description,
                CONCAT(u_candidat.prenom, ' ', u_candidat.nom) as candidat_nom,
                u_candidat.email as candidat_email,
                CONCAT(u_correcteur.prenom, ' ', u_correcteur.nom) as correcteur_nom,
                u_correcteur.email as correcteur_email,
                cor.date_correction,
                JSON_UNQUOTE(JSON_EXTRACT(cor.evaluation_data_json, '$.note_totale')) as note_finale,
                JSON_UNQUOTE(JSON_EXTRACT(cor.evaluation_data_json, '$.commentaire_general')) as commentaire_general
            FROM copies cp
            INNER JOIN concours co ON cp.concours_id = co.id
            INNER JOIN utilisateurs u_candidat ON cp.candidat_id = u_candidat.id
            LEFT JOIN corrections cor ON cp.id = cor.copie_id
            LEFT JOIN utilisateurs u_correcteur ON cor.correcteur_id = u_correcteur.id
            WHERE cp.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$copie_id]);
    $copie = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$copie) {
        header('Location: ' . APP_URL . '/admin/attribuer_copies.php?error=copie_introuvable');
        exit();
    }

    // Utilisation du système d'anonymisation pour déchiffrer le chemin du fichier
    $anonymisation = new Anonymisation($conn);
    $fichier_path_dechiffre = $anonymisation->dechiffrerChemin($copie['fichier_path']);

    if ($fichier_path_dechiffre) {
        $copie['fichier_path_dechiffre'] = $fichier_path_dechiffre;
        $copie['nom_fichier'] = basename($fichier_path_dechiffre);
    } else {
        $copie['fichier_path_dechiffre'] = null;
        $copie['nom_fichier'] = 'Fichier non accessible';
    }

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération de la copie : " . $e->getMessage();
    $copie = null;
}

// Fonction pour déterminer le statut en français
function getStatutFrancais($statut) {
    switch ($statut) {
        case 'en_attente': return '⏳ En attente';
        case 'en_correction': return '📝 En correction';
        case 'correction_soumise': return '📋 Correction soumise';
        case 'corrigee': return '✅ Corrigée';
        default: return $statut;
    }
}

// Fonction pour déterminer la couleur du statut
function getStatutClass($statut) {
    switch ($statut) {
        case 'en_attente': return 'statut-attente';
        case 'en_correction': return 'statut-correction';
        case 'correction_soumise': return 'statut-soumise';
        case 'corrigee': return 'statut-corrigee';
        default: return '';
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisation copie - Concours Anonyme</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <div class="page-header">
                        <h1>👁️ Visualisation de la copie</h1>
                        <div class="header-actions">
                            <a href="<?php echo APP_URL; ?>/admin/attribuer_copies.php" class="btn btn-secondary">← Retour à la gestion</a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($copie): ?>
                    
                    <!-- Informations de la copie -->
                    <section class="copie-details">
                        <div class="details-grid">
                            <!-- Informations générales -->
                            <div class="detail-card">
                                <h3>📋 Informations générales</h3>
                                <div class="detail-row">
                                    <span class="detail-label">Identifiant anonyme :</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Concours :</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($copie['concours_titre']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Date de dépôt :</span>
                                    <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Statut :</span>
                                    <span class="detail-value <?php echo getStatutClass($copie['statut']); ?>">
                                        <?php echo getStatutFrancais($copie['statut']); ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Fichier :</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($copie['nom_fichier']); ?></span>
                                </div>
                            </div>
                            
                            <!-- Informations candidat -->
                            <div class="detail-card">
                                <h3>👤 Candidat</h3>
                                <div class="detail-row">
                                    <span class="detail-label">Nom :</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($copie['candidat_nom']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email :</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($copie['candidat_email']); ?></span>
                                </div>
                            </div>
                            
                            <!-- Informations correction -->
                            <div class="detail-card">
                                <h3>📝 Correction</h3>
                                <?php if ($copie['correcteur_nom']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Correcteur :</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($copie['correcteur_nom']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email correcteur :</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($copie['correcteur_email']); ?></span>
                                </div>
                                <?php if ($copie['date_correction']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Date correction :</span>
                                    <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($copie['date_correction'])); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($copie['note_finale']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Note finale :</span>
                                    <span class="detail-value note-finale"><?php echo $copie['note_finale']; ?>/20</span>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <p class="no-correction">Aucun correcteur attribué</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Commentaire général -->
                    <?php if ($copie['commentaire_general']): ?>
                    <section class="commentaire-section">
                        <h3>💬 Commentaire du correcteur</h3>
                        <div class="commentaire-content">
                            <?php echo nl2br(htmlspecialchars($copie['commentaire_general'])); ?>
                        </div>
                    </section>
                    <?php endif; ?>
                    
                    <!-- Visualisation du fichier -->
                    <section class="fichier-section">
                        <h3>📄 Fichier de la copie</h3>
                        <div class="fichier-info">
                            <div class="fichier-details">
                                <span class="fichier-nom"><?php echo htmlspecialchars($copie['nom_fichier']); ?></span>
                                <span class="fichier-taille">
                                    <?php
                                    if ($copie['fichier_path_dechiffre'] && file_exists($copie['fichier_path_dechiffre'])) {
                                        echo number_format(filesize($copie['fichier_path_dechiffre']) / 1024, 1) . ' KB';
                                    } else {
                                        echo 'Fichier introuvable';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="fichier-actions">
                                <?php if ($copie['fichier_path_dechiffre'] && file_exists($copie['fichier_path_dechiffre'])): ?>
                                <a href="<?php echo APP_URL; ?>/admin/telecharger_copie.php?id=<?php echo $copie['id']; ?>&action=download"
                                   target="_blank" class="btn btn-primary">
                                    📥 Télécharger
                                </a>
                                <?php if (strtolower(pathinfo($copie['nom_fichier'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                                <a href="<?php echo APP_URL; ?>/admin/telecharger_copie.php?id=<?php echo $copie['id']; ?>&action=view"
                                   target="_blank" class="btn btn-info">
                                    👁️ Prévisualiser
                                </a>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="fichier-erreur">❌ Fichier introuvable</span>
                                <?php endif; ?>
                            </div>
                        </div>

                    </section>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/main.js"></script>
    

</body>
</html>
