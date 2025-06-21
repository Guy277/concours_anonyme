<?php
    /**
     * Page d'accueil du système de gestion de concours
     * Cette page présente le système et permet aux utilisateurs de se connecter ou s'inscrire
     */
    // Activation du reporting d'erreurs
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Inclusion de la configuration
    require_once __DIR__ . '/includes/config.php';
    
    // Vérification de la connexion
    if (!isset($conn) || !($conn instanceof PDO)) {
        die("Erreur de connexion à la base de données");
    }

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plateforme de gestion de concours</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Animation du texte de bienvenue */
        .animated-text {
            display: inline-block;
            font-size: 2.5em;
            margin-bottom: 20px;
        }
        .animated-text span {
            display: inline-block;
            animation: wave 6s infinite ease-in-out, colorChange 4s infinite;
        }
        @keyframes wave {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-30px); }
        }
        @keyframes colorChange {
            0% { color: #87ceeb; }
            25% { color: #ff69b4; }
            50% { color: #00ced1; }
            75% { color: #ba55d3; }
            100% { color: #87ceeb; }
        }
        .animated-text span:nth-child(odd) {
            animation-delay: 0.3s;
        }
        .animated-text span:nth-child(even) {
            animation-delay: 0.6s;
        }
        
        /* Fond plus clair pour la section hero */
        .hero {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 50%, #90caf9 100%) !important;
            color: #1565c0 !important;
        }
        
        .hero .subtitle {
            color: #1976d2 !important;
            text-shadow: 0 1px 2px rgba(255,255,255,0.8);
        }
        
        .hero .animated-text span {
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-weight: 600;
        }
        
        /* Responsive pour l'animation */
        @media (max-width: 768px) {
            .animated-text {
                font-size: 1.8em;
            }
        }
        @media (max-width: 480px) {
            .animated-text {
                font-size: 1.4em;
            }
        }
        
        /* Style simple pour la section Concours en cours */
        .active-contests {
            background-color: #f0f8ff !important;
        }
        
        .contest-card {
            background-color: white !important;
            border-left: 4px solid #4a90e2 !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <!-- Section héro -->
            <section class="hero">
                <div class="animated-text">
                    <span>B</span><span>i</span><span>e</span><span>n</span><span>v</span><span>e</span><span>n</span><span>u</span><span>e</span>
                    <span>s</span><span>u</span><span>r</span> <span>l</span><span>a</span> <span>p</span><span>l</span><span>a</span><span>t</span><span>e</span><span>f</span><span>o</span><span>r</span><span>m</span><span>e</span>
                    <span>d</span><span>e</span> <span>C</span><span>o</span><span>n</span><span>c</span><span>o</span><span>u</span><span>r</span><span>s</span> <br>
                    <span>a</span><span>v</span><span>e</span><span>c</span> <span>C</span><span>o</span><span>r</span><span>r</span><span>e</span><span>c</span><span>t</span><span>i</span><span>o</span><span>n</span>
                    <span>A</span><span>n</span><span>o</span><span>n</span><span>y</span><span>m</span><span>e</span>
                </div>
                <p class="subtitle">Un système sécurisé pour la gestion de vos concours et examens</p>
                
                <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="cta-buttons">
                    <a href="login.php" class="btn btn-primary">Se connecter</a>
                    <a href="register.php" class="btn btn-secondary">S'inscrire</a>
                </div>
                <?php endif; ?>
            </section>

            <!-- Section des fonctionnalités -->
            <section class="features">
                <h2>Nos fonctionnalités</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <h3>Dépôt de copies</h3>
                        <p>Déposez vos copies en toute sécurité au format PDF ou ZIP</p>
                    </div>
                    <div class="feature-card">
                        <h3>Correction anonyme</h3>
                        <p>Un système d'anonymisation garantissant l'équité des corrections</p>
                    </div>
                    <div class="feature-card">
                        <h3>Suivi en temps réel</h3>
                        <p>Suivez l'état de vos copies et consultez vos résultats</p>
                    </div>
                    <div class="feature-card">
                        <h3>Interface intuitive</h3>
                        <p>Une plateforme simple et efficace pour tous les utilisateurs</p>
                    </div>
                    <div class="feature-card">
                        <h3>Grilles d'évaluation</h3>
                        <p>Des critères de notation standardisés pour une évaluation objective</p>
                    </div>
                    <div class="feature-card">
                        <h3>Notifications automatiques</h3>
                        <p>Recevez des alertes par email pour les dates limites et les résultats</p>
                    </div>
                </div>
            </section>

            <!-- Section des concours actifs -->
            <section class="active-contests">
                <h2>Concours en cours</h2>
                <div class="contests-grid">
                    <?php
                    try {
                        // Récupération des concours actifs
                        $sql = "SELECT * FROM concours WHERE date_fin >= CURDATE() ORDER BY date_debut DESC LIMIT 3";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                        $concours = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($concours) > 0):
                            foreach($concours as $concours_item):
                    ?>
                    <div class="contest-card">
                        <h3><?php echo htmlspecialchars($concours_item['titre']); ?></h3>
                        <p>Date limite : <?php echo date('d/m/Y', strtotime($concours_item['date_fin'])); ?></p>
                        <?php if (isset($_SESSION['user_id'])): 
                            $role = $_SESSION['role'] ?? 'candidat';
                            $url = '#'; // URL par défaut
                            switch ($role) {
                                case 'admin':
                                    $url = 'admin/concours_detail.php?id=' . $concours_item['id'];
                                    break;
                                case 'candidat':
                                    $url = 'copies/deposer.php?concours_id=' . $concours_item['id'];
                                    break;
                                case 'correcteur':
                                    $url = 'corrections/copies_a_corriger.php?concours_id=' . $concours_item['id'];
                                    break;
                            }
                        ?>
                            <a href="<?php echo $url; ?>" class="btn btn-small">Voir les détails</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-small">Se connecter pour participer</a>
                        <?php endif; ?>
                    </div>
                    <?php
                            endforeach;
                        else:
                    ?>
                    <p>Aucun concours actif pour le moment.</p>
                    <?php 
                        endif;
                    } catch (PDOException $e) {
                        echo "<p>Une erreur est survenue lors de la récupération des concours.</p>";
                    }
                    ?>
                </div>
            </section>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
</body>
</html>