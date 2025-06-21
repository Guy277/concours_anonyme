<?php
/**
 * Page "Mes copies" pour les candidats
 * Affiche toutes les copies déposées par le candidat connecté
 */

session_start();
require_once '../includes/config.php';

// Vérification de l'authentification et du rôle candidat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidat') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupération des informations utilisateur
$sql = "SELECT nom, prenom FROM utilisateurs WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);
$nom = $user_info['nom'] ?? 'Utilisateur';
$prenom = $user_info['prenom'] ?? '';

// Filtres
$filtre_statut = $_GET['statut'] ?? 'tous';
$filtre_concours = $_GET['concours'] ?? '';

// Construction de la requête avec filtres
$where_conditions = ["co.candidat_id = ?"];
$params = [$user_id];

if ($filtre_statut !== 'tous') {
    $where_conditions[] = "co.statut = ?";
    $params[] = $filtre_statut;
}

if ($filtre_concours) {
    $where_conditions[] = "c.id = ?";
    $params[] = $filtre_concours;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupération des copies avec détails
$sql = "SELECT
            co.*,
            c.titre as concours_titre,
            c.date_debut,
            c.date_fin,
            CASE
                WHEN NOW() < c.date_debut THEN 'pending'
                WHEN NOW() <= c.date_fin THEN 'active'
                ELSE 'finished'
            END as concours_status,
            cor.note,
            cor.date_correction,
            JSON_UNQUOTE(JSON_EXTRACT(cor.evaluation_data_json, '$.commentaire_general')) as commentaire_general
        FROM copies co
        INNER JOIN concours c ON co.concours_id = c.id
        LEFT JOIN corrections cor ON co.id = cor.copie_id
        WHERE {$where_clause}
        ORDER BY co.date_depot DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$copies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des concours pour le filtre
$sql_concours = "SELECT DISTINCT c.id, c.titre
                 FROM copies co
                 INNER JOIN concours c ON co.concours_id = c.id
                 WHERE co.candidat_id = ?
                 ORDER BY c.titre";
$stmt_concours = $conn->prepare($sql_concours);
$stmt_concours->execute([$user_id]);
$concours_list = $stmt_concours->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total' => count($copies),
    'en_attente' => count(array_filter($copies, fn($c) => $c['statut'] === 'en_attente')),
    'en_correction' => count(array_filter($copies, fn($c) => $c['statut'] === 'en_correction')),
    'corrigees' => count(array_filter($copies, fn($c) => $c['statut'] === 'corrigee')),
    'note_moyenne' => 0
];

$notes = array_filter(array_column($copies, 'note'));
if (!empty($notes)) {
    $stats['note_moyenne'] = array_sum($notes) / count($notes);
}

$page_title = "Mes copies";
include '../includes/header.php';
?>

