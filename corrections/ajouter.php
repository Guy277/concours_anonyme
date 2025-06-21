<?php
/**
 * Interface de correction
 * Permet aux correcteurs de corriger les copies qui leur sont attribuées
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/anonymisation.php';

// Vérification de l'authentification et du rôle correcteur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'correcteur') {
    header('Location: ../login.php');
    exit();
}

$error = null;
$success = null;
$copie = null;

// Vérification de l'ID de la copie
if (!isset($_GET['copie_id'])) {
    header('Location: ../dashboard/correcteur.php');
    exit();
}

$copie_id = (int)$_GET['copie_id'];
$correcteur_id = $_SESSION['user_id'];

// Vérification de l'accès à la copie
$anonymisation = new Anonymisation($conn);
if (!$anonymisation->verifierAccesCorrecteur($copie_id, $correcteur_id)) {
    header('Location: ../dashboard/correcteur.php');
    exit();
}

// Récupération des informations de la copie
$copie = $anonymisation->getCopieAnonyme($copie_id);

// Traitement du formulaire de correction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_FLOAT);
    $commentaire = trim($_POST['commentaire'] ?? '');
    
    if ($note === false || $note < 0 || $note > 20) {
        $error = "La note doit être comprise entre 0 et 20.";
    } else {
        // Enregistrement de la correction
        $sql = "INSERT INTO corrections (copie_id, correcteur_id, note, commentaire, date_correction) 
                VALUES (:copie_id, :correcteur_id, :note, :commentaire, NOW())";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([
            'copie_id' => $copie_id,
            'correcteur_id' => $correcteur_id,
            'note' => $note,
            'commentaire' => $commentaire
        ])) {
            // Mise à jour du statut de la copie (en attente de validation admin)
            $sql = "UPDATE copies SET statut = 'correction_soumise' WHERE id = :copie_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['copie_id' => $copie_id]);

            $success = "La correction a été enregistrée avec succès. En attente de validation par l'administrateur.";
            // Redirection après 2 secondes
            header("refresh:2;url=../dashboard/correcteur.php");
        } else {
            $error = "Une erreur est survenue lors de l'enregistrement de la correction.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Correction</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <section class="correction-section">
                        <div class="copie-info">
                            <h2>Copie à corriger</h2>
                            <p><strong>Concours :</strong> <?php echo htmlspecialchars($copie['concours_titre']); ?></p>
                            <p><strong>Identifiant anonyme :</strong> <?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></p>
                            
                            <div class="copie-preview">
                                <h3>Aperçu de la copie</h3>
                                <?php if (file_exists($copie['fichier_path'])): ?>
                                    <?php if (pathinfo($copie['fichier_path'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                        <iframe src="<?php echo $copie['fichier_path']; ?>" width="100%" height="600px"></iframe>
                                    <?php else: ?>
                                        <p>Fichier ZIP : <a href="<?php echo $copie['fichier_path']; ?>" class="btn-small">Télécharger</a></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="error">Le fichier n'est pas disponible.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <form method="POST" class="correction-form">
                            <div class="form-group">
                                <label for="note">Note (sur 20) :</label>
                                <input type="number" id="note" name="note" min="0" max="20" step="0.5" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="commentaire">Commentaire :</label>
                                <textarea id="commentaire" name="commentaire" rows="6" required></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn">Enregistrer la correction</button>
                                <a href="../dashboard/correcteur.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>