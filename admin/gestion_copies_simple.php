<?php
/**
 * Version simplifiée de la gestion des copies
 * Utilise seulement les colonnes qui existent vraiment dans la base de données
 */

session_start();
require_once '../includes/config.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Traitement des actions (attribution, retrait, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['attribuer_copie'])) {
        $copie_id = (int)$_POST['copie_id'];
        $correcteur_id = (int)$_POST['correcteur_id'];
        
        try {
            // Vérifier si une attribution existe déjà
            $sql = "SELECT id FROM attributions_copies WHERE copie_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$copie_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Mettre à jour l'attribution existante
                $sql = "UPDATE attributions_copies SET correcteur_id = ?, date_attribution = NOW() WHERE copie_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$correcteur_id, $copie_id]);
            } else {
                // Créer une nouvelle attribution
                $sql = "INSERT INTO attributions_copies (copie_id, correcteur_id, date_attribution) VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$copie_id, $correcteur_id]);
            }
            
            // Mettre à jour le statut de la copie
            $sql = "UPDATE copies SET statut = 'en_correction' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$copie_id]);
            
            $success = "Copie attribuée avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur lors de l'attribution : " . $e->getMessage();
        }
    }
}

// Récupération des copies avec une requête simple qui fonctionne
try {
    $sql = "SELECT 
                cp.id,
                cp.identifiant_anonyme,
                cp.date_depot,
                cp.statut,
                co.titre as concours_titre,
                co.id as concours_id,
                CONCAT(u_candidat.prenom, ' ', u_candidat.nom) as candidat_nom,
                u_candidat.email as candidat_email,
                ac.correcteur_id,
                CONCAT(u_correcteur.prenom, ' ', u_correcteur.nom) as correcteur_nom,
                ac.date_attribution
            FROM copies cp
            INNER JOIN concours co ON cp.concours_id = co.id
            INNER JOIN utilisateurs u_candidat ON cp.candidat_id = u_candidat.id
            LEFT JOIN attributions_copies ac ON cp.id = ac.copie_id
            LEFT JOIN utilisateurs u_correcteur ON ac.correcteur_id = u_correcteur.id
            ORDER BY cp.date_depot DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $copies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des copies : " . $e->getMessage();
    $copies = [];
}

// Récupération des correcteurs pour les listes déroulantes
try {
    $sql = "SELECT id, CONCAT(prenom, ' ', nom) as nom_complet FROM utilisateurs WHERE role = 'correcteur' ORDER BY nom, prenom";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $correcteurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $correcteurs = [];
}

// Fonction pour déterminer le statut en français
function getStatutFrancais($statut) {
    switch ($statut) {
        case 'en_attente': return '⏳ En attente';
        case 'en_correction': return '📝 En correction';
        case 'corrigee': return '✅ Corrigée';
        default: return $statut;
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des copies - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .copies-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .copies-table th,
        .copies-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .copies-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .copies-table tr:hover {
            background-color: #f5f5f5;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
            margin: 2px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .attribution-form {
            display: inline-block;
            margin: 5px 0;
        }
        .attribution-form select {
            padding: 5px;
            margin-right: 5px;
        }
        .modal {
            display: none;
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
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: black; }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label { font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <div class="page-header">
                        <h1>📄 Gestion des copies (Version simplifiée)</h1>
                        <div class="header-actions">
                            <a href="<?php echo APP_URL; ?>/dashboard/admin.php" class="btn btn-secondary">← Dashboard</a>
                            <a href="debug_copies.php" class="btn btn-info">🔍 Diagnostic</a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo count($copies); ?></div>
                            <div class="stat-label">Total copies</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo count(array_filter($copies, fn($c) => $c['statut'] === 'en_attente')); ?></div>
                            <div class="stat-label">En attente</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo count(array_filter($copies, fn($c) => $c['statut'] === 'en_correction')); ?></div>
                            <div class="stat-label">En correction</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo count(array_filter($copies, fn($c) => $c['statut'] === 'corrigee')); ?></div>
                            <div class="stat-label">Corrigées</div>
                        </div>
                    </div>

                    <table class="copies-table">
                        <thead>
                            <tr>
                                <th>Identifiant</th>
                                <th>Concours</th>
                                <th>Candidat</th>
                                <th>Date dépôt</th>
                                <th>Statut</th>
                                <th>Correcteur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($copies as $copie): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></strong></td>
                                <td><?php echo htmlspecialchars($copie['concours_titre']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($copie['candidat_nom']); ?><br>
                                    <small><?php echo htmlspecialchars($copie['candidat_email']); ?></small>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></td>
                                <td><?php echo getStatutFrancais($copie['statut']); ?></td>
                                <td>
                                    <?php if ($copie['correcteur_nom']): ?>
                                        <?php echo htmlspecialchars($copie['correcteur_nom']); ?><br>
                                        <small>Attribué le <?php echo date('d/m/Y', strtotime($copie['date_attribution'])); ?></small>
                                    <?php else: ?>
                                        <em>Non attribuée</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$copie['correcteur_id']): ?>
                                    <!-- Attribution -->
                                    <form method="POST" class="attribution-form">
                                        <input type="hidden" name="copie_id" value="<?php echo $copie['id']; ?>">
                                        <select name="correcteur_id" required>
                                            <option value="">Choisir...</option>
                                            <?php foreach ($correcteurs as $correcteur): ?>
                                            <option value="<?php echo $correcteur['id']; ?>">
                                                <?php echo htmlspecialchars($correcteur['nom_complet']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="attribuer_copie" class="btn-small btn-primary">Attribuer</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo APP_URL; ?>/admin/copies/voir.php?id=<?php echo $copie['id']; ?>" class="btn-small btn-success">
                                        👁️ Voir
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>



    <?php include '../includes/footer.php'; ?>
    

</body>
</html>
