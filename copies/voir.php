<?php
/**
 * Page de consultation d'une copie pour les candidats
 */

session_start();
require_once '../includes/config.php';

// Inclure la classe d'anonymisation pour déchiffrer les chemins de fichiers
require_once '../includes/anonymisation.php';



// Vérification de l'authentification et du rôle candidat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidat') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérification du paramètre ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . APP_URL . '/copies/mes_copies.php');
    exit();
}

$copie_id = (int)$_GET['id'];

// Récupération des informations de la copie
$sql = "SELECT
            co.*,
            c.titre as concours_titre,
            c.description as concours_description,
            c.date_debut,
            c.date_fin,
            CASE
                WHEN NOW() < c.date_debut THEN 'pending'
                WHEN NOW() <= c.date_fin THEN 'active'
                ELSE 'finished'
            END as concours_status,
            cor.note,
            cor.date_correction,
            JSON_UNQUOTE(JSON_EXTRACT(cor.evaluation_data_json, '$.note_totale')) as note_totale,
            JSON_UNQUOTE(JSON_EXTRACT(cor.evaluation_data_json, '$.commentaire_general')) as commentaire_general,
            CONCAT(u.prenom, ' ', u.nom) as correcteur_nom
        FROM copies co
        INNER JOIN concours c ON co.concours_id = c.id
        LEFT JOIN corrections cor ON co.id = cor.copie_id
        LEFT JOIN utilisateurs u ON cor.correcteur_id = u.id
        WHERE co.id = ? AND co.candidat_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$copie_id, $user_id]);
$copie = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérification que la copie existe et appartient au candidat
if (!$copie) {
    header('Location: ' . APP_URL . '/copies/mes_copies.php?error=copie_introuvable');
    exit();
}

// Déchiffrer le chemin du fichier
$anonymisation = new Anonymisation($conn);
if ($copie['fichier_path']) {
    $copie['fichier_path_dechiffre'] = $anonymisation->decrypt($copie['fichier_path']);
} else {
    $copie['fichier_path_dechiffre'] = null;
}

// Récupération des informations utilisateur
$sql = "SELECT nom, prenom FROM utilisateurs WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);
$nom = $user_info['nom'] ?? 'Utilisateur';
$prenom = $user_info['prenom'] ?? '';

$page_title = "Consulter ma copie - " . $copie['concours_titre'];
include '../includes/header.php';

// Fonction pour formater la taille des fichiers
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>

