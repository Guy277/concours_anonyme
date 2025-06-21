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
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0 0 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn:hover {
            background: #5a6268;
        }
        .btn-primary {
            background: #007bff;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid #007bff;
        }
        .info-card h3 {
            margin: 0 0 15px 0;
            color: #495057;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
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
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
        }
        .file-section h3 {
            margin: 0 0 15px 0;
            color: #495057;
        }
        .file-actions {
            margin-top: 15px;
        }
        .file-actions a {
            margin-right: 10px;
        }
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👁️ Visualisation de la copie</h1>
            <a href="attribuer_copies.php" class="btn">← Retour à la gestion</a>
        </div>

        <div class="alert">
            <strong>⚠️ Accès administrateur</strong><br>
            En tant qu'administrateur, vous avez accès aux données personnelles pour la gestion du système.
        </div>

        <div class="info-grid">
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
            
            <div class="file-actions">
                <?php if (file_exists($copie['chemin_fichier'])): ?>
                <a href="<?php echo APP_URL . '/' . $copie['chemin_fichier']; ?>" target="_blank" class="btn btn-primary">
                    📥 Télécharger le fichier
                </a>
                <?php if (strtolower(pathinfo($copie['nom_fichier'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                <a href="<?php echo APP_URL . '/' . $copie['chemin_fichier']; ?>" target="_blank" class="btn btn-primary">
                    👁️ Ouvrir le PDF
                </a>
                <?php endif; ?>
                <?php else: ?>
                <span style="color: red;">❌ Fichier introuvable sur le serveur</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
