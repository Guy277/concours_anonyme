<?php
/**
 * Interface de dépôt de copies
 * Permet aux candidats de déposer leurs copies de manière anonyme
 */

// Démarrage de la session pour vérifier l'authentification
session_start();

// Inclusion du fichier de configuration de la base de données
// Chemins absolus pour plus de robustesse
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__));
}
require_once BASE_DIR . '/includes/config.php';
require_once BASE_DIR . '/includes/anonymisation.php';

// Vérification de l'authentification et du rôle candidat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidat') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$error = null;
$success = null;

// Vérification du concours
if (!isset($_GET['concours_id'])) {
    header('Location: ' . APP_URL . '/dashboard/candidat.php');
    exit();
}

$concours_id = (int)$_GET['concours_id'];
$user_id = $_SESSION['user_id'];

// Génération du token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérification que le concours existe et est actif
$sql = "SELECT * FROM concours WHERE id = :concours_id AND date_fin >= CURDATE()";
$stmt = $conn->prepare($sql);
$stmt->execute([':concours_id' => $concours_id]);
$concours = $stmt->fetch();

if (!$concours) {
    header('Location: ' . APP_URL . '/dashboard/candidat.php');
    exit();
}

// Gestion des phases de soumission
$now = new DateTime();
$date_debut = new DateTime($concours['date_debut']);
$date_fin = new DateTime($concours['date_fin']);

// Calcul des phases
$duree_totale = $date_fin->getTimestamp() - $date_debut->getTimestamp();
$duree_grace = $duree_totale * 0.1; // 10% de période de grâce
$date_fin_grace = clone $date_fin;
$date_fin_grace->add(new DateInterval('PT' . round($duree_grace) . 'S'));

$phase_actuelle = '';
$message_phase = '';
$peut_deposer = true;
$classe_phase = '';

