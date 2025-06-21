<?php
/**
 * Page de gestion des utilisateurs
 * Permet aux administrateurs de gérer les utilisateurs du système
 */

session_start();
require_once '../includes/config.php';

// Vérification des droits d'accès - tous les utilisateurs connectés peuvent voir cette page
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$error = null;
$success = null;

// Traitement de la suppression d'un utilisateur (table utilisateurs) avec PDO - SEULS LES ADMINS
if (isset($_POST['delete_user']) && $isAdmin) {
    $user_id = (int)$_POST['user_id'];
    // Vérification que l'utilisateur n'est pas l'administrateur principal
    $sql = "SELECT role FROM utilisateurs WHERE id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
// Empêcher la suppression du super admin (id=1)
if ($user && $user_id == 1) {
    $error = "Impossible de supprimer le super administrateur.";
} else {
    try {
        // Vérifier s'il y a des données associées à cet utilisateur
        $hasData = false;
        $dataTypes = [];

        // Vérifier les copies
        $sql = "SELECT COUNT(*) as count FROM copies WHERE candidat_id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $copiesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($copiesCount > 0) {
            $hasData = true;
            $dataTypes[] = "$copiesCount copie(s) déposée(s)";
        }

        // Vérifier les corrections
        $sql = "SELECT COUNT(*) as count FROM corrections WHERE correcteur_id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $correctionsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($correctionsCount > 0) {
            $hasData = true;
            $dataTypes[] = "$correctionsCount correction(s) effectuée(s)";
        }

        // Vérifier les attributions de copies
        $sql = "SELECT COUNT(*) as count FROM attributions_copies WHERE correcteur_id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $attributionsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($attributionsCount > 0) {
            $hasData = true;
            $dataTypes[] = "$attributionsCount attribution(s) de copie(s)";
        }

        // Vérifier les concours créés (pour les admins)
        $sql = "SELECT COUNT(*) as count FROM concours WHERE id_admin = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $concoursCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($concoursCount > 0) {
            $hasData = true;
            $dataTypes[] = "$concoursCount concours créé(s)";
        }

        if ($hasData) {
            $error = "Impossible de supprimer cet utilisateur car il a des données associées : " . implode(', ', $dataTypes) . ". Vous devez d'abord supprimer ou réassigner ces données.";
        } else {
            // Aucune donnée associée, on peut supprimer
            $sql = "DELETE FROM utilisateurs WHERE id = :user_id";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute(['user_id' => $user_id])) {
                $success = "L'utilisateur a été supprimé avec succès.";
            } else {
                $error = "Une erreur est survenue lors de la suppression de l'utilisateur.";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}
}

// Gestion du filtrage par rôle
$filtre_role = $_GET['role'] ?? '';

// Construction de la requête avec filtre
$where_clause = '';
$params = [];

if ($filtre_role && in_array($filtre_role, ['admin', 'candidat', 'correcteur'])) {
    $where_clause = 'WHERE role = ?';
    $params[] = $filtre_role;
}

// Récupération de la liste des utilisateurs avec filtrage
$sql = "SELECT id, nom, prenom, email, role, date_creation FROM utilisateurs $where_clause ORDER BY date_creation DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/concours_anonyme/">
    <title>Gestion des utilisateurs - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Gestion des utilisateurs</h1>



                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>


                    <!-- Filtres -->
                    <section class="filters-section" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3>🔍 Filtrer les utilisateurs</h3>
                        <form method="GET" style="display: flex; gap: 15px; align-items: end;">
                            <div class="form-group">
                                <label for="role">Filtrer par rôle :</label>
                                <select name="role" id="role" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">Tous les rôles</option>
                                    <option value="admin" <?php echo ($filtre_role === 'admin') ? 'selected' : ''; ?>>👑 Administrateurs</option>
                                    <option value="correcteur" <?php echo ($filtre_role === 'correcteur') ? 'selected' : ''; ?>>📝 Correcteurs</option>
                                    <option value="candidat" <?php echo ($filtre_role === 'candidat') ? 'selected' : ''; ?>>📚 Candidats</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary" style="background-color: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Filtrer</button>
                                <a href="utilisateurs/gerer.php" class="btn btn-secondary" style="background-color: #6c757d; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; margin-left: 10px;">Réinitialiser</a>
                            </div>
                        </form>

                        <?php if ($filtre_role): ?>
                            <div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #007bff; border-radius: 4px;">
                                <strong>Filtrage actif :</strong>
                                <?php
                                $role_labels = [
                                    'admin' => '👑 Administrateurs',
                                    'correcteur' => '📝 Correcteurs',
                                    'candidat' => '📚 Candidats'
                                ];
                                echo $role_labels[$filtre_role] ?? $filtre_role;
                                ?>
                                (<?php echo count($users); ?> utilisateur<?php echo count($users) > 1 ? 's' : ''; ?> trouvé<?php echo count($users) > 1 ? 's' : ''; ?>)
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="users-section">
                        <div class="section-header">
                            <h2>Liste des utilisateurs</h2>
                            <a href="utilisateurs/creer.php" class="btn btn-primary btn-add-user" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">➕ Ajouter un utilisateur</a>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Date d'inscription</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($user['prenom']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="role-with-badge">
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <?php if ($user['id'] == 1): ?>
                                                        <span class="admin-badge super-admin">👑</span>
                                                    <?php else: ?>
                                                        <span class="admin-badge admin">⭐</span>
                                                    <?php endif; ?>
                                                <?php elseif ($user['role'] === 'candidat'): ?>
                                                    <span class="admin-badge">📚</span>
                                                <?php elseif ($user['role'] === 'correcteur'): ?>
                                                    <span class="admin-badge">📝</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php
                                                // Super admin (id=1) connecté ?
                                                $isSuperAdmin = isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1;
                                                $currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;



                                                // Afficher le bouton modifier si :
                                                // - c'est l'utilisateur lui-même (tout le monde peut se modifier) OU
                                                // - c'est un admin qui modifie quelqu'un d'autre
                                                if ($user['id'] == $currentUserId || ($isAdmin && ($isSuperAdmin || $user['role'] !== 'admin'))) {
                                                ?>
                                                    <a href="utilisateurs/modifier.php?id=<?php echo $user['id']; ?>" class="btn btn-small">Modifier</a>
                                                <?php
                                                }

                                                // Bouton pour voir les copies d'un candidat (pour les admins)
                                                if ($isAdmin && $user['role'] === 'candidat') {
                                                ?>
                                                    <a href="admin/attribuer_copies.php?candidat_id=<?php echo $user['id']; ?>" class="btn btn-small" style="background-color: #17a2b8; color: white;">
                                                        📄 Ses copies
                                                    </a>
                                                <?php
                                                }

                                                // Bouton pour voir les corrections d'un correcteur (pour les admins)
                                                if ($isAdmin && $user['role'] === 'correcteur') {
                                                ?>
                                                    <a href="admin/gerer_correcteur.php?correcteur_id=<?php echo $user['id']; ?>" class="btn btn-small" style="background-color: #28a745; color: white;">
                                                        📝 Ses corrections
                                                    </a>
                                                <?php
                                                }
                                                // Afficher le bouton supprimer uniquement si :
                                                // - c'est un admin ET c'est le super admin connecté ET la cible est un autre admin (pas lui-même)
                                                if ($isAdmin && $isSuperAdmin && $user['role'] === 'admin' && $user['id'] != 1) {
                                                ?>
                                                    <form method="POST" class="delete-form" onsubmit="return confirmerSuppressionUtilisateur('<?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['role'], ENT_QUOTES); ?>');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-small btn-danger">Supprimer</button>
                                                    </form>
                                                <?php
                                                }
                                                // Pour les autres utilisateurs (non admin), tout admin peut supprimer
                                                if ($isAdmin && $user['role'] !== 'admin') {
                                                ?>
                                                    <form method="POST" class="delete-form" onsubmit="return confirmerSuppressionUtilisateur('<?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['role'], ENT_QUOTES); ?>');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-small btn-danger">Supprimer</button>
                                                    </form>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        // Fonction de confirmation pour la suppression d'utilisateurs
        function confirmerSuppressionUtilisateur(nomUtilisateur, roleUtilisateur) {
            const roleTexte = {
                'admin': 'Administrateur',
                'candidat': 'Candidat',
                'correcteur': 'Correcteur'
            };

            const roleAffiche = roleTexte[roleUtilisateur] || roleUtilisateur;

            return confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + nomUtilisateur + '" ?\n\nRôle : ' + roleAffiche + '\n\n⚠️ ATTENTION :\nLa suppression sera refusée si cet utilisateur a :\n- Des copies déposées\n- Des corrections effectuées\n- Des concours créés\n- Des attributions de copies\n\nVous devrez d\'abord supprimer ou réassigner ces données.\n\nCliquez sur "OK" pour tenter la suppression ou "Annuler" pour abandonner.');
        }
    </script>
</body>
</html>