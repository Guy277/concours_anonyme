<?php
/**
 * Interface de modification des copies
 * Permet aux candidats de modifier leurs copies avant correction
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/anonymisation.php';
require_once '../includes/submission_rules.php';

// Vérification de l'authentification et du rôle candidat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidat') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Vérification de l'ID de la copie
if (!isset($_GET['copie_id'])) {
    $_SESSION['error'] = "Copie introuvable.";
    header('Location: mes_copies.php');
    exit();
}

$copie_id = (int)$_GET['copie_id'];

// Récupération des informations de la copie
$sql = "SELECT 
            c.*,
            co.titre as concours_titre,
            co.date_fin,
            co.description
        FROM copies c
        INNER JOIN concours co ON c.concours_id = co.id
        WHERE c.id = ? AND c.candidat_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$copie_id, $user_id]);
$copie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$copie) {
    $_SESSION['error'] = "Copie introuvable ou vous n'avez pas l'autorisation de la modifier.";
    header('Location: mes_copies.php');
    exit();
}

// Vérifier si la copie peut être modifiée selon les règles de soumission
$submissionRules = new SubmissionRules($conn);
$canModify = $submissionRules->canModify($copie_id, $user_id);

if (!$canModify['allowed']) {
    $_SESSION['error'] = $canModify['message'];
    header('Location: mes_copies.php');
    exit();
}

// Obtenir les informations de statut pour l'affichage
$submissionStatus = $canModify['status'];

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du fichier
    if (!isset($_FILES['nouvelle_copie']) || $_FILES['nouvelle_copie']['error'] !== UPLOAD_ERR_OK) {
        $error = "Erreur lors du téléchargement du nouveau fichier.";
    } else {
        $file = $_FILES['nouvelle_copie'];
        $allowed_types = ['application/pdf', 'application/zip'];
        
        // Vérification renforcée du type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_types)) {
            $error = "Format de fichier non autorisé. Formats acceptés : PDF, ZIP.";
        } else {
            // Création du dossier de stockage si nécessaire
            $upload_dir = dirname(__DIR__) . "/uploads/copies/" . $copie['concours_id'];
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Génération d'un nom de fichier unique
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $nouveau_filepath = $upload_dir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $nouveau_filepath)) {
                try {
                    // Déchiffrer l'ancien chemin pour supprimer l'ancien fichier
                    $anonymisation = new Anonymisation($conn);
                    $ancien_filepath = $anonymisation->decrypt($copie['fichier_path']);
                    
                    // Chiffrer le nouveau chemin
                    $nouveau_filepath_chiffre = $anonymisation->encrypt($nouveau_filepath);
                    
                    // Mettre à jour la base de données
                    $sql = "UPDATE copies SET fichier_path = ?, date_depot = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);

                    if ($stmt->execute([$nouveau_filepath_chiffre, $copie_id])) {
                        // Enregistrer la modification dans l'historique
                        $submissionRules->logModification(
                            $copie_id,
                            $user_id,
                            $copie['fichier_path'],
                            $nouveau_filepath_chiffre,
                            $submissionStatus['phase'] === 'GRACE' ? 'Modification en délai de grâce' : 'Modification normale'
                        );

                        // Supprimer l'ancien fichier
                        if (file_exists($ancien_filepath)) {
                            unlink($ancien_filepath);
                        }

                        // Log de l'action
                        $anonymisation->logAudit($user_id, 'Modification Copie', "Copie ID: {$copie_id}, Identifiant: {$copie['identifiant_anonyme']}, Phase: {$submissionStatus['phase']}");

                        $_SESSION['success'] = "Votre copie a été modifiée avec succès.";
                        header('Location: mes_copies.php');
                        exit();
                    } else {
                        $error = "Erreur lors de la mise à jour de la copie.";
                        // Supprimer le nouveau fichier en cas d'erreur
                        unlink($nouveau_filepath);
                    }
                } catch (Exception $e) {
                    $error = "Erreur lors du traitement : " . $e->getMessage();
                    // Supprimer le nouveau fichier en cas d'erreur
                    unlink($nouveau_filepath);
                }
            } else {
                $error = "Erreur lors du déplacement du fichier.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier ma copie - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <div class="breadcrumb">
                        <a href="mes_copies.php" class="btn btn-secondary">← Retour à mes copies</a>
                    </div>
                    
                    <h1>✏️ Modifier ma copie</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <!-- Informations de la copie -->
                    <section class="dashboard-section">
                        <h2>Informations de la copie</h2>
                        <div class="copie-info">
                            <div class="info-row">
                                <strong>Concours :</strong>
                                <span><?php echo htmlspecialchars($copie['concours_titre']); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Identifiant anonyme :</strong>
                                <span class="identifiant-anonyme"><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Date de dépôt actuelle :</strong>
                                <span><?php echo date('d/m/Y à H:i', strtotime($copie['date_depot'])); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Date limite :</strong>
                                <span><?php echo date('d/m/Y à H:i', strtotime($copie['date_fin'])); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Statut :</strong>
                                <span class="status-badge status-pending">En attente</span>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Avertissement selon le statut -->
                    <section class="dashboard-section">
                        <?php
                        $warning = $submissionRules->getModificationWarning($copie_id);
                        $alertClass = 'alert-' . $warning['type'];
                        ?>
                        <div class="alert <?php echo $alertClass; ?>">
                            <h3><?php echo $warning['title']; ?></h3>
                            <p><?php echo $warning['message']; ?></p>

                            <?php if ($submissionStatus['phase'] === 'SUBMISSION'): ?>
                            <ul>
                                <li>Le remplacement de votre copie supprimera définitivement l'ancienne version</li>
                                <li>Cette action ne peut pas être annulée</li>
                                <li>Vous pouvez modifier librement jusqu'à la date limite</li>
                                <li>La date de dépôt sera mise à jour avec l'heure actuelle</li>
                            </ul>
                            <?php elseif ($submissionStatus['phase'] === 'GRACE'): ?>
                            <ul>
                                <li><strong>DÉLAI DE GRÂCE ACTIF :</strong> Vous ne pouvez modifier qu'UNE SEULE FOIS</li>
                                <li>Modifications restantes : <?php echo $submissionStatus['modifications_remaining']; ?></li>
                                <li>Cette modification sera votre dernière chance</li>
                                <li>Réfléchissez bien avant de confirmer</li>
                            </ul>
                            <?php endif; ?>

                            <?php if ($warning['show_countdown'] && isset($submissionStatus['deadline'])): ?>
                            <div class="countdown-info">
                                <?php if ($submissionStatus['phase'] === 'SUBMISSION'): ?>
                                    <p><strong>Temps restant :</strong>
                                    <?php
                                    $interval = $submissionStatus['time_remaining'];
                                    if ($interval->days > 0) {
                                        echo "{$interval->days} jour(s) {$interval->h}h {$interval->i}min";
                                    } elseif ($interval->h > 0) {
                                        echo "{$interval->h}h {$interval->i}min";
                                    } else {
                                        echo "{$interval->i} minutes";
                                    }
                                    ?>
                                    </p>
                                <?php elseif ($submissionStatus['phase'] === 'GRACE'): ?>
                                    <p><strong>Délai de grâce expire dans :</strong>
                                    <?php
                                    $now = new DateTime();
                                    $grace_remaining = $submissionStatus['grace_end']->diff($now);
                                    if ($grace_remaining->h > 0) {
                                        echo "{$grace_remaining->h}h {$grace_remaining->i}min";
                                    } else {
                                        echo "{$grace_remaining->i} minutes";
                                    }
                                    ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    
                    <!-- Formulaire de modification -->
                    <section class="form-section">
                        <h2>Remplacer ma copie</h2>
                        
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <div class="form-group">
                                <label for="nouvelle_copie">Sélectionnez votre nouvelle copie :</label>
                                <input type="file" id="nouvelle_copie" name="nouvelle_copie" accept=".pdf,.zip" required>
                                <small>Formats acceptés : PDF, ZIP (max 10MB)</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Êtes-vous sûr de vouloir remplacer votre copie ? Cette action est irréversible.')">
                                    Remplacer ma copie
                                </button>
                                <a href="mes_copies.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </section>
                    
                    <!-- Copie actuelle -->
                    <section class="dashboard-section">
                        <h2>Copie actuelle</h2>
                        <div class="copie-preview">
                            <p>Vous pouvez consulter votre copie actuelle avant de la remplacer :</p>
                            <a href="copies/voir.php?id=<?php echo $copie['id']; ?>" class="btn btn-secondary" target="_blank">
                                Voir ma copie actuelle
                            </a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
    <script>
        // Validation côté client
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.upload-form');
            const fileInput = document.getElementById('nouvelle_copie');
            
            form.addEventListener('submit', function(e) {
                if (!fileInput.files.length) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un fichier.');
                    return false;
                }
                
                const file = fileInput.files[0];
                const maxSize = 10 * 1024 * 1024; // 10MB
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('Le fichier est trop volumineux. Taille maximale : 10MB');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