<div class="voir-copie-container">
    <div class="voir-copie-header">
        <h1>👁️ Consulter ma copie</h1>
        <div class="header-actions">
            <a href="<?php echo APP_URL; ?>/copies/mes_copies.php" class="btn btn-secondary">← Mes copies</a>
            <?php if ($copie['concours_status'] === 'active' && $copie['statut'] !== 'corrigee'): ?>
                <a href="<?php echo APP_URL; ?>/copies/modifier.php?copie_id=<?php echo $copie['id']; ?>" class="btn btn-warning">✏️ Modifier</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informations de la copie -->
    <section class="copie-info-section">
        <div class="info-cards">
            <div class="info-card copie-details">
                <h3>📄 Détails de la copie</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Identifiant anonyme :</span>
                        <span class="detail-value"><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Statut :</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?php echo $copie['statut']; ?>">
                                <?php
                                switch($copie['statut']) {
                                    case 'en_attente': echo '⏳ En attente'; break;
                                    case 'en_correction': echo '📝 En correction'; break;
                                    case 'corrigee': echo '✅ Corrigée'; break;
                                    default: echo $copie['statut'];
                                }
                                ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Date de dépôt :</span>
                        <span class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($copie['date_depot'])); ?></span>
                    </div>
                    <?php if ($copie['statut'] === 'corrigee' && $copie['note_totale']): ?>
                        <div class="detail-item highlight">
                            <span class="detail-label">Note obtenue :</span>
                            <span class="detail-value note-value"><?php echo number_format($copie['note_totale'], 1); ?>/20</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($copie['date_correction']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Date de correction :</span>
                            <span class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($copie['date_correction'])); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($copie['correcteur_nom']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Correcteur :</span>
                            <span class="detail-value"><?php echo htmlspecialchars($copie['correcteur_nom']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card concours-details">
                <h3>🎯 Détails du concours</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Titre :</span>
                        <span class="detail-value"><?php echo htmlspecialchars($copie['concours_titre']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Statut :</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?php echo $copie['concours_status']; ?>">
                                <?php
                                switch($copie['concours_status']) {
                                    case 'pending': echo '⏳ En attente'; break;
                                    case 'active': echo '🟢 En cours'; break;
                                    case 'finished': echo '🔴 Terminé'; break;
                                }
                                ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Période :</span>
                        <span class="detail-value">
                            Du <?php echo date('d/m/Y', strtotime($copie['date_debut'])); ?>
                            au <?php echo date('d/m/Y', strtotime($copie['date_fin'])); ?>
                        </span>
                    </div>
                    <?php if ($copie['concours_description']): ?>
                        <div class="detail-item full-width">
                            <span class="detail-label">Description :</span>
                            <div class="detail-value description">
                                <?php echo nl2br(htmlspecialchars($copie['concours_description'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Fichier de la copie -->
    <section class="fichier-section">
        <div class="fichier-card">
            <h3>📎 Fichier déposé</h3>


            <?php if ($copie['fichier_path_dechiffre'] && file_exists($copie['fichier_path_dechiffre'])): ?>
                <div class="fichier-info">
                    <div class="fichier-details">
                        <div class="fichier-icon">
                            <?php
                            $extension = strtolower(pathinfo($copie['fichier_path_dechiffre'], PATHINFO_EXTENSION));
                            switch($extension) {
                                case 'pdf': echo '📄'; break;
                                case 'zip': case 'rar': echo '📦'; break;
                                case 'doc': case 'docx': echo '📝'; break;
                                default: echo '📎';
                            }
                            ?>
                        </div>
                        <div class="fichier-meta">
                            <div class="fichier-nom"><?php echo htmlspecialchars(basename($copie['fichier_path_dechiffre'])); ?></div>
                            <div class="fichier-taille"><?php echo formatBytes(filesize($copie['fichier_path_dechiffre'])); ?></div>
                            <div class="fichier-type">Type: <?php echo strtoupper($extension); ?></div>
                        </div>
                    </div>
                    <div class="fichier-actions">
                        <a href="<?php echo APP_URL; ?>/copies/telecharger_copie.php?id=<?php echo $copie['id']; ?>&action=view"
                           target="_blank" class="btn btn-primary">
                            👁️ Ouvrir le fichier
                        </a>
                        <a href="<?php echo APP_URL; ?>/copies/telecharger_copie.php?id=<?php echo $copie['id']; ?>&action=download"
                           class="btn btn-outline">
                            💾 Télécharger
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="fichier-error">
                    <div class="error-icon">⚠️</div>
                    <div class="error-message">
                        <h4>Fichier non disponible</h4>
                        <p>Le fichier de cette copie n'est plus accessible ou a été supprimé.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Résultats de correction (si disponibles) -->
    <?php if ($copie['statut'] === 'corrigee'): ?>
        <?php
        // Récupération des données d'évaluation détaillées
        $sql_eval = "SELECT
                        cor.evaluation_data_json,
                        c.grading_grid_json
                     FROM corrections cor
                     INNER JOIN copies co ON cor.copie_id = co.id
                     INNER JOIN concours c ON co.concours_id = c.id
                     WHERE cor.copie_id = ?";
        $stmt_eval = $conn->prepare($sql_eval);
        $stmt_eval->execute([$copie['id']]);
        $evaluation_details = $stmt_eval->fetch(PDO::FETCH_ASSOC);

        $evaluation_data = null;
        $grading_grid = null;

        if ($evaluation_details) {
            $evaluation_data = json_decode($evaluation_details['evaluation_data_json'], true);
            $grading_grid = json_decode($evaluation_details['grading_grid_json'], true);
        }
        ?>

        <section class="resultats-section">
            <div class="resultats-card">
                <h3>📊 Mes résultats</h3>

                <!-- Note globale -->
                <?php if ($copie['note_totale']): ?>
                <div class="note-globale">
                    <div class="note-display">
                        <span class="note-value"><?php echo number_format($copie['note_totale'], 1); ?></span>
                        <span class="note-max">/20</span>
                    </div>
                    <div class="note-appreciation">
                        <?php
                        $note = (float)$copie['note_totale'];
                        if ($note >= 16) echo "🏆 Très bien";
                        elseif ($note >= 14) echo "🎯 Bien";
                        elseif ($note >= 12) echo "👍 Assez bien";
                        elseif ($note >= 10) echo "✅ Passable";
                        else echo "📚 Insuffisant";
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Détail par critères -->
                <?php if ($evaluation_data && $grading_grid): ?>
                <div class="evaluation-details">
                    <h4>📝 Détail par critères</h4>
                    <?php foreach ($grading_grid as $item): ?>
                        <?php if (isset($evaluation_data[$item['id']])): ?>
                        <div class="evaluation-item">
                            <div class="evaluation-header">
                                <span class="critere-label"><?php echo htmlspecialchars($item['label']); ?></span>
                                <?php if ($item['type'] === 'number'): ?>
                                    <span class="critere-note">
                                        <?php echo $evaluation_data[$item['id']]; ?>/<?php echo $item['max'] ?? 20; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($item['type'] === 'textarea' && !empty($evaluation_data[$item['id']])): ?>
                            <div class="critere-commentaire">
                                <?php echo nl2br(htmlspecialchars($evaluation_data[$item['id']])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Commentaire général -->
                <?php if ($copie['commentaire_general']): ?>
                <div class="commentaire-general">
                    <h4>💬 Commentaire général</h4>
                    <div class="commentaire-content">
                        <?php echo nl2br(htmlspecialchars($copie['commentaire_general'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Actions résultats -->
                <div class="resultats-actions">
                    <a href="<?php echo APP_URL; ?>/resultats/voir.php?copie_id=<?php echo $copie['id']; ?>" class="btn btn-info">
                        📊 Voir l'évaluation complète
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        🖨️ Imprimer mes résultats
                    </button>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Actions simplifiées SANS texte après les icônes -->
    <section class="actions-section">
        <div class="actions-grid">
            <a href="<?php echo APP_URL; ?>/copies/mes_copies.php" class="action-card">
                <div class="action-icon">📋</div>
            </a>

            <?php if ($copie['concours_status'] === 'active' && $copie['statut'] !== 'corrigee'): ?>
            <a href="<?php echo APP_URL; ?>/copies/modifier.php?copie_id=<?php echo $copie['id']; ?>" class="action-card">
                <div class="action-icon">✏️</div>
            </a>
            <?php endif; ?>

            <?php if ($copie['statut'] === 'corrigee'): ?>
            <a href="<?php echo APP_URL; ?>/resultats/mes_resultats.php" class="action-card">
                <div class="action-icon">📊</div>
            </a>
            <?php endif; ?>
        </div>
    </section>
</div>

<style>
.voir-copie-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.voir-copie-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
}

.info-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.info-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-card h3 {
    margin: 0 0 20px 0;
    color: #333;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 10px;
}

.detail-grid {
    display: grid;
    gap: 15px;
}

.detail-item {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 10px;
    align-items: center;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-item.highlight {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}

.detail-label {
    font-weight: 600;
    color: #555;
}

.detail-value {
    color: #333;
}

.note-value {
    font-size: 1.2em;
    font-weight: bold;
    color: #007bff;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: bold;
}

.status-badge.status-en_attente {
    background: #fff3cd;
    color: #856404;
}

.status-badge.status-en_correction {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.status-corrigee {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.status-active {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-finished {
    background: #f8d7da;
    color: #721c24;
}

.description {
    line-height: 1.6;
    color: #666;
}

.fichier-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.fichier-card h3 {
    margin: 0 0 20px 0;
    color: #333;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 10px;
}

.fichier-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.fichier-details {
    display: flex;
    align-items: center;
    gap: 15px;
}

.fichier-icon {
    font-size: 3rem;
}

.fichier-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.fichier-nom {
    font-weight: bold;
    color: #333;
}

.fichier-taille, .fichier-type {
    color: #666;
    font-size: 0.9rem;
}

.fichier-actions {
    display: flex;
    gap: 10px;
}

.fichier-error {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f8d7da;
    border-radius: 8px;
    border-left: 4px solid #dc3545;
}

.error-icon {
    font-size: 2rem;
}

.error-message h4 {
    margin: 0 0 5px 0;
    color: #721c24;
}

.error-message p {
    margin: 0;
    color: #721c24;
}

.resultats-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.resultats-card h3 {
    margin: 0 0 20px 0;
    color: #333;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 10px;
}

.note-globale {
    text-align: center;
    margin: 30px 0;
    padding: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
}

.note-display {
    margin-bottom: 15px;
}

.note-value {
    font-size: 3rem;
    font-weight: bold;
}

.note-max {
    font-size: 1.5rem;
    opacity: 0.8;
}

.note-appreciation {
    font-size: 1.2rem;
    font-weight: 500;
}

.evaluation-details {
    margin: 25px 0;
}

.evaluation-details h4 {
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.evaluation-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid #007bff;
}

.evaluation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.critere-label {
    font-weight: 600;
    color: #333;
}

.critere-note {
    background: #007bff;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-weight: bold;
    font-size: 0.9rem;
}

.critere-commentaire {
    background: white;
    padding: 12px;
    border-radius: 5px;
    border: 1px solid #ddd;
    line-height: 1.6;
    color: #555;
    margin-top: 10px;
}

.commentaire-general {
    margin: 25px 0;
}

.commentaire-general h4 {
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.commentaire-content {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #28a745;
    line-height: 1.6;
    color: #555;
}

.resultats-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 25px;
    flex-wrap: wrap;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 20px;
    max-width: 400px;
    margin: 0 auto;
}

.action-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    display: flex;
    justify-content: center;
    align-items: center;
}

.action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    text-decoration: none;
    color: inherit;
}

.action-icon {
    font-size: 2.5rem;
}

@media (max-width: 768px) {
    .voir-copie-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }

    .info-cards {
        grid-template-columns: 1fr;
    }

    .detail-item {
        grid-template-columns: 1fr;
        gap: 5px;
    }

    .fichier-info {
        flex-direction: column;
        align-items: flex-start;
    }

    .fichier-actions {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php include '../includes/footer.php'; ?>