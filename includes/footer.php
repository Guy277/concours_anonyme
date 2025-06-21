<?php
/**
 * Footer du site
 * Contient les liens utiles et les informations de copyright
 */
?>
<footer>
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Concours Anonyme</h3>
                <p>Une plateforme sécurisée pour la gestion de vos concours et examens.</p>
            </div>
            <div class="footer-section">
                <h3 class="foot">Liens utiles</h3>
                <ul>
                    <li class="foot"><a href="<?php echo APP_URL; ?>/index.php">Accueil</a></li>
                    <li class="foot"><a href="<?php echo APP_URL; ?>/login.php">Connexion</a></li>
                    <li class="foot"><a href="<?php echo APP_URL; ?>/register.php">Inscription</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3 class="foot">Contact</h3>
                    <form action="" method="post">
                    
                    </form>

                <p>Email : contact@concours-anonyme.ci</p>
                <p>Téléphone : +05 55 16 16 29</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Concours Anonyme. Tous droits réservés. @gleguyachille</p>
        </div>
    </div>
</footer>