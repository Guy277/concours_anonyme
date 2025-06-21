<?php
/**
 * Page d'attribution des copies aux correcteurs
 * Permet aux administrateurs d'attribuer manuellement des copies à des correcteurs
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/anonymisation.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$error = null;
$success = null;

// Vérification de l'ID du concours
if (!isset($_GET['concours_id'])) {
    header('Location: dashboard/admin.php');
    exit();
}

$concours_id = (int)$_GET['concours_id'];

// Récupération des informations du concours
$sql = "SELECT * FROM concours WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$concours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$concours) {
    header('Location: dashboard/admin.php');
    exit();
}

// Traitement de l'attribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attribuer'])) {
    $copie_id = (int)$_POST['copie_id'];
    $correcteur_id = (int)$_POST['correcteur_id'];
    
    $anonymisation = new Anonymisation($conn);
    $result = $anonymisation->attribuerCopie($copie_id, $correcteur_id);
    
    if ($result['success']) {
        $success = "La copie a été attribuée avec succès au correcteur.";
    } else {
        $error = $result['error'];
    }
}

// Traitement de la suppression d'attribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retirer_attribution'])) {
    $copie_id = (int)$_POST['copie_id'];
    
    try {
        // Supprimer l'attribution
        $sql = "DELETE FROM attributions_copies WHERE copie_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$copie_id]);
        
        // Remettre le statut à "en_attente"
        $sql = "UPDATE copies SET statut = 'en_attente' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$copie_id]);
        
        $success = "L'attribution a été supprimée avec succès.";
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression de l'attribution.";
    }
}

// Récupération des copies non attribuées
$sql = "SELECT c.*, u.nom, u.prenom 
        FROM copies c 
        LEFT JOIN utilisateurs u ON c.candidat_id = u.id 
        LEFT JOIN attributions_copies ac ON c.id = ac.copie_id 
        WHERE c.concours_id = ? AND ac.copie_id IS NULL 
        ORDER BY c.date_depot DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$copies_non_attribuees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des copies attribuées
$sql = "SELECT c.*, u.nom, u.prenom, uc.nom as correcteur_nom, uc.prenom as correcteur_prenom 
        FROM copies c 
        LEFT JOIN utilisateurs u ON c.candidat_id = u.id 
        INNER JOIN attributions_copies ac ON c.id = ac.copie_id 
        INNER JOIN utilisateurs uc ON ac.correcteur_id = uc.id 
        WHERE c.concours_id = ? 
        ORDER BY c.date_depot DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$copies_attribuees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des correcteurs assignés au concours
$sql = "SELECT u.id, u.nom, u.prenom, u.email 
        FROM utilisateurs u 
        INNER JOIN assignations_correcteurs ac ON u.id = ac.correcteur_id 
        WHERE ac.concours_id = ? 
        ORDER BY u.nom, u.prenom";
$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$correcteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attribution des copies - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Attribution des copies</h1>
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
                    
                    <!-- Copies non attribuées -->
                    <section class="dashboard-section">
                        <h3>Copies en attente d'attribution (<?php echo count($copies_non_attribuees); ?>)</h3>
                        
                        <?php if (empty($copies_non_attribuees)): ?>
                            <p>Toutes les copies ont été attribuées.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID Anonyme</th>
                                            <th>Date de dépôt</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($copies_non_attribuees as $copie): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></td>
                                            <td><span class="status-badge status-pending"><?php echo htmlspecialchars($copie['statut']); ?></span></td>
                                            <td>
                                                <?php if (!empty($correcteurs)): ?>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="copie_id" value="<?php echo $copie['id']; ?>">
                                                    <select name="correcteur_id" required>
                                                        <option value="">Choisir un correcteur...</option>
                                                        <?php foreach ($correcteurs as $correcteur): ?>
                                                            <option value="<?php echo $correcteur['id']; ?>">
                                                                <?php echo htmlspecialchars($correcteur['prenom'] . ' ' . $correcteur['nom']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" name="attribuer" class="btn btn-small">Attribuer</button>
                                                </form>
                                                <?php else: ?>
                                                    <span class="text-muted">Aucun correcteur assigné au concours</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                    
                    <!-- Copies attribuées -->
                    <section class="dashboard-section">
                        <h3>Copies attribuées (<?php echo count($copies_attribuees); ?>)</h3>
                        
                        <?php if (empty($copies_attribuees)): ?>
                            <p>Aucune copie n'a encore été attribuée.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID Anonyme</th>
                                            <th>Date de dépôt</th>
                                            <th>Correcteur</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($copies_attribuees as $copie): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></td>
                                            <td><?php echo htmlspecialchars($copie['correcteur_prenom'] . ' ' . $copie['correcteur_nom']); ?></td>
                                            <td><span class="status-badge status-assigned"><?php echo htmlspecialchars($copie['statut']); ?></span></td>
                                            <td>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Êtes-vous sûr de vouloir retirer cette attribution ?');">
                                                    <input type="hidden" name="copie_id" value="<?php echo $copie['id']; ?>">
                                                    <button type="submit" name="retirer_attribution" class="btn btn-small btn-danger">Retirer</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>
