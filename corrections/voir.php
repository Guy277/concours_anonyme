<?php
/**
 * Page de visualisation d'une correction
 * Permet aux administrateurs et correcteurs de voir les détails d'une correction
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/anonymisation.php';
require_once '../includes/note_calculator.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$error = null;
$correction = null;

// Vérification de l'ID de la correction
if (!isset($_GET['id'])) {
    header('Location: liste.php');
    exit();
}

$correction_id = (int)$_GET['id'];

// Récupération de la correction

$sql = "SELECT c.*, cp.identifiant_anonyme, cp.fichier_path as copie_fichier, co.titre as concours_titre,
        CONCAT(u.prenom, ' ', u.nom, ' (', u.email, ')') as correcteur_username
        FROM corrections c
        INNER JOIN copies cp ON c.copie_id = cp.id
        INNER JOIN concours co ON cp.concours_id = co.id
        INNER JOIN utilisateurs u ON c.correcteur_id = u.id
        WHERE c.id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$correction_id]);
$correction = $stmt->fetch();

if (!$correction) {
    $error = "Correction introuvable.";
} else {
    // Vérification des droits d'accès
    if ($role === 'correcteur' && $correction['correcteur_id'] !== $user_id) {
        header('Location: liste.php'); // Un correcteur ne peut voir que ses propres corrections
        exit();
    }

    // Extraction des données d'évaluation depuis le JSON
    $evaluation_data = null;
    if (!empty($correction['evaluation_data_json'])) {
        $evaluation_data = json_decode($correction['evaluation_data_json'], true);

        // Utiliser la classe unifiée pour calculer la note
        if ($evaluation_data) {
            $correction['note_finale'] = NoteCalculator::calculerNoteFinale($evaluation_data);
            $correction['commentaire_final'] = $evaluation_data['commentaire_general'] ?? $correction['commentaire'];
        } else {
            $correction['note_finale'] = $correction['note'];
            $correction['commentaire_final'] = $correction['commentaire'];
        }
    } else {
        $correction['note_finale'] = $correction['note'];
        $correction['commentaire_final'] = $correction['commentaire'];
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la correction - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Détails de la correction</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php else: ?>


                        <section class="correction-details-section">
                            <div class="info-grid">
                                <div class="info-item">
                                    <h3>Concours</h3>
                                    <p><?php echo htmlspecialchars($correction['concours_titre']); ?></p>
                                </div>
                                <div class="info-item">
                                    <h3>ID Anonyme Copie</h3>
                                    <p><?php echo htmlspecialchars($correction['identifiant_anonyme']); ?></p>
                                </div>
                                <div class="info-item">
                                    <h3>Correcteur</h3>
                                    <p><?php echo htmlspecialchars($correction['correcteur_username']); ?></p>
                                </div>
                                <div class="info-item">
                                    <h3>Note</h3>
                                    <p><strong><?php echo htmlspecialchars($correction['note_finale'] ?? 'Non définie'); ?>/20</strong></p>
                                </div>
                                <div class="info-item full-width">
                                    <h3>Commentaire général</h3>
                                    <p><?php echo nl2br(htmlspecialchars($correction['commentaire_final'] ?? 'Aucun commentaire')); ?></p>
                                </div>

                                <?php if ($evaluation_data && isset($evaluation_data['criteres'])): ?>
                                <div class="info-item full-width">
                                    <h3>Détail par critères</h3>
                                    <div class="criteres-detail" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;">
                                        <?php foreach ($evaluation_data['criteres'] as $nom_critere => $critere): ?>
                                            <div class="critere-item" style="padding: 8px 12px; background: #f8f9fa; border-radius: 5px; border-left: 3px solid #007bff;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                                                    <strong style="text-transform: capitalize; font-size: 0.9em;"><?php echo htmlspecialchars($nom_critere); ?></strong>
                                                    <span style="background: #007bff; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; font-weight: bold;"><?php echo $critere['note']; ?>/<?php echo $critere['max']; ?></span>
                                                </div>
                                                <?php if (!empty($critere['commentaire'])): ?>
                                                    <div style="font-size: 0.85em; color: #666; line-height: 1.3;"><?php echo htmlspecialchars($critere['commentaire']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <h3>Date de correction</h3>
                                    <p><?php echo date('d/m/Y H:i', strtotime($correction['date_correction'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="copie-preview">
                                <h3>Copie corrigée</h3>
                                <?php
                                    // Déchiffrer le chemin du fichier
                                    $anonymisation = new Anonymisation($conn);
                                    $copie_path_dechiffre = $anonymisation->decrypt($correction['copie_fichier']);

                                    if ($copie_path_dechiffre && file_exists($copie_path_dechiffre)):
                                        $extension = strtolower(pathinfo($copie_path_dechiffre, PATHINFO_EXTENSION));
                                ?>
                                    <div class="fichier-info">
                                        <p><strong>Fichier :</strong> <?php echo htmlspecialchars(basename($copie_path_dechiffre)); ?></p>
                                        <p><strong>Type :</strong> <?php echo strtoupper($extension); ?></p>
                                    </div>

                                    <div class="fichier-actions" style="margin: 15px 0;">
                                        <a href="<?php echo APP_URL; ?>/corrections/telecharger_copie.php?copie_id=<?php echo $correction['copie_id']; ?>&action=view"
                                           target="_blank" class="btn btn-primary">
                                            👁️ Voir le fichier
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/corrections/telecharger_copie.php?copie_id=<?php echo $correction['copie_id']; ?>&action=download"
                                           class="btn btn-secondary">
                                            💾 Télécharger
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <p class="error">Le fichier de la copie n'est pas disponible.</p>
                                <?php endif; ?>
                            </div>
                        </section>
                        <div class="form-actions">
                            <a href="<?php echo APP_URL; ?>/corrections/mes_corrections.php" class="btn btn-secondary" style="background-color:rgb(183, 188, 191); color: white;">Retour à la liste des corrections</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>