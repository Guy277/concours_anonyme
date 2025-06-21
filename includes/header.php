<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // Définir la base de l'URL pour les liens relatifs.
    // Assure que les chemins sont corrects, peu importe la structure de l'URL.
    $base_path = rtrim(parse_url(APP_URL, PHP_URL_PATH), '/') . '/';
    ?>
    <base href="<?php echo htmlspecialchars($base_path); ?>">
    <title>Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="./" class="logo">Concours Anonyme</a>
                <div class="nav-links">
                    <?php
                    // Vérifie si l'utilisateur est connecté (user_id existe et n'est pas vide)
                    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])):
                        // Déterminer le statut admin
                        $isSuperAdmin = isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1;
                        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
                    ?>
                        <div class="user-status">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <?php if ($_SESSION['user_id'] == 1): ?>
                                    <a href="dashboard/admin.php" class="nav-link-with-status">
                                        <span class="admin-badge super-admin">👑</span>
                                        Administration
                                    </a>
                                <?php else: ?>
                                    <a href="dashboard/admin.php" class="nav-link-with-status">
                                        <span class="admin-badge admin">⭐</span>
                                        Administration
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($_SESSION['role'] === 'candidat'): ?>
                                <a href="dashboard/candidat.php" class="nav-link-with-status">
                                    <span class="admin-badge">📚</span>
                                    Mon espace
                                </a>
                            <?php elseif ($_SESSION['role'] === 'correcteur'): ?>
                                <a href="dashboard/correcteur.php" class="nav-link-with-status">
                                    <span class="admin-badge">📝</span>
                                    Corrections
                                </a>
                            <?php endif; ?>
                        </div>
                        <a href="logout.php" class="logout-link">Déconnexion</a>
                    <?php else: ?>
                        <a href="login.php">Connexion</a>
                        <a href="register.php">Inscription</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>
