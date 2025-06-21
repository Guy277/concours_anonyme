<?php
/**
 * Page de liste des copies
 * Permet aux administrateurs de visualiser les copies déposées pour un concours
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

// Vérification de l'ID du concours
if (!isset($_GET['concours_id'])) {
    header('Location: ../dashboard/admin.php');
    exit();
}

$concours_id = (int)$_GET['concours_id'];

// Récupération des informations du concours
$sql = "SELECT * FROM concours WHERE id = :concours_id";
$stmt = $conn->prepare($sql);
$stmt->execute([':concours_id' => $concours_id]);
$concours = $stmt->fetch();

if (!$concours) {
    header('Location: ../dashboard/admin.php');
    exit();
}

// Récupération des copies pour ce concours
$sql = "SELECT c.*, CONCAT(u.prenom, ' ', u.nom) as candidat_nom FROM copies c
        LEFT JOIN utilisateurs u ON c.candidat_id = u.id
        WHERE c.concours_id = :concours_id ORDER BY c.date_depot DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([':concours_id' => $concours_id]);
$copies = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des copies - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1 class="profile-title-with-status">
                        Copies du concours : <?php echo htmlspecialchars($concours['titre']); ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <?php if ($_SESSION['user_id'] == 1): ?>
                                <span class="admin-badge super-admin">👑</span>
                            <?php else: ?>
                                <span class="admin-badge admin">⭐</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <section class="copies-list-section">
                        <?php if (empty($copies)): ?>
                            <p>Aucune copie n'a été déposée pour ce concours pour le moment.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID Anonyme</th>
                                            <th>Candidat</th>
                                            <th>Date de dépôt</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($copies as $copie): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></td>
                                            <td><?php echo htmlspecialchars($copie['candidat_username'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></td>
                                            <td><?php echo htmlspecialchars($copie['statut']); ?></td>
                                            <td>
                                                <a href="voir.php?id=<?php echo $copie['id']; ?>" class="btn btn-small">Voir</a>
                                                <!-- Ajoutez d'autres actions si nécessaire, ex: télécharger -->
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <div class="form-actions">
                            <a href="../dashboard/admin.php" class="btn btn-secondary">Retour au tableau de bord</a>
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