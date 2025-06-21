<?php
/**
 * Page de liste des corrections
 * Permet aux administrateurs et correcteurs de visualiser les corrections
 */

session_start();
require_once '../includes/config.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$error = null;
$success = null;
$corrections = [];

// Récupération des corrections en fonction du rôle
try {
    if ($role === 'admin') {
        // Admin voit toutes les corrections
        $sql = "SELECT c.*, cp.identifiant_anonyme, co.titre as concours_titre,
                CONCAT(u.prenom, ' ', u.nom, ' (', u.email, ')') as correcteur_username,
                JSON_UNQUOTE(JSON_EXTRACT(c.evaluation_data_json, '$.note_totale')) as note_finale,
                JSON_UNQUOTE(JSON_EXTRACT(c.evaluation_data_json, '$.commentaire_general')) as commentaire_general
                FROM corrections c
                INNER JOIN copies cp ON c.copie_id = cp.id
                INNER JOIN concours co ON cp.concours_id = co.id
                INNER JOIN utilisateurs u ON c.correcteur_id = u.id
                ORDER BY c.date_correction DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } elseif ($role === 'correcteur') {
        // Correcteur voit ses propres corrections
        $sql = "SELECT c.*, cp.identifiant_anonyme, co.titre as concours_titre,
                JSON_UNQUOTE(JSON_EXTRACT(c.evaluation_data_json, '$.note_totale')) as note_finale,
                JSON_UNQUOTE(JSON_EXTRACT(c.evaluation_data_json, '$.commentaire_general')) as commentaire_general
                FROM corrections c
                INNER JOIN copies cp ON c.copie_id = cp.id
                INNER JOIN concours co ON cp.concours_id = co.id
                WHERE c.correcteur_id = ?
                ORDER BY c.date_correction DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
    } else {
        // Autres rôles n'ont pas accès
        header('Location: ../dashboard/' . $role . '.php');
        exit();
    }

    $corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des corrections : " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des corrections - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Liste des corrections</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <section class="corrections-list-section">
                        <?php if (empty($corrections)): ?>
                            <p>Aucune correction n'a été enregistrée pour le moment.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Concours</th>
                                            <th>ID Anonyme Copie</th>
                                            <?php if ($role === 'admin'): ?>
                                            <th>Correcteur</th>
                                            <?php endif; ?>
                                            <th>Note</th>
                                            <th>Commentaire</th>
                                            <th>Date de correction</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($corrections as $correction): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($correction['concours_titre']); ?></td>
                                            <td><?php echo htmlspecialchars($correction['identifiant_anonyme']); ?></td>
                                            <?php if ($role === 'admin'): ?>
                                            <td><?php echo htmlspecialchars($correction['correcteur_username']); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo $correction['note_finale'] ? htmlspecialchars($correction['note_finale']) . '/20' : 'N/A'; ?></td>
                                            <td><?php
                                                $commentaire = $correction['commentaire_general'] ?? '';
                                                echo htmlspecialchars(substr($commentaire, 0, 100)) . (strlen($commentaire) > 100 ? '...' : '');
                                            ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($correction['date_correction'])); ?></td>
                                            <td>
                                                <a href="<?php echo APP_URL; ?>/corrections/voir.php?id=<?php echo $correction['id']; ?>" class="btn btn-small">Voir</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <div class="form-actions">
                            <a href="<?php echo APP_URL; ?>/dashboard/<?php echo $role; ?>.php" class="btn btn-secondary btn-retour">← Retour au tableau de bord</a>
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