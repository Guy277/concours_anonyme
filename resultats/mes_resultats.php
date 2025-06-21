<?php
/**
 * Page "Mes résultats" pour les candidats
 * Affiche tous les résultats des copies corrigées du candidat
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/note_calculator.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidat') {
    header('Location: ' . APP_URL . '/login.php');
    exit;
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
$filtre_concours = $_GET['concours'] ?? '';

try {
    // Récupération des corrections validées pour ce candidat
    $sql = "SELECT 
                c.id as correction_id,
                c.date_correction,
                c.evaluation_data_json,
                c.commentaire_admin,
                cp.identifiant_anonyme,
                cp.date_depot,
                co.titre as concours_titre,
                co.date_debut,
                co.date_fin,
                CONCAT(u.prenom, ' ', u.nom) as correcteur_nom
            FROM corrections c
            INNER JOIN (
                SELECT copie_id, MAX(id) as last_correction_id
                FROM corrections
                GROUP BY copie_id
            ) as latest_corrections ON c.id = latest_corrections.last_correction_id
            INNER JOIN copies cp ON c.copie_id = cp.id
            INNER JOIN concours co ON cp.concours_id = co.id
            INNER JOIN utilisateurs u ON c.correcteur_id = u.id
            WHERE cp.candidat_id = ? AND cp.statut = 'corrigee'
            ORDER BY c.date_correction DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recalculer les notes avec la classe unifiée
    foreach ($resultats as &$resultat) {
        if ($resultat['evaluation_data_json']) {
            $evaluation_data = json_decode($resultat['evaluation_data_json'], true);
            $resultat['note_totale'] = NoteCalculator::calculerNoteFinale($evaluation_data);
        } else {
            $resultat['note_totale'] = $resultat['note_finale'] ?? 0;
        }
    }

    // Récupération des concours pour les filtres
    $sql_concours = "SELECT DISTINCT co.id, co.titre
                     FROM concours co
                     INNER JOIN copies cp ON co.id = cp.concours_id
                     WHERE cp.candidat_id = ? AND cp.statut = 'corrigee'
                     ORDER BY co.titre";
    $stmt_concours = $conn->prepare($sql_concours);
    $stmt_concours->execute([$user_id]);
    $concours_list = $stmt_concours->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques avec la classe unifiée
    $stats = [
        'total_resultats' => count($resultats),
        'note_moyenne' => 0,
        'note_max' => 0,
        'note_min' => 20,
        'concours_evalues' => count($concours_list)
    ];

    $notes = [];
    foreach ($resultats as $resultat) {
        $note = NoteCalculator::getNoteFromCopie($resultat);
        if ($note > 0) {
            $notes[] = $note;
        }
    }

    if (!empty($notes)) {
        $stats_notes = NoteCalculator::calculerStatistiques($notes);
        $stats['note_moyenne'] = $stats_notes['moyenne'];
        $stats['note_max'] = $stats_notes['max'];
        $stats['note_min'] = $stats_notes['min'];
    }

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des résultats : " . $e->getMessage();
}

$page_title = "Mes résultats";
include '../includes/header.php';
?>

<div class="mes-resultats-container">
    <div class="mes-resultats-header">
        <h1>📊 Mes résultats</h1>
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
                    <div class="stat-number"><?php echo $stats['total_resultats']; ?></div>
                    <div class="stat-label">Résultats disponibles</div>
                </div>
            </div>
            <div class="stat-card concours">
                <div class="stat-icon">🎯</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['concours_evalues']; ?></div>
                    <div class="stat-label">Concours évalués</div>
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
            <div class="stat-card max">
                <div class="stat-icon">🏆</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['note_max'], 1); ?></div>
                    <div class="stat-label">Meilleure note</div>
                </div>
            </div>
            <div class="stat-card min">
                <div class="stat-icon">📈</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['note_min'], 1); ?></div>
                    <div class="stat-label">Note la plus basse</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Filtres -->
    <section class="filters-section">
        <form method="GET" class="filters-form">
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

    <!-- Liste des résultats -->
    <section class="resultats-section">
        <?php if (empty($resultats)): ?>
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <h3>Aucun résultat disponible</h3>
                <p>Vos copies n'ont pas encore été corrigées ou aucun résultat ne correspond aux critères sélectionnés.</p>
                <a href="copies/mes_copies.php" class="btn btn-primary">Voir mes copies</a>
            </div>
        <?php else: ?>
            <div class="resultats-grid">
                <?php foreach ($resultats as $resultat): ?>
                    <?php
                    $note_finale = $resultat['note_totale'] ?: $resultat['note_finale'];
                    $note_class = '';
                    if ($note_finale >= 16) $note_class = 'excellent';
                    elseif ($note_finale >= 14) $note_class = 'tres-bien';
                    elseif ($note_finale >= 12) $note_class = 'bien';
                    elseif ($note_finale >= 10) $note_class = 'assez-bien';
                    else $note_class = 'insuffisant';
                    ?>
                    <div class="resultat-card note-<?php echo $note_class; ?>">
                        <div class="resultat-header">
                            <div class="resultat-title">
                                <h3><?php echo htmlspecialchars($resultat['concours_titre']); ?></h3>
                                <div class="note-principale">
                                    <span class="note-value"><?php echo number_format($note_finale, 1); ?></span>
                                    <span class="note-max">/20</span>
                                </div>
                            </div>
                            <div class="resultat-info">
                                <span class="identifiant"><?php echo htmlspecialchars($resultat['identifiant_anonyme']); ?></span>
                            </div>
                        </div>

                        <div class="resultat-details">
                            <div class="detail-row">
                                <span class="detail-label">📅 Copie déposée :</span>
                                <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($resultat['date_depot'])); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">✅ Corrigée le :</span>
                                <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($resultat['date_correction'])); ?></span>
                            </div>

                            <?php if ($resultat['correcteur_nom']): ?>
                            <div class="detail-row">
                                <span class="detail-label">👨‍🏫 Correcteur :</span>
                                <span class="detail-value"><?php echo htmlspecialchars($resultat['correcteur_nom']); ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="detail-row">
                                <span class="detail-label">🎯 Concours :</span>
                                <span class="detail-value">
                                    <?php echo date('d/m/Y', strtotime($resultat['date_debut'])); ?> -
                                    <?php echo date('d/m/Y', strtotime($resultat['date_fin'])); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($resultat['commentaire_admin']): ?>
                            <div class="commentaire-section">
                                <h4>💬 Commentaire administratif</h4>
                                <div class="commentaire-content">
                                    <?php echo nl2br(htmlspecialchars($resultat['commentaire_admin'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($resultat['evaluation_data_json']): ?>
                            <div class="criteres-section">
                                <h4>📋 Détail de l'évaluation</h4>
                                <div class="criteres-list">
                                    <?php
                                    $criteres = json_decode($resultat['evaluation_data_json'], true);
                                    if ($criteres && is_array($criteres)):
                                        foreach ($criteres as $critere):
                                    ?>
                                        <div class="critere-item">
                                            <div class="critere-header">
                                                <span class="critere-nom"><?php echo htmlspecialchars($critere['nom'] ?? 'Critère'); ?></span>
                                                <span class="critere-note"><?php echo $critere['note'] ?? 'N/A'; ?></span>
                                            </div>
                                            <?php if (!empty($critere['commentaire'])): ?>
                                                <div class="critere-commentaire">
                                                    <?php echo htmlspecialchars($critere['commentaire']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="resultat-actions">
                            <a href="copies/voir.php?id=<?php echo $resultat['correction_id']; ?>" class="btn btn-small btn-outline">
                                👁️ Voir la copie
                            </a>
                            <a href="resultats/voir.php?copie_id=<?php echo $resultat['correction_id']; ?>" class="btn btn-small btn-info">
                                📊 Détail complet
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
function resetFilters() {
    window.location.href = 'resultats/mes_resultats.php';
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
.mes-resultats-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.mes-resultats-header {
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
.stat-card.concours { border-left: 4px solid #6f42c1; }
.stat-card.average { border-left: 4px solid #17a2b8; }
.stat-card.max { border-left: 4px solid #28a745; }
.stat-card.min { border-left: 4px solid #ffc107; }

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

.resultats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
    gap: 25px;
}

.resultat-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.resultat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.note-excellent { border-left: 4px solid #28a745; }
.note-tres-bien { border-left: 4px solid #20c997; }
.note-bien { border-left: 4px solid #17a2b8; }
.note-assez-bien { border-left: 4px solid #ffc107; }
.note-insuffisant { border-left: 4px solid #dc3545; }

.resultat-header {
    margin-bottom: 20px;
}

.resultat-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.resultat-title h3 {
    margin: 0;
    color: #333;
    font-size: 1.3rem;
}

.note-principale {
    display: flex;
    align-items: baseline;
    gap: 2px;
}

.note-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: #007bff;
}

.note-max {
    font-size: 1.2rem;
    color: #666;
}

.identifiant {
    color: #666;
    font-style: italic;
    font-size: 0.9rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding: 5px 0;
}

.detail-label {
    font-weight: 500;
    color: #555;
}

.detail-value {
    color: #333;
}

.commentaire-section {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.commentaire-section h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 1rem;
}

.commentaire-content {
    color: #555;
    line-height: 1.5;
}

.criteres-section {
    margin: 20px 0;
}

.criteres-section h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 1rem;
}

.criteres-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.critere-item {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #dee2e6;
}

.critere-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.critere-nom {
    font-weight: 500;
    color: #333;
}

.critere-note {
    font-weight: bold;
    color: #007bff;
}

.critere-commentaire {
    font-size: 0.9rem;
    color: #666;
    font-style: italic;
}

.resultat-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
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
    .mes-resultats-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }

    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }

    .resultats-grid {
        grid-template-columns: 1fr;
    }

    .resultat-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .resultat-actions {
        justify-content: center;
    }
}
</style>

<?php include '../includes/footer.php'; ?>