<?php
/**
 * Page de gestion des correcteurs
 * Permet aux administrateurs d'attribuer des correcteurs à un concours
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

// Récupération des messages de session
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Vérification de l'ID du concours
if (!isset($_GET['concours_id'])) {
    header('Location: ../dashboard/admin.php');
    exit();
}

$concours_id = (int)$_GET['concours_id'];

// Récupération des informations du concours
$sql = "SELECT * FROM concours WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$concours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$concours) {
    header('Location: ../dashboard/admin.php');
    exit();
}

// Traitement de l'ajout d'un correcteur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['correcteur_id'])) {
    $correcteur_id = (int)$_POST['correcteur_id'];

    // Vérification que l'utilisateur est bien un correcteur (table utilisateurs)
    $sql = "SELECT 1 FROM utilisateurs WHERE id = ? AND role = 'correcteur'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$correcteur_id]);
    if ($stmt->rowCount() > 0) {
        // Ajout du correcteur au concours
        $sql = "INSERT INTO assignations_correcteurs (concours_id, correcteur_id)
                VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$concours_id, $correcteur_id])) {
            $success = "Le correcteur a été ajouté avec succès.";
        } else {
            $error = "Une erreur est survenue lors de l'ajout du correcteur.";
        }
    } else {
        $error = "L'utilisateur sélectionné n'est pas un correcteur.";
    }
}

// Traitement de la suppression d'un correcteur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retirer_correcteur_id'])) {
    $correcteur_id_a_retirer = (int)$_POST['retirer_correcteur_id'];

    // Suppression de l'assignation
    $sql = "DELETE FROM assignations_correcteurs WHERE concours_id = ? AND correcteur_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt->execute([$concours_id, $correcteur_id_a_retirer])) {
        $success = "Le correcteur a été retiré avec succès.";
    } else {
        $error = "Une erreur est survenue lors du retrait du correcteur.";
    }
}

// Récupération des correcteurs déjà assignés (table utilisateurs)
$sql = "SELECT u.* FROM utilisateurs u
        INNER JOIN assignations_correcteurs ac ON u.id = ac.correcteur_id
        WHERE ac.concours_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$correcteurs_assignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des correcteurs disponibles (table utilisateurs)
$sql = "SELECT u.* FROM utilisateurs u
        WHERE u.role = 'correcteur'
        AND u.id NOT IN (
            SELECT correcteur_id
            FROM assignations_correcteurs
            WHERE concours_id = ?
        )";
$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$correcteurs_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les correcteurs - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Gérer les correcteurs</h1>
                    <h2><?php echo htmlspecialchars($concours['titre']); ?></h2>

                    <div class="breadcrumb">
                        <a href="concours/liste.php" class="btn btn-secondary">← Retour à la liste des concours</a>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <section class="correcteurs-section">
                        <div class="correcteurs-assignes">
                            <h3>Correcteurs assignés</h3>
                            <?php if (empty($correcteurs_assignes)): ?>
                                <p>Aucun correcteur assigné pour le moment.</p>
                            <?php else: ?>
                                <ul class="correcteurs-list">
                                    <?php foreach ($correcteurs_assignes as $correcteur): ?>
                                        <li>
                                            <?php echo htmlspecialchars($correcteur['prenom'] . ' ' . $correcteur['nom'] . ' (' . $correcteur['email'] . ')'); ?>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Êtes-vous sûr de vouloir retirer ce correcteur ?');">
                                                <input type="hidden" name="retirer_correcteur_id" value="<?php echo $correcteur['id']; ?>">
                                                <button type="submit" class="btn-small btn-danger">Retirer</button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ajouter-correcteur">
                            <h3>Ajouter un correcteur</h3>
                            <?php if (empty($correcteurs_disponibles)): ?>
                                <p>Aucun correcteur disponible.</p>
                            <?php else: ?>
                                <form method="POST" class="correcteur-form">
                                    <div class="form-group">
                                        <label for="correcteur_id">Sélectionner un correcteur :</label>
                                        <select id="correcteur_id" name="correcteur_id" required>
                                            <option value="">Choisir un correcteur...</option>
                                            <?php foreach ($correcteurs_disponibles as $correcteur): ?>
                                                <option value="<?php echo $correcteur['id']; ?>">
                                                    <?php echo htmlspecialchars($correcteur['prenom'] . ' ' . $correcteur['nom'] . ' (' . $correcteur['email'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn">Ajouter le correcteur</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>