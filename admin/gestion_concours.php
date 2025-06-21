<?php
/**
 * Interface admin pour la gestion des concours et des phases de soumission
 */

// Démarrer la session seulement si pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/submission_rules.php';

// Debug temporaire
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'non défini'));
error_log("Session role: " . ($_SESSION['role'] ?? 'non défini'));

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("Redirection vers login - User ID: " . ($_SESSION['user_id'] ?? 'non défini') . " - Role: " . ($_SESSION['role'] ?? 'non défini'));
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$submissionRules = new SubmissionRules($conn);

// Traitement des actions admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'extend_deadline':
                $concours_id = $_POST['concours_id'];
                $new_deadline = $_POST['new_deadline'];
                
                $sql = "UPDATE concours SET date_fin = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt->execute([$new_deadline, $concours_id])) {
                    $success = "Date limite prolongée avec succès";
                } else {
                    $error = "Erreur lors de la prolongation";
                }
                break;
                
            case 'force_grace_period':
                $concours_id = $_POST['concours_id'];
                // Activer manuellement le délai de grâce en modifiant la date de fin
                $new_deadline = date('Y-m-d H:i:s', strtotime('-5 minutes'));
                
                $sql = "UPDATE concours SET date_fin = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt->execute([$new_deadline, $concours_id])) {
                    $success = "Délai de grâce activé manuellement";
                } else {
                    $error = "Erreur lors de l'activation du délai de grâce";
                }
                break;
        }
    }
}

// Récupération des concours avec statistiques
try {
    $sql = "SELECT 
                c.id, c.titre, c.date_debut, c.date_fin,
                (SELECT COUNT(*) FROM copies WHERE concours_id = c.id) as nb_copies,
                (SELECT COUNT(DISTINCT candidat_id) FROM copies WHERE concours_id = c.id) as nb_candidats,
                (SELECT COUNT(*) FROM copies WHERE concours_id = c.id AND date_depot <= c.date_fin) as copies_a_temps,
                (SELECT COUNT(*) FROM copies WHERE concours_id = c.id AND date_depot > c.date_fin) as copies_en_retard
            FROM concours c
            ORDER BY c.date_debut DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $concours = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des concours : " . $e->getMessage();
    $concours = [];
}

