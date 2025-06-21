<?php
/**
 * Liste des concours pour gestion (admin)
 * Permet de choisir un concours pour gérer les correcteurs
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
$concours_list = [];

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_concours'])) {
    $concours_id = (int)$_POST['concours_id'];

    try {
        // Vérifier s'il y a des copies pour ce concours
        $sql = "SELECT COUNT(*) FROM copies WHERE concours_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$concours_id]);
        $nb_copies = $stmt->fetchColumn();

        if ($nb_copies > 0) {
            $error = "Impossible de supprimer ce concours car il contient des copies déposées.";
        } else {
            // Supprimer les assignations de correcteurs
            $sql = "DELETE FROM assignations_correcteurs WHERE concours_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$concours_id]);

            // Supprimer le concours
            $sql = "DELETE FROM concours WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$concours_id])) {
                $success = "Le concours a été supprimé avec succès.";
            } else {
                $error = "Une erreur est survenue lors de la suppression.";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression du concours.";
    }
}

try {
    $sql = "SELECT id, titre, date_debut, date_fin FROM concours ORDER BY date_creation DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $concours_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des concours.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des concours - Gérer les correcteurs</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                <div class="dashboard-content">
                    <h1>Liste des concours</h1>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <section class="concours-list-section">
                        <?php if (empty($concours_list)): ?>
                            <p>Aucun concours trouvé.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Titre</th>
                                        <th>Date de début</th>
                                        <th>Date de fin</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($concours_list as $concours): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($concours['titre']); ?></td>
                                        <td><?php echo htmlspecialchars($concours['date_debut']); ?></td>
                                        <td><?php echo htmlspecialchars($concours['date_fin']); ?></td>
                                        <td class="actions-cell">
                                            <a href="<?php echo APP_URL; ?>/concours/modifier.php?id=<?php echo $concours['id']; ?>" class="btn btn-small">Modifier</a>
                                            <a href="<?php echo APP_URL; ?>/concours/gerer_correcteurs.php?concours_id=<?php echo $concours['id']; ?>" class="btn btn-small">Gérer les correcteurs</a>
                                            <a href="<?php echo APP_URL; ?>/copies/attribuer.php?concours_id=<?php echo $concours['id']; ?>" class="btn btn-small">Attribuer les copies</a>
                                            <form method="POST" class="inline-form" onsubmit="return confirmerSuppression('<?php echo htmlspecialchars($concours['titre'], ENT_QUOTES); ?>');">
                                                <input type="hidden" name="concours_id" value="<?php echo $concours['id']; ?>">
                                                <button type="submit" name="delete_concours" class="btn btn-small btn-danger">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
    <script>
        // Fonction de confirmation pour la suppression
        function confirmerSuppression(titreConcours) {
            return confirm('Êtes-vous sûr de vouloir supprimer le concours "' + titreConcours + '" ?\n\nCette action est irréversible et supprimera définitivement :\n- Le concours\n- Toutes les assignations de correcteurs\n\nCliquez sur "OK" pour confirmer ou "Annuler" pour abandonner.');
        }
    </script>
</body>
</html>