<div class="mes-copies-container">
    <div class="mes-copies-header">
        <h1>📄 Mes copies</h1>
        <div class="header-actions">
            <a href="dashboard/candidat.php" class="btn btn-secondary">← Dashboard</a>
        </div>
    </div>

    <!-- Statistiques -->
    <section class="stats-section">
        <div class="stats-cards">
            <div class="stat-card total">
                <div class="stat-icon">📋</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total copies</div>
                </div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['en_attente']; ?></div>
                    <div class="stat-label">En attente</div>
                </div>
            </div>
            <div class="stat-card correction">
                <div class="stat-icon">📝</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['en_correction']; ?></div>
                    <div class="stat-label">En correction</div>
                </div>
            </div>
            <div class="stat-card completed">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['corrigees']; ?></div>
                    <div class="stat-label">Corrigées</div>
                </div>
            </div>
            <?php if ($stats['note_moyenne'] > 0): ?>
            <div class="stat-card average">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['note_moyenne'], 1); ?></div>
                    <div class="stat-label">Note moyenne</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Filtres -->
    <section class="filters-section">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label for="statut">Statut :</label>
                <select name="statut" id="statut" onchange="this.form.submit()">
                    <option value="tous" <?php echo $filtre_statut === 'tous' ? 'selected' : ''; ?>>Toutes</option>
                    <option value="en_attente" <?php echo $filtre_statut === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="en_correction" <?php echo $filtre_statut === 'en_correction' ? 'selected' : ''; ?>>En correction</option>
                    <option value="corrigee" <?php echo $filtre_statut === 'corrigee' ? 'selected' : ''; ?>>Corrigées</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="concours">Concours :</label>
                <select name="concours" id="concours" onchange="this.form.submit()">
                    <option value="">Tous les concours</option>
                    <?php foreach ($concours_list as $concours): ?>
                        <option value="<?php echo $concours['id']; ?>"
                                <?php echo $filtre_concours == $concours['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($concours['titre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="button" onclick="resetFilters()" class="btn btn-outline">🔄 Réinitialiser</button>
        </form>
    </section>

    <!-- Liste des copies -->
    <section class="copies-section">
        <?php if (empty($copies)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>Aucune copie trouvée</h3>
                <p>Vous n'avez pas encore déposé de copie ou aucune copie ne correspond aux critères sélectionnés.</p>
                <a href="dashboard/candidat.php" class="btn btn-primary">Voir les concours disponibles</a>
            </div>
        <?php else: ?>
            <div class="copies-grid">
                <?php foreach ($copies as $copie): ?>
                    <div class="copie-card status-<?php echo $copie['statut']; ?>">
                        <div class="copie-header">
                            <div class="copie-title">
                                <h3><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></h3>
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
                            </div>
                            <div class="concours-info">
                                <span class="concours-title"><?php echo htmlspecialchars($copie['concours_titre']); ?></span>
                                <span class="concours-status status-<?php echo $copie['concours_status']; ?>">
                                    <?php
                                    switch($copie['concours_status']) {
                                        case 'pending': echo '⏳ En attente'; break;
                                        case 'active': echo '🟢 En cours'; break;
                                        case 'finished': echo '🔴 Terminé'; break;
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="copie-details">
                            <div class="detail-row">
                                <span class="detail-label">📅 Déposée le :</span>
                                <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">⏰ Concours :</span>
                                <span class="detail-value">
                                    <?php echo date('d/m/Y', strtotime($copie['date_debut'])); ?> -
                                    <?php echo date('d/m/Y', strtotime($copie['date_fin'])); ?>
                                </span>
                            </div>

                            <?php if ($copie['statut'] === 'corrigee' && $copie['note']): ?>
                                <div class="detail-row highlight">
                                    <span class="detail-label">📊 Note :</span>
                                    <span class="detail-value note-value"><?php echo number_format($copie['note'], 1); ?>/20</span>
                                </div>

                                <?php if ($copie['date_correction']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">✅ Corrigée le :</span>
                                    <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($copie['date_correction'])); ?></span>
                                </div>
                                <?php endif; ?>

                                <?php if ($copie['commentaire_general']): ?>
                                    <div class="commentaire-preview">
                                        <strong>💬 Commentaire :</strong>
                                        <p><?php echo htmlspecialchars(substr($copie['commentaire_general'], 0, 100)); ?>
                                           <?php echo strlen($copie['commentaire_general']) > 100 ? '...' : ''; ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="copie-actions">
                            <a href="copies/voir.php?id=<?php echo $copie['id']; ?>" class="btn btn-small btn-outline">
                                👁️ Consulter
                            </a>

                            <?php if ($copie['concours_status'] === 'active' && $copie['statut'] !== 'corrigee'): ?>
                                <a href="copies/modifier.php?copie_id=<?php echo $copie['id']; ?>" class="btn btn-small btn-warning">
                                    ✏️ Modifier
                                </a>
                            <?php endif; ?>

                            <?php if ($copie['statut'] === 'corrigee'): ?>
                                <a href="resultats/voir.php?copie_id=<?php echo $copie['id']; ?>" class="btn btn-small btn-info">
                                    📊 Voir résultat
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
function resetFilters() {
    window.location.href = 'copies/mes_copies.php';
}

// Animation des compteurs
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stat-number');
    counters.forEach(counter => {
        const target = parseFloat(counter.textContent);
        let current = 0;
        const increment = target / 20;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.textContent = target % 1 === 0 ? target : target.toFixed(1);
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 50);
    });
});
</script>

<style>
.mes-copies-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.mes-copies-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-card.total { border-left: 4px solid #007bff; }
.stat-card.pending { border-left: 4px solid #ffc107; }
.stat-card.correction { border-left: 4px solid #17a2b8; }
.stat-card.completed { border-left: 4px solid #28a745; }
.stat-card.average { border-left: 4px solid #6f42c1; }

.stat-icon {
    font-size: 2rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #333;
}

.filters-form {
    display: flex;
    gap: 20px;
    align-items: end;
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: bold;
    color: #555;
}

.filter-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    min-width: 150px;
}

.copies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.copie-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.copie-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.status-en_attente {
    border-left: 4px solid #ffc107;
}

.status-en_correction {
    border-left: 4px solid #17a2b8;
}

.status-corrigee {
    border-left: 4px solid #28a745;
}

.copie-header {
    margin-bottom: 15px;
}

.copie-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.copie-title h3 {
    margin: 0;
    color: #333;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
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

.concours-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.concours-title {
    color: #666;
    font-style: italic;
}

.concours-status {
    font-size: 0.8rem;
    padding: 2px 8px;
    border-radius: 12px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.detail-row.highlight {
    background: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    font-weight: bold;
}

.note-value {
    color: #007bff;
    font-size: 1.1em;
    font-weight: bold;
}

.commentaire-preview {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 0.9em;
}

.copie-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .mes-copies-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }

    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }

    .copies-grid {
        grid-template-columns: 1fr;
    }

    .copie-actions {
        justify-content: center;
    }
}
</style>

<?php include '../includes/footer.php'; ?>