$page_title = "Gestion des Concours";
include '../includes/header_admin.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>🎯 Gestion des Concours</h1>
        <div class="admin-nav">
            <a href="../dashboard/admin.php" class="btn btn-secondary">🏠 Dashboard</a>
            <a href="../concours/creer.php" class="btn">➕ Nouveau concours</a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Vue d'ensemble -->
    <section class="admin-section">
        <h2>📊 Vue d'ensemble</h2>
        <div class="overview-grid">
            <?php
            $now = new DateTime();
            $stats = [
                'total' => count($concours),
                'en_attente' => 0,
                'en_cours' => 0,
                'en_grace' => 0,
                'termines' => 0
            ];
            
            foreach ($concours as $concours_item) {
                $debut = new DateTime($concours_item['date_debut']);
                $fin_str = $concours_item['date_fin'];
                if (strlen($fin_str) == 10) $fin_str .= ' 23:59:59';
                $fin = new DateTime($fin_str);
                $grace_end = clone $fin;
                $grace_end->add(new DateInterval('PT30M'));
                
                if ($now < $debut) {
                    $stats['en_attente']++;
                } elseif ($now <= $fin) {
                    $stats['en_cours']++;
                } elseif ($now <= $grace_end) {
                    $stats['en_grace']++;
                } else {
                    $stats['termines']++;
                }
            }
            ?>
            
            <div class="stat-card stat-total">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total concours</div>
            </div>
            
            <div class="stat-card stat-pending">
                <div class="stat-number"><?php echo $stats['en_attente']; ?></div>
                <div class="stat-label">🔵 En attente</div>
            </div>
            
            <div class="stat-card stat-active">
                <div class="stat-number"><?php echo $stats['en_cours']; ?></div>
                <div class="stat-label">🟢 En cours</div>
            </div>
            
            <div class="stat-card stat-grace">
                <div class="stat-number"><?php echo $stats['en_grace']; ?></div>
                <div class="stat-label">🟡 Délai de grâce</div>
            </div>
            
            <div class="stat-card stat-finished">
                <div class="stat-number"><?php echo $stats['termines']; ?></div>
                <div class="stat-label">🔴 Terminés</div>
            </div>
        </div>
    </section>

    <!-- Liste détaillée des concours -->
    <section class="admin-section">
        <h2>📋 Gestion détaillée</h2>
        
        <div class="concours-admin-list">
            <?php if (!empty($concours) && is_array($concours)): ?>
            <?php foreach ($concours as $concours_item): ?>
                <?php
                $debut = new DateTime($concours_item['date_debut']);
                $fin_str = $concours_item['date_fin'];
                if (strlen($fin_str) == 10) $fin_str .= ' 23:59:59';
                $fin = new DateTime($fin_str);
                $grace_end = clone $fin;
                $grace_end->add(new DateInterval('PT30M'));
                
                $now = new DateTime();
                $is_started = $now >= $debut;
                $is_open = $now <= $fin;
                $is_grace = $now > $fin && $now <= $grace_end;
                $is_finished = $now > $grace_end;
                
                // Déterminer le statut et la classe CSS
                if (!$is_started) {
                    $status = 'pending';
                    $status_text = '🔵 En attente';
                    $status_detail = 'Commence dans ' . $now->diff($debut)->format('%a jour(s) %h h');
                } elseif ($is_open) {
                    $status = 'active';
                    $status_text = '🟢 En cours';
                    $status_detail = 'Se termine dans ' . $now->diff($fin)->format('%a jour(s) %h h');
                } elseif ($is_grace) {
                    $status = 'grace';
                    $status_text = '🟡 Délai de grâce';
                    $status_detail = 'Expire dans ' . $now->diff($grace_end)->format('%h h %i min');
                } else {
                    $status = 'finished';
                    $status_text = '🔴 Terminé';
                    $status_detail = 'Terminé depuis ' . $fin->diff($now)->format('%a jour(s)');
                }
                ?>
                
                <div class="concours-admin-card status-<?php echo $status; ?>">
                    <div class="concours-admin-header">
                        <div class="concours-admin-info">
                            <h3><?php echo htmlspecialchars($concours_item['titre']); ?></h3>
                            <p class="concours-dates">
                                📅 Du <?php echo $debut->format('d/m/Y H:i'); ?> 
                                au <?php echo $fin->format('d/m/Y H:i'); ?>
                            </p>
                        </div>
                        
                        <div class="concours-admin-status">
                            <span class="status-badge"><?php echo $status_text; ?></span>
                            <small><?php echo $status_detail; ?></small>
                        </div>
                    </div>
                    
                    <div class="concours-admin-stats">
                        <div class="stat-item">
                            <strong><?php echo $concours_item['nb_copies']; ?></strong>
                            <span>Copies</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo $concours_item['nb_candidats']; ?></strong>
                            <span>Candidats</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo $concours_item['copies_a_temps']; ?></strong>
                            <span>À temps</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo $concours_item['copies_en_retard']; ?></strong>
                            <span>En retard</span>
                        </div>
                    </div>
                    
                    <div class="concours-admin-actions">
                        <a href="concours_detail.php?id=<?php echo $concours_item['id']; ?>" class="btn btn-small">📊 Détails</a>
                        <a href="../concours/modifier.php?id=<?php echo $concours_item['id']; ?>" class="btn btn-small btn-secondary">✏️ Modifier</a>
                        
                        <?php if ($is_open || $is_grace): ?>
                            <button onclick="showExtendModal(<?php echo $concours_item['id']; ?>, '<?php echo $fin->format('Y-m-d\TH:i'); ?>')" 
                                    class="btn btn-small btn-warning">⏰ Prolonger</button>
                        <?php endif; ?>
                        
                        <?php if ($is_open && !$is_grace): ?>
                            <button onclick="activateGrace(<?php echo $concours_item['id']; ?>)" 
                                    class="btn btn-small btn-danger">🚨 Activer délai de grâce</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>Aucun concours à gérer pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Modal pour prolonger la date limite -->
<div id="extendModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>⏰ Prolonger la date limite</h3>
        <form method="POST">
            <input type="hidden" name="action" value="extend_deadline">
            <input type="hidden" name="concours_id" id="extend_concours_id">
            
            <div class="form-group">
                <label for="new_deadline">Nouvelle date limite :</label>
                <input type="datetime-local" name="new_deadline" id="new_deadline" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-warning">Prolonger</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function showExtendModal(concoursId, currentDeadline) {
    document.getElementById('extend_concours_id').value = concoursId;
    document.getElementById('new_deadline').value = currentDeadline;
    document.getElementById('extendModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('extendModal').style.display = 'none';
}

function activateGrace(concoursId) {
    if (confirm('Êtes-vous sûr de vouloir activer le délai de grâce pour ce concours ? Cette action terminera immédiatement la période normale.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="force_grace_period">
            <input type="hidden" name="concours_id" value="${concoursId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fermer le modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('extendModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php include '../includes/footer_admin.php'; ?>