if ($now < $date_debut) {
    $phase_actuelle = 'pas_ouvert';
    $message_phase = "🔒 Ce concours n'est pas encore ouvert. Ouverture le " . $date_debut->format('d/m/Y à H:i');
    $peut_deposer = false;
    $classe_phase = 'phase-fermee';
} elseif ($now <= $date_fin) {
    $phase_actuelle = 'normale';
    $message_phase = "✅ Période normale de soumission. Date limite : " . $date_fin->format('d/m/Y à H:i');
    $classe_phase = 'phase-normale';
} elseif ($now <= $date_fin_grace) {
    $phase_actuelle = 'grace';
    $message_phase = "⚠️ Période de grâce ! Soumission encore possible jusqu'au " . $date_fin_grace->format('d/m/Y à H:i');
    $classe_phase = 'phase-grace';
} else {
    $phase_actuelle = 'ferme';
    $message_phase = "❌ Concours fermé. Date limite dépassée le " . $date_fin_grace->format('d/m/Y à H:i');
    $peut_deposer = false;
    $classe_phase = 'phase-fermee';
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF (simple)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "Token de sécurité invalide. Veuillez recharger la page.";
    }
    // Vérification du fichier
    elseif (!isset($_FILES['copie']) || $_FILES['copie']['error'] !== UPLOAD_ERR_OK) {
        // Diagnostic détaillé de l'erreur
        if (!isset($_FILES['copie'])) {
            $error = "Aucun fichier n'a été sélectionné.";
        } else {
            switch ($_FILES['copie']['error']) {
                case UPLOAD_ERR_NO_FILE:
                    $error = "Aucun fichier n'a été sélectionné.";
                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = "Le fichier est trop volumineux.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = "Le fichier n'a été que partiellement téléchargé.";
                    break;
                default:
                    $error = "Erreur lors du téléchargement du fichier.";
            }
        }
    } else {
        $file = $_FILES['copie'];
        $allowed_types = ['application/pdf', 'application/zip'];
        $max_file_size = 10 * 1024 * 1024; // 10MB

        // Vérification de la taille
        if ($file['size'] > $max_file_size) {
            $error = "Le fichier est trop volumineux. Taille maximale : 10MB.";
        }
        // Vérification renforcée du type MIME
        else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            // Vérification de l'extension
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'zip'];

            if (!in_array($mime_type, $allowed_types) || !in_array($extension, $allowed_extensions)) {
                $error = "Format de fichier non autorisé. Formats acceptés : PDF, ZIP.";
            } else {
                // Placeholder pour l'intégration de ClamAV
                // if (class_exists('ClamAV') && !ClamAV::scan($file['tmp_name'])) {
                //     $error = "Le fichier contient un virus et ne peut pas être téléchargé.";
                //     unlink($file['tmp_name']); // Supprimer le fichier temporaire infecté
                //     return;
                // }

                // Placeholder pour la gestion des quotas utilisateur
                // $max_copies_per_user = 3; // Exemple de quota
                // $current_copies = $anonymisation->countUserCopies($user_id, $concours_id);
                // if ($current_copies >= $max_copies_per_user) {
                //     $error = "Vous avez atteint le nombre maximal de copies autorisées pour ce concours.";
                //     unlink($file['tmp_name']);
                //     return;
                // }

                // Création du dossier de stockage si nécessaire
                $upload_dir = BASE_DIR . "/uploads/copies/" . $concours_id;
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Génération d'un nom de fichier unique
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $extension;
                $filepath = $upload_dir . '/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Utilisation de la classe d'anonymisation
                    $result = (new Anonymisation($conn))->deposerCopie($concours_id, $user_id, $filepath);

                    if ($result['success']) {
                        $success = "Votre copie a été déposée avec succès. Identifiant anonyme : " . $result['identifiant_anonyme'];
                    } else {
                        $error = "Erreur lors de l'enregistrement de la copie : " . $result['error'];
                        // Suppression du fichier en cas d'erreur
                        unlink($filepath);
                    }
                } else {
                    $error = "Erreur lors du déplacement du fichier.";
                }
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
    <title>Déposer une copie - Concours Anonyme</title>
    <?php
    // Définir la base de l'URL pour les liens relatifs.
    $base_path = rtrim(parse_url(APP_URL, PHP_URL_PATH), '/') . '/';
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_path); ?>assets/css/style.css">
    <style>
        .phase-info { padding: 15px; border-radius: 8px; margin: 20px 0; font-weight: bold; }
        .phase-normale { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .phase-grace { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .phase-fermee { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .countdown { font-size: 1.1rem; margin-top: 10px; }
        .upload-form { opacity: 1; transition: opacity 0.3s; }
        .upload-form.disabled { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body>
    <?php include BASE_DIR . '/includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <!-- Navigation du dashboard -->
                <?php include BASE_DIR . '/includes/dashboard_nav.php'; ?>
                
                <!-- Contenu principal -->
                <div class="dashboard-content">
                    <h1>Déposer une copie</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <section class="form-section">
                        <h2><?php echo htmlspecialchars($concours['titre']); ?></h2>

                        <!-- Affichage de la phase actuelle -->
                        <div class="phase-info <?php echo $classe_phase; ?>">
                            <?php echo $message_phase; ?>
                            <?php if ($phase_actuelle === 'normale' || $phase_actuelle === 'grace'): ?>
                                <div class="countdown" id="countdown"></div>
                            <?php endif; ?>
                        </div>

                        <p class="concours-dates">
                            <strong>Période normale :</strong> <?php echo date('d/m/Y H:i', strtotime($concours['date_debut'])); ?>
                            → <?php echo date('d/m/Y H:i', strtotime($concours['date_fin'])); ?><br>
                            <strong>Période de grâce :</strong> <?php echo $date_fin_grace->format('d/m/Y H:i'); ?>
                        </p>
                        
                        <form method="POST" enctype="multipart/form-data" class="upload-form <?php echo !$peut_deposer ? 'disabled' : ''; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <div class="form-group">
                                <label for="copie">Sélectionnez votre copie :</label>
                                <input type="file" id="copie" name="copie" accept=".pdf,.zip" required>
                                <small>Formats acceptés : PDF, ZIP (max 10MB)</small>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn">Déposer ma copie</button>
                                <a href="<?php echo APP_URL . '/dashboard/candidat.php'; ?>" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include BASE_DIR . '/includes/footer.php'; ?>
    <script src="<?php echo htmlspecialchars($base_path); ?>assets/js/main.js"></script>
    <script>
        // Amélioration de l'interface de sélection de fichier
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('copie');
            const form = document.querySelector('.upload-form');
            const submitBtn = form.querySelector('button[type="submit"]');

            // Affichage du nom du fichier sélectionné
            fileInput.addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'Aucun fichier sélectionné';
                const fileSize = this.files[0] ? (this.files[0].size / 1024 / 1024).toFixed(2) + ' MB' : '';

                // Créer ou mettre à jour l'affichage du fichier
                let fileDisplay = document.getElementById('file-display');
                if (!fileDisplay) {
                    fileDisplay = document.createElement('div');
                    fileDisplay.id = 'file-display';
                    fileDisplay.style.cssText = 'margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; border: 1px solid #ddd;';
                    this.parentNode.appendChild(fileDisplay);
                }

                if (this.files[0]) {
                    fileDisplay.innerHTML = `
                        <strong>Fichier sélectionné :</strong><br>
                        📄 ${fileName}<br>
                        📊 Taille : ${fileSize}
                    `;
                    fileDisplay.style.color = '#28a745';
                } else {
                    fileDisplay.innerHTML = '❌ Aucun fichier sélectionné';
                    fileDisplay.style.color = '#dc3545';
                }
            });

            // Validation avant soumission
            form.addEventListener('submit', function(e) {
                if (!fileInput.files.length) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un fichier avant de soumettre.');
                    return false;
                }

                const file = fileInput.files[0];
                const maxSize = 10 * 1024 * 1024; // 10MB

                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('Le fichier est trop volumineux. Taille maximale : 10MB');
                    return false;
                }

                const allowedExtensions = ['pdf', 'zip'];
                const extension = file.name.split('.').pop().toLowerCase();

                if (!allowedExtensions.includes(extension)) {
                    e.preventDefault();
                    alert('Format de fichier non autorisé. Formats acceptés : PDF, ZIP');
                    return false;
                }

                // Désactiver le bouton pour éviter les doubles soumissions
                submitBtn.disabled = true;
                submitBtn.textContent = 'Dépôt en cours...';

                return true;
            });

            // Drag & Drop (optionnel)
            const formGroup = fileInput.parentNode;

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                formGroup.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                formGroup.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                formGroup.addEventListener(eventName, unhighlight, false);
            });

            function highlight(e) {
                formGroup.style.background = '#e3f2fd';
                formGroup.style.borderColor = '#2196f3';
            }

            function unhighlight(e) {
                formGroup.style.background = '';
                formGroup.style.borderColor = '';
            }

            formGroup.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;

                if (files.length > 0) {
                    fileInput.files = files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            }
        });

        // Compte à rebours dynamique
        <?php if ($phase_actuelle === 'normale' || $phase_actuelle === 'grace'): ?>
        function updateCountdown() {
            const now = new Date().getTime();
            const targetDate = <?php echo ($phase_actuelle === 'normale' ? $date_fin->getTimestamp() : $date_fin_grace->getTimestamp()) * 1000; ?>;
            const distance = targetDate - now;

            if (distance > 0) {
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                let countdownText = '';
                if (days > 0) countdownText += days + 'j ';
                if (hours > 0) countdownText += hours + 'h ';
                if (minutes > 0) countdownText += minutes + 'm ';
                countdownText += seconds + 's';

                document.getElementById('countdown').innerHTML = '⏰ Temps restant : ' + countdownText;
            } else {
                document.getElementById('countdown').innerHTML = '⏰ Temps écoulé !';
                // Recharger la page pour mettre à jour la phase
                setTimeout(() => location.reload(), 2000);
            }
        }

        // Mettre à jour le compte à rebours toutes les secondes
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>
    </script>
</body>
</html>