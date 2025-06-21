<?php
/**
 * Page de gestion des corrections d'un correcteur spécifique
 * Permet de voir et supprimer les attributions/corrections d'un correcteur
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/anonymisation.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérification du paramètre correcteur_id
if (!isset($_GET['correcteur_id']) || !is_numeric($_GET['correcteur_id'])) {
    header('Location: ../utilisateurs/gerer.php');
    exit();
}

$correcteur_id = (int)$_GET['correcteur_id'];

// Récupération des informations du correcteur
$sql = "SELECT nom, prenom, email FROM utilisateurs WHERE id = ? AND role = 'correcteur'";
$stmt = $conn->prepare($sql);
$stmt->execute([$correcteur_id]);
$correcteur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$correcteur) {
    header('Location: ../utilisateurs/gerer.php?error=correcteur_introuvable');
    exit();
}

$success = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'supprimer_attribution') {
        $copie_id = (int)$_POST['copie_id'];
        
        try {
            $conn->beginTransaction();
            
            // Supprimer la correction si elle existe
            $sql = "DELETE FROM corrections WHERE copie_id = ? AND correcteur_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$copie_id, $correcteur_id]);
            
            // Supprimer l'attribution
            $sql = "DELETE FROM attributions_copies WHERE copie_id = ? AND correcteur_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$copie_id, $correcteur_id]);
            
            // Remettre la copie en attente
            $sql = "UPDATE copies SET statut = 'en_attente' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$copie_id]);
            
            $conn->commit();
            $success = "Attribution et correction supprimées avec succès.";
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
    
    if ($action === 'reassigner') {
        $copie_id = (int)$_POST['copie_id'];
        $nouveau_correcteur_id = (int)$_POST['nouveau_correcteur_id'];

        try {
            $conn->beginTransaction();

            // Supprimer l'ancienne correction si elle existe
            $sql = "DELETE FROM corrections WHERE copie_id = ? AND correcteur_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$copie_id, $correcteur_id]);

            // Mettre à jour l'attribution
            $sql = "UPDATE attributions_copies SET correcteur_id = ?, date_attribution = NOW() WHERE copie_id = ? AND correcteur_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nouveau_correcteur_id, $copie_id, $correcteur_id]);

            // Mettre à jour le statut de la copie
            $sql = "UPDATE copies SET statut = 'en_correction' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$copie_id]);

            $conn->commit();
            $success = "Copie réassignée avec succès.";

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Erreur lors de la réassignation : " . $e->getMessage();
        }
    }

    if ($action === 'supprimer_toutes_attributions') {
        try {
            $conn->beginTransaction();

            // Récupérer toutes les copies attribuées au correcteur
            $sql = "SELECT copie_id FROM attributions_copies WHERE correcteur_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$correcteur_id]);
            $copies_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($copies_ids)) {
                // Supprimer toutes les corrections du correcteur
                $sql = "DELETE FROM corrections WHERE correcteur_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$correcteur_id]);

                // Supprimer toutes les attributions du correcteur
                $sql = "DELETE FROM attributions_copies WHERE correcteur_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$correcteur_id]);

                // Remettre toutes les copies en attente
                $placeholders = str_repeat('?,', count($copies_ids) - 1) . '?';
                $sql = "UPDATE copies SET statut = 'en_attente' WHERE id IN ($placeholders)";
                $stmt = $conn->prepare($sql);
                $stmt->execute($copies_ids);
            }

            $conn->commit();
            $success = "Toutes les attributions ont été supprimées avec succès. Vous pouvez maintenant supprimer ce correcteur.";

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Récupération des copies attribuées au correcteur
$sql = "SELECT 
            c.id,
            c.identifiant_anonyme,
            c.date_depot,
            c.statut,
            co.titre as concours_titre,
            co.date_debut,
            co.date_fin,
            ac.date_attribution,
            cor.date_correction,
            cor.id as correction_id
        FROM copies c
        INNER JOIN concours co ON c.concours_id = co.id
        INNER JOIN attributions_copies ac ON c.id = ac.copie_id
        LEFT JOIN corrections cor ON c.id = cor.copie_id AND cor.correcteur_id = ?
        WHERE ac.correcteur_id = ?
        ORDER BY ac.date_attribution DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$correcteur_id, $correcteur_id]);
$copies_attribuees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération de la liste des autres correcteurs pour la réassignation
$sql = "SELECT id, nom, prenom FROM utilisateurs WHERE role = 'correcteur' AND id != ? ORDER BY nom, prenom";
$stmt = $conn->prepare($sql);
$stmt->execute([$correcteur_id]);
$autres_correcteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Gestion des corrections - " . $correcteur['prenom'] . ' ' . $correcteur['nom'];
include '../includes/header_admin.php';
?>

<div class="gerer-correcteur-container">
    <div class="gerer-correcteur-header">
        <h1>📝 Gestion des corrections</h1>
        <div class="header-actions">
            <a href="../utilisateurs/gerer.php" class="btn btn-secondary">← Retour aux utilisateurs</a>
        </div>
    </div>

    <!-- Informations du correcteur -->
    <section class="correcteur-info">
        <div class="info-card">
            <h2>👤 Correcteur</h2>
            <div class="correcteur-details">
                <p><strong>Nom :</strong> <?php echo htmlspecialchars($correcteur['prenom'] . ' ' . $correcteur['nom']); ?></p>
                <p><strong>Email :</strong> <?php echo htmlspecialchars($correcteur['email']); ?></p>
                <p><strong>Copies attribuées :</strong> <?php echo count($copies_attribuees); ?></p>
            </div>
        </div>
    </section>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Liste des copies attribuées -->
    <section class="copies-section">
        <h2>📋 Copies attribuées</h2>
        
        <?php if (empty($copies_attribuees)): ?>
            <div class="empty-state">
                <p>Aucune copie n'est attribuée à ce correcteur.</p>
                <p>Vous pouvez maintenant supprimer ce correcteur en toute sécurité.</p>
                <a href="../utilisateurs/gerer.php" class="btn btn-primary">Retour à la gestion des utilisateurs</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID Anonyme</th>
                            <th>Concours</th>
                            <th>Date d'attribution</th>
                            <th>Statut</th>
                            <th>Date de correction</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($copies_attribuees as $copie): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></strong></td>
                            <td><?php echo htmlspecialchars($copie['concours_titre']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($copie['date_attribution'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $copie['statut']; ?>">
                                    <?php
                                    switch($copie['statut']) {
                                        case 'en_correction': echo '⏳ En correction'; break;
                                        case 'correction_soumise': echo '📝 Correction soumise'; break;
                                        case 'corrigee': echo '✅ Corrigée'; break;
                                        default: echo $copie['statut'];
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($copie['date_correction']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($copie['date_correction'])); ?>
                                <?php else: ?>
                                    <span style="color: #999;">Non corrigée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <!-- Bouton de suppression -->
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette attribution et sa correction ?');">
                                        <input type="hidden" name="action" value="supprimer_attribution">
                                        <input type="hidden" name="copie_id" value="<?php echo $copie['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger">🗑️ Supprimer</button>
                                    </form>
                                    
                                    <!-- Bouton de réassignation -->
                                    <?php if (!empty($autres_correcteurs)): ?>
                                    <button onclick="showReassignModal(<?php echo $copie['id']; ?>, '<?php echo htmlspecialchars($copie['identifiant_anonyme']); ?>')" 
                                            class="btn btn-small btn-warning">🔄 Réassigner</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Actions globales -->
            <div class="global-actions">
                <button onclick="confirmDeleteAll()" class="btn btn-danger">🗑️ Supprimer toutes les attributions</button>
                <p style="margin-top: 10px; color: #666; font-size: 0.9em;">
                    ⚠️ Après avoir supprimé toutes les attributions, vous pourrez supprimer ce correcteur.
                </p>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Modal de réassignation -->
<div id="reassignModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3>🔄 Réassigner la copie</h3>
        <form method="POST">
            <input type="hidden" name="action" value="reassigner">
            <input type="hidden" name="copie_id" id="reassign_copie_id">
            
            <div class="form-group">
                <label>Copie : <span id="reassign_copie_nom"></span></label>
            </div>
            
            <div class="form-group">
                <label for="nouveau_correcteur_id">Nouveau correcteur :</label>
                <select name="nouveau_correcteur_id" id="nouveau_correcteur_id" required>
                    <option value="">Choisir un correcteur...</option>
                    <?php foreach ($autres_correcteurs as $correcteur_option): ?>
                        <option value="<?php echo $correcteur_option['id']; ?>">
                            <?php echo htmlspecialchars($correcteur_option['prenom'] . ' ' . $correcteur_option['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Réassigner</button>
                <button type="button" onclick="hideReassignModal()" class="btn btn-secondary">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function showReassignModal(copieId, copieNom) {
    document.getElementById('reassign_copie_id').value = copieId;
    document.getElementById('reassign_copie_nom').textContent = copieNom;
    document.getElementById('reassignModal').style.display = 'block';
}

function hideReassignModal() {
    document.getElementById('reassignModal').style.display = 'none';
}

function confirmDeleteAll() {
    if (confirm('Êtes-vous sûr de vouloir supprimer TOUTES les attributions de ce correcteur ?\n\nCette action supprimera également toutes les corrections associées et remettra les copies en attente.')) {
        // Soumettre un formulaire pour supprimer toutes les attributions
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="supprimer_toutes_attributions">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Fermer le modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('reassignModal');
    if (event.target === modal) {
        hideReassignModal();
    }
}
</script>

<style>
.gerer-correcteur-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.gerer-correcteur-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border-radius: 10px;
}

.correcteur-info {
    margin-bottom: 30px;
}

.info-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.correcteur-details p {
    margin: 10px 0;
}

.copies-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #666;
}

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.global-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    text-align: center;
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 30px;
    border-radius: 10px;
    width: 80%;
    max-width: 500px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 600;
}

.status-badge.status-en_correction {
    background: #fff3cd;
    color: #856404;
}

.status-badge.status-correction_soumise {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.status-corrigee {
    background: #d4edda;
    color: #155724;
}
</style>

<?php include '../includes/footer.php'; ?>
