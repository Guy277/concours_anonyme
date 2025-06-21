<?php
session_start();
require_once '../includes/config.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

$copie_id = (int)($_GET['id'] ?? 0);

if ($copie_id <= 0) {
    echo "Erreur: ID de copie invalide";
    exit();
}

// Récupération des détails de la copie
try {
    $sql = "SELECT 
                cp.id,
                cp.identifiant_anonyme,
                cp.nom_fichier,
                cp.chemin_fichier,
                cp.date_depot,
                cp.statut,
                co.titre as concours_titre,
                CONCAT(u_candidat.prenom, ' ', u_candidat.nom) as candidat_nom,
                u_candidat.email as candidat_email
            FROM copies cp
            INNER JOIN concours co ON cp.concours_id = co.id
            INNER JOIN utilisateurs u_candidat ON cp.candidat_id = u_candidat.id
            WHERE cp.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$copie_id]);
    $copie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$copie) {
        echo "Erreur: Copie introuvable";
        exit();
    }
    
} catch (PDOException $e) {
    echo "Erreur SQL: " . $e->getMessage();
    exit();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisation copie - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .copie-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .copie-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .copie-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .info-value {
            color: #6c757d;
        }
        .file-section {
            background: #e9ecef;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="copie-container">
        <div class="copie-header">
            <h1>👁️ Visualisation de la copie</h1>
            <a href="attribuer_copies.php" class="btn btn-secondary">← Retour à la gestion</a>
        </div>

        <div class="alert">
            <strong>⚠️ Accès administrateur</strong><br>
            En tant qu'administrateur, vous avez accès aux données personnelles pour la gestion du système.
        </div>

        <div class="copie-info">
            <div class="info-card">
                <h3>📋 Informations générales</h3>
                <div class="info-row">
                    <span class="info-label">ID:</span>
                    <span class="info-value"><?php echo $copie['id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Identifiant anonyme:</span>
                    <span class="info-value"><?php echo htmlspecialchars($copie['identifiant_anonyme']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Concours:</span>
                    <span class="info-value"><?php echo htmlspecialchars($copie['concours_titre']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date de dépôt:</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($copie['date_depot'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Statut:</span>
                    <span class="info-value"><?php echo $copie['statut']; ?></span>
                </div>
            </div>

            <div class="info-card">
                <h3>👤 Candidat (Accès admin)</h3>
                <div class="info-row">
                    <span class="info-label">Nom:</span>
                    <span class="info-value"><?php echo htmlspecialchars($copie['candidat_nom']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($copie['candidat_email']); ?></span>
                </div>
            </div>
        </div>

        <div class="file-section">
            <h3>📄 Fichier de la copie</h3>
            <div class="info-row">
                <span class="info-label">Nom du fichier:</span>
                <span class="info-value"><?php echo htmlspecialchars($copie['nom_fichier']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Chemin:</span>
                <span class="info-value"><?php echo htmlspecialchars($copie['chemin_fichier']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Taille:</span>
                <span class="info-value">
                    <?php 
                    if (file_exists($copie['chemin_fichier'])) {
                        echo number_format(filesize($copie['chemin_fichier']) / 1024, 1) . ' KB';
                    } else {
                        echo 'Fichier introuvable';
                    }
                    ?>
                </span>
            </div>
            
            <?php if (file_exists($copie['chemin_fichier'])): ?>
            <div style="margin-top: 15px;">
                <a href="<?php echo APP_URL . '/' . $copie['chemin_fichier']; ?>" target="_blank" class="btn">
                    📥 Télécharger le fichier
                </a>
                <?php if (strtolower(pathinfo($copie['nom_fichier'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                <a href="<?php echo APP_URL . '/' . $copie['chemin_fichier']; ?>" target="_blank" class="btn">
                    👁️ Ouvrir le PDF
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="margin-top: 15px; color: red;">
                ❌ Fichier introuvable sur le serveur
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
