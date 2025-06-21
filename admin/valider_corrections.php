<?php
/**
 * Interface de validation des corrections pour les administrateurs
 * Permet de valider, rejeter ou demander des modifications aux corrections soumises
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/notifications.php';
require_once '../includes/note_calculator.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Initialiser le gestionnaire de notifications
$notificationManager = new NotificationManager($conn);

// Traitement des actions de validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $correction_id = (int)($_POST['correction_id'] ?? 0);
    $commentaire_admin = trim($_POST['commentaire_admin'] ?? '');
    
    if ($correction_id > 0) {
        try {
            $conn->beginTransaction();
            
            switch ($action) {
                case 'valider':
                    // Récupérer les informations de la correction et du candidat - CORRIGÉ
                    $sql = "SELECT c.*, cp.candidat_id, cp.identifiant_anonyme, co.titre as concours_titre
                            FROM corrections c
                            INNER JOIN copies cp ON c.copie_id = cp.id
                            INNER JOIN concours co ON cp.concours_id = co.id
                            WHERE c.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$correction_id]);
                    $correction_data = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($correction_data) {
                        // Recalculer la note avec la classe unifiée
                        $evaluation_data = json_decode($correction_data['evaluation_data_json'], true);
                        $note_finale = NoteCalculator::calculerNoteFinale($evaluation_data);
                        
                        // Mettre à jour la note dans evaluation_data_json
                        $evaluation_data = NoteCalculator::mettreAJourNote($evaluation_data);
                        $evaluation_data_json_mise_a_jour = json_encode($evaluation_data);
                        
                        // Valider la correction
                        $sql = "UPDATE copies SET statut = 'corrigee' WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$correction_data['copie_id']]);

                        // Mettre à jour la correction avec la note recalculée
                        $sql = "UPDATE corrections SET evaluation_data_json = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$evaluation_data_json_mise_a_jour, $correction_id]);

                        // Ajouter un commentaire admin si fourni
                        if ($commentaire_admin) {
                            $sql = "UPDATE corrections SET commentaire_admin = ?, date_validation = NOW(), validee_par = ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$commentaire_admin, $user_id, $correction_id]);
                        } else {
                            $sql = "UPDATE corrections SET date_validation = NOW(), validee_par = ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$user_id, $correction_id]);
                        }

                        // Notifier le candidat que sa copie est corrigée
                        $message_candidat = "Votre copie pour le concours \"{$correction_data['concours_titre']}\" a été corrigée. Note obtenue : {$note_finale}/20";
                        if ($commentaire_admin) {
                            $message_candidat .= "\n\nCommentaire de l'administrateur : " . $commentaire_admin;
                        }

                        $notificationManager->addNotification(
                            $correction_data['candidat_id'],
                            'copie_corrigee',
                            $message_candidat,
                            [
                                'copie_id' => $correction_data['copie_id'],
                                'identifiant_anonyme' => $correction_data['identifiant_anonyme'],
                                'note_finale' => $note_finale,
                                'concours_titre' => $correction_data['concours_titre']
                            ]
                        );

                        $success = "Correction validée avec succès. Le candidat a été notifié.";
                    } else {
                        $error = "Correction introuvable.";
                    }
                    break;
                    
                case 'rejeter':
                    if (!$commentaire_admin) {
                        $error = "Un commentaire est requis pour rejeter une correction.";
                        break;
                    }

                    // Récupérer les données de la correction avant suppression - CORRIGÉ
                    $sql = "SELECT c.*, cp.id as copie_id, c.correcteur_id, c.evaluation_data_json
                            FROM corrections c
                            INNER JOIN copies cp ON c.copie_id = cp.id
                            WHERE c.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$correction_id]);
                    $correction_data = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($correction_data) {
                        // Recalculer la note avec la classe unifiée
                        $evaluation_data = json_decode($correction_data['evaluation_data_json'], true);
                        $note_finale = NoteCalculator::calculerNoteFinale($evaluation_data);
                        
                        // Sauvegarder dans l'historique des rejets
                        $sql = "INSERT INTO rejets_corrections (copie_id, correcteur_id, admin_id, commentaire_rejet, correction_data_json, note_rejetee)
                                VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $correction_data['copie_id'],
                            $correction_data['correcteur_id'],
                            $user_id,
                            $commentaire_admin,
                            $correction_data['evaluation_data_json'],
                            $note_finale
                        ]);

                        // Ajouter notification au correcteur
                        $notification = [
                            'type' => 'correction_rejetee',
                            'message' => 'Votre correction a été rejetée par l\'administrateur',
                            'commentaire_admin' => $commentaire_admin,
                            'copie_id' => $correction_data['copie_id'],
                            'date' => date('Y-m-d H:i:s'),
                            'lu' => false
                        ];

                        // Récupérer les notifications existantes du correcteur
                        $sql = "SELECT notifications_json FROM utilisateurs WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$correction_data['correcteur_id']]);
                        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

                        $notifications = [];
                        if ($user_data && $user_data['notifications_json']) {
                            $notifications = json_decode($user_data['notifications_json'], true) ?: [];
                        }

                        // Ajouter la nouvelle notification
                        $notifications[] = $notification;

                        // Garder seulement les 10 dernières notifications
                        $notifications = array_slice($notifications, -10);

                        // Mettre à jour les notifications
                        $sql = "UPDATE utilisateurs SET notifications_json = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([json_encode($notifications), $correction_data['correcteur_id']]);

                        // Remettre la copie en correction
                        $sql = "UPDATE copies SET statut = 'en_correction' WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$correction_data['copie_id']]);

                        // Supprimer la correction pour permettre une nouvelle soumission
                        $sql = "DELETE FROM corrections WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$correction_id]);

                        $success = "Correction rejetée avec succès. Le correcteur a été notifié et peut soumettre une nouvelle correction.";
                    } else {
                        $error = "Correction introuvable.";
                    }
                    break;
                    
                case 'demander_modification':
                    // Demander une modification
                    if (!$commentaire_admin) {
                        $error = "Un commentaire est requis pour demander une modification.";
                    } else {
                        $sql = "UPDATE corrections SET commentaire_admin = ?, statut_validation = 'modification_demandee' WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$commentaire_admin, $correction_id]);
                        
                        $success = "Demande de modification envoyée au correcteur.";
                    }
                    break;
            }
            
            if (!$error) {
                $conn->commit();
            } else {
                $conn->rollBack();
            }
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Erreur lors du traitement : " . $e->getMessage();
        }
    }
}

// Récupération des corrections en attente de validation - CORRIGÉ
try {
    $sql = "SELECT 
                c.id as correction_id,
                c.date_correction,
                c.evaluation_data_json,
                JSON_UNQUOTE(JSON_EXTRACT(c.evaluation_data_json, '$.commentaire_general')) as commentaire_general,
                cp.identifiant_anonyme,
                cp.date_depot,
                co.titre as concours_titre,
                CONCAT(u_correcteur.prenom, ' ', u_correcteur.nom) as correcteur_nom,
                u_correcteur.email as correcteur_email,
                CONCAT(u_candidat.prenom, ' ', u_candidat.nom) as candidat_nom,
                u_candidat.email as candidat_email
            FROM corrections c
            INNER JOIN copies cp ON c.copie_id = cp.id
            INNER JOIN concours co ON cp.concours_id = co.id
            INNER JOIN utilisateurs u_correcteur ON c.correcteur_id = u_correcteur.id
            INNER JOIN utilisateurs u_candidat ON cp.candidat_id = u_candidat.id
            INNER JOIN (
                SELECT copie_id, correcteur_id, MAX(id) as max_correction_id
                FROM corrections
                GROUP BY copie_id, correcteur_id
            ) latest_corrections ON c.copie_id = latest_corrections.copie_id 
                AND c.correcteur_id = latest_corrections.correcteur_id 
                AND c.id = latest_corrections.max_correction_id
            WHERE cp.statut = 'correction_soumise'
            ORDER BY c.date_correction ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $corrections_en_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recalculer les notes avec la classe unifiée
    foreach ($corrections_en_attente as &$correction) {
        if ($correction['evaluation_data_json']) {
            $evaluation_data = json_decode($correction['evaluation_data_json'], true);
            $correction['note_finale'] = NoteCalculator::calculerNoteFinale($evaluation_data);
        } else {
            $correction['note_finale'] = 0;
        }
    }
    
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des corrections : " . $e->getMessage();
    $corrections_en_attente = [];
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation des corrections - Concours Anonyme</title>
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
                        <h1>✅ Validation des corrections</h1>
                        <div class="header-actions">
                            <a href="<?php echo APP_URL; ?>/dashboard/admin.php" class="btn btn-secondary">← Retour au dashboard</a>
                            <span class="badge"><?php echo count($corrections_en_attente); ?> en attente</span>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <section class="validation-section">
                        <?php if (empty($corrections_en_attente)): ?>
                            <div class="empty-state">
                                <h3>🎉 Aucune correction en attente</h3>
                                <p>Toutes les corrections ont été validées ou aucune correction n'a été soumise.</p>
                            </div>
                        <?php else: ?>
                            <div class="corrections-grid">
                                <?php foreach ($corrections_en_attente as $correction): ?>
                                <div class="correction-card">
                                    <div class="correction-header">
                                        <h3>📝 <?php echo htmlspecialchars($correction['concours_titre']); ?></h3>
                                        <span class="correction-date"><?php echo date('d/m/Y H:i', strtotime($correction['date_correction'])); ?></span>
                                    </div>
                                    
                                    <div class="correction-info">
                                        <div class="info-row">
                                            <strong>ID Anonyme :</strong>
                                            <span><?php echo htmlspecialchars($correction['identifiant_anonyme']); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <strong>Correcteur :</strong>
                                            <span><?php echo htmlspecialchars($correction['correcteur_nom']); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <strong>Note finale :</strong>
                                            <span class="note-finale"><?php echo $correction['note_finale']; ?>/20</span>
                                        </div>
                                        <div class="info-row">
                                            <strong>Candidat :</strong>
                                            <span><?php echo htmlspecialchars($correction['candidat_nom']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($correction['commentaire_general']): ?>
                                    <div class="commentaire-section">
                                        <strong>Commentaire du correcteur :</strong>
                                        <p><?php echo nl2br(htmlspecialchars($correction['commentaire_general'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="validation-actions">
                                        <form method="POST" class="validation-form">
                                            <input type="hidden" name="correction_id" value="<?php echo $correction['correction_id']; ?>">
                                            
                                            <div class="form-group">
                                                <label for="commentaire_admin_<?php echo $correction['correction_id']; ?>">
                                                    Commentaire administrateur :
                                                    <span class="required-note">(Obligatoire pour rejet et modification)</span>
                                                </label>
                                                <textarea name="commentaire_admin" id="commentaire_admin_<?php echo $correction['correction_id']; ?>"
                                                          rows="3" placeholder="Commentaire pour le correcteur ou le candidat..."></textarea>
                                            </div>
                                            
                                            <div class="action-buttons">
                                                <button type="submit" name="action" value="valider" class="btn btn-success">
                                                    ✅ Valider
                                                </button>
                                                <button type="submit" name="action" value="demander_modification" class="btn btn-warning">
                                                    🔄 Demander modification
                                                </button>
                                                <button type="submit" name="action" value="rejeter" class="btn btn-danger"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir rejeter cette correction ? Elle sera supprimée et le correcteur devra recommencer.');">
                                                    ❌ Rejeter
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>
