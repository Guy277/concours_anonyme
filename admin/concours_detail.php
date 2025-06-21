<?php
/**
 * Détail d'un concours avec gestion des phases de soumission
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/submission_rules.php';

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$concours_id = $_GET['id'] ?? null;
if (!$concours_id) {
    header('Location: gestion_concours.php');
    exit();
}

$submissionRules = new SubmissionRules($conn);

// Récupération des informations du concours
$sql = "SELECT * FROM concours WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$concours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$concours) {
    header('Location: gestion_concours.php');
    exit();
}

// Récupération des copies avec détails des candidats
$sql = "SELECT 
            c.*,
            u.nom,
            u.prenom,
            u.email,
            CASE 
                WHEN c.date_depot <= co.date_fin THEN 'À temps'
                ELSE 'En retard'
            END as statut_depot
        FROM copies c
        INNER JOIN utilisateurs u ON c.candidat_id = u.id
        INNER JOIN concours co ON c.concours_id = co.id
        WHERE c.concours_id = ?
        ORDER BY c.date_depot DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$concours_id]);
$copies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul des statistiques
$now = new DateTime();
$debut = new DateTime($concours['date_debut']);
$fin_str = $concours['date_fin'];
if (strlen($fin_str) == 10) $fin_str .= ' 23:59:59';
$fin = new DateTime($fin_str);
$grace_end = clone $fin;
$grace_end->add(new DateInterval('PT30M'));

$is_started = $now >= $debut;
$is_open = $now <= $fin;
$is_grace = $now > $fin && $now <= $grace_end;
$is_finished = $now > $grace_end;

$page_title = "Détail du concours";
include '../includes/header_admin.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>📊 <?php echo htmlspecialchars($concours['titre']); ?></h1>
        <div class="admin-nav">
            <a href="gestion_concours.php" class="btn btn-secondary">← Retour</a>
            <a href="../concours/modifier.php?id=<?php echo $concours_id; ?>" class="btn">✏️ Modifier</a>
        </div>
    </div>

    <!-- Statut actuel du concours -->
    <section class="admin-section">
        <h2>📅 Statut actuel</h2>
        <div class="status-overview">
            <?php if (!$is_started): ?>
                <div class="status-card status-pending">
                    <div class="status-icon">🔵</div>
                    <div class="status-content">
                        <h3>Concours en attente</h3>
                        <p>Commence le <?php echo $debut->format('d/m/Y à H:i'); ?></p>
                        <div class="countdown-info">
                            Dans <?php echo $now->diff($debut)->format('%a jour(s) %h h %i min'); ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($is_open): ?>
                <div class="status-card status-active">
                    <div class="status-icon">🟢</div>
                    <div class="status-content">
                        <h3>Concours en cours</h3>
                        <p>Se termine le <?php echo $fin->format('d/m/Y à H:i'); ?></p>
                        <div class="countdown-info">
                            Reste <?php echo $now->diff($fin)->format('%a jour(s) %h h %i min'); ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($is_grace): ?>
                <div class="status-card status-grace">
                    <div class="status-icon">🟡</div>
                    <div class="status-content">
                        <h3>Délai de grâce actif</h3>
                        <p>Expire le <?php echo $grace_end->format('d/m/Y à H:i'); ?></p>
                        <div class="countdown-info urgent">
                            Reste <?php echo $now->diff($grace_end)->format('%h h %i min'); ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="status-card status-finished">
                    <div class="status-icon">🔴</div>
                    <div class="status-content">
                        <h3>Concours terminé</h3>
                        <p>Terminé le <?php echo $grace_end->format('d/m/Y à H:i'); ?></p>
                        <div class="countdown-info">
                            Depuis <?php echo $grace_end->diff($now)->format('%a jour(s)'); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Statistiques détaillées -->
    <section class="admin-section">
        <h2>📊 Statistiques</h2>
        <div class="stats-detailed">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($copies); ?></div>
                <div class="stat-label">Total copies</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo count(array_filter($copies, function($c) { return $c['statut_depot'] === 'À temps'; })); ?>
                </div>
                <div class="stat-label">Copies à temps</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php echo count(array_filter($copies, function($c) { return $c['statut_depot'] === 'En retard'; })); ?>
                </div>
                <div class="stat-label">Copies en retard</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $modifications = 0;
                    foreach ($copies as $copie) {
                        $history = $submissionRules->getModificationHistory($copie['id']);
                        $modifications += count($history);
                    }
                    echo $modifications;
                    ?>
                </div>
                <div class="stat-label">Total modifications</div>
            </div>
        </div>
    </section>

    <!-- Liste des copies -->
    <section class="admin-section">
        <h2>📋 Copies soumises</h2>
        
        <?php if (empty($copies)): ?>
            <div class="empty-state">
                <p>Aucune copie n'a encore été soumise pour ce concours.</p>
            </div>
        <?php else: ?>
            <div class="copies-table-container">
                <table class="copies-table">
                    <thead>
                        <tr>
                            <th>Candidat</th>
                            <th>Email</th>
                            <th>Date de dépôt</th>
                            <th>Statut</th>
                            <th>Modifications</th>
                            <th>Phase actuelle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($copies as $copie): ?>
                            <?php
                            $status = $submissionRules->getSubmissionStatus($copie['id']);
                            $history = $submissionRules->getModificationHistory($copie['id']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($copie['nom'] . ' ' . $copie['prenom']); ?></strong>
                                    <br>
                                    <small>ID: <?php echo $copie['identifiant_anonyme']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($copie['email']); ?></td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?>
                                    <br>
                                    <small class="<?php echo $copie['statut_depot'] === 'À temps' ? 'text-success' : 'text-warning'; ?>">
                                        <?php echo $copie['statut_depot']; ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $copie['statut']; ?>">
                                        <?php echo ucfirst($copie['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo count($history); ?></strong>
                                    <?php if (count($history) > 0): ?>
                                        <br><small>Dernière: <?php echo date('d/m H:i', strtotime($history[0]['date_modification'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status['can_modify']): ?>
                                        <span class="phase-badge phase-<?php echo $status['phase']; ?>">
                                            <?php echo $status['phase'] === 'GRACE' ? '🟡 Délai de grâce' : '🟢 Modification libre'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="phase-badge phase-locked">🔴 Verrouillé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="../copies/voir.php?id=<?php echo $copie['id']; ?>" class="btn btn-small">👁️ Voir</a>
                                        <?php if (count($history) > 0): ?>
                                            <button onclick="showHistory(<?php echo $copie['id']; ?>)" class="btn btn-small btn-secondary">📝 Historique</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
function showHistory(copieId) {
    // Ici vous pouvez ajouter une modal pour afficher l'historique
    alert('Fonctionnalité d\'historique à implémenter pour la copie ID: ' + copieId);
}
</script>

<?php include '../includes/footer_admin.php'; ?>
