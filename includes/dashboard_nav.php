<?php
/**
 * Navigation simple et synchronisée pour les tableaux de bord
 */

// Vérification du rôle
if (!isset($_SESSION['role'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Déterminer le titre selon le rôle
$pageTitle = '';
switch ($_SESSION['role']) {
    case 'admin':
        $pageTitle = 'Administration';
        break;
    case 'candidat':
        $pageTitle = 'Espace Candidat';
        break;
    case 'correcteur':
        $pageTitle = 'Espace Correcteur';
        break;
}
?>

<nav class="dashboard-nav">
    <h2 class="nav-title">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <?php if ($_SESSION['user_id'] == 1): ?>
                <span class="admin-badge super-admin">👑</span>
            <?php else: ?>
                <span class="admin-badge admin">⭐</span>
            <?php endif; ?>
        <?php elseif ($_SESSION['role'] === 'candidat'): ?>
            <span class="admin-badge">📚</span>
        <?php elseif ($_SESSION['role'] === 'correcteur'): ?>
            <span class="admin-badge">📝</span>
        <?php endif; ?>
        <?= htmlspecialchars($pageTitle) ?>
    </h2>

    <ul class="nav-menu">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <li><a href="concours/creer.php">Créer un concours</a></li>
            <li><a href="concours/liste.php">Gérer les concours</a></li>
            <li><a href="utilisateurs/gerer.php">Gérer les utilisateurs</a></li>
            <li><a href="admin/attribuer_copies.php">Gérer les copies</a></li>
            <li><a href="admin/valider_corrections.php">Valider les corrections</a></li>
            <li><a href="admin/statistiques_globales.php">Statistiques</a></li>
            <li><a href="exports/resultats.php">Exporter les résultats</a></li>
            <li><a href="mon-profil.php">Mon profil</a></li>
        <?php elseif ($_SESSION['role'] === 'candidat'): ?>
            <li><a href="dashboard/candidat.php">Tableau de bord</a></li>
            <li><a href="copies/mes_copies.php">Mes copies</a></li>
            <li><a href="resultats/mes_resultats.php">Mes résultats</a></li>
            <li><a href="mon-profil.php">Mon profil</a></li>
        <?php elseif ($_SESSION['role'] === 'correcteur'): ?>
            <li><a href="dashboard/correcteur.php">Tableau de bord</a></li>
            <li><a href="corrections/mes_corrections.php">Mes corrections</a></li>
            <li><a href="corrections/copies_a_corriger.php">Copies à corriger</a></li>
            <li><a href="mon-profil.php">Mon profil</a></li>
        <?php endif; ?>
    </ul>
</nav>


