<?php
/**
 * Page de création de concours
 * Permet aux administrateurs de créer un nouveau concours
 */

session_start();
require_once '../includes/config.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = null;
$success = null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    $grading_grid_json_input = trim($_POST['grading_grid_json'] ?? '');
    
    // Validation des données
    if (empty($titre) || empty($date_debut) || empty($date_fin)) {
        $error = "Tous les champs obligatoires doivent être remplis.";
    } elseif (strtotime($date_debut) >= strtotime($date_fin)) {
        $error = "La date de début doit être antérieure à la date de fin.";
    } else {
        $grading_grid_json = null;
        if (!empty($grading_grid_json_input)) {
            $decoded_grid = json_decode($grading_grid_json_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = "Format JSON invalide pour la grille d'évaluation.";
            } else {
                $grading_grid_json = $grading_grid_json_input;
            }
        }

        if (!$error) {
            // Vérification préventive : le titre existe-t-il déjà ?
            $sql_check = "SELECT id, titre FROM concours WHERE titre = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->execute([$titre]);
            $existing_concours = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_concours) {
                $error = "❌ Un concours avec le titre <strong>« " . htmlspecialchars($titre) . " »</strong> existe déjà. Veuillez choisir un titre différent.";
            } else {
                // Insertion du concours
                $sql = "INSERT INTO concours (id_admin, titre, description, date_debut, date_fin, grading_grid_json) 
                        VALUES (:id_admin, :titre, :description, :date_debut, :date_fin, :grading_grid_json)";
                $stmt = $conn->prepare($sql);
                
                try {
                    if ($stmt->execute([
                        'id_admin' => $_SESSION['user_id'],
                        'titre' => $titre,
                        'description' => $description,
                        'date_debut' => $date_debut,
                        'date_fin' => $date_fin,
                        'grading_grid_json' => $grading_grid_json
                    ])) {
                        $success = "✅ Le concours <strong>« " . htmlspecialchars($titre) . " »</strong> a été créé avec succès.";
                        // Redirection après 2 secondes
                        header("refresh:2;url=../dashboard/admin.php");
                    } else {
                        $error = "Une erreur est survenue lors de la création du concours.";
                    }
                } catch (PDOException $e) {
                    // Gestion spécifique de l'erreur de contrainte unique
                    if ($e->getCode() == 23000 && strpos($e->getMessage(), 'UQ_concours_titre') !== false) {
                        $error = "❌ Un concours avec le titre <strong>« " . htmlspecialchars($titre) . " »</strong> existe déjà. Veuillez choisir un titre différent.";
                    } else {
                        $error = "Une erreur est survenue lors de la création du concours : " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un concours - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <h1>Créer un nouveau concours</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <section class="form-section">
                        <form method="POST" class="concours-form">
                            <div class="form-group">
                                <label for="titre">Titre du concours *</label>
                                <input type="text" id="titre" name="titre" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_debut">Date de début *</label>
                                <input type="date" id="date_debut" name="date_debut" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_fin">Date de fin *</label>
                                <input type="date" id="date_fin" name="date_fin" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="grading_grid_json">Grille d'évaluation (JSON)</label>
                                <textarea id="grading_grid_json" name="grading_grid_json" rows="10" placeholder='[{"id":"q1","label":"Qualité de la rédaction","type":"number","min":0,"max":10,"step":0.5,"required":true},{"id":"q2","label":"Originalité de l\'argumentation","type":"textarea","rows":3,"required":false}]'></textarea>
                                <small>Format JSON pour définir les champs d'évaluation (ex: `[{"id":"note","label":"Note","type":"number","min":0,"max":20,"step":0.5,"required":true}]`)</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn">Créer le concours</button>
                                <a href="../dashboard/admin.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
    <script>
        // Validation des dates
        document.querySelector('.concours-form').addEventListener('submit', function(e) {
            const dateDebut = new Date(document.getElementById('date_debut').value);
            const dateFin = new Date(document.getElementById('date_fin').value);
            
            if (dateDebut >= dateFin) {
                e.preventDefault();
                alert('La date de début doit être antérieure à la date de fin.');
            }
        });

        // Validation en temps réel du titre
        const titreInput = document.getElementById('titre');
        const titreFeedback = document.createElement('div');
        titreFeedback.className = 'titre-feedback';
        titreInput.parentNode.appendChild(titreFeedback);

        let checkTimeout;

        titreInput.addEventListener('input', function() {
            const titre = this.value.trim();
            
            // Supprimer le timeout précédent
            clearTimeout(checkTimeout);
            
            // Réinitialiser le feedback
            titreFeedback.innerHTML = '';
            titreFeedback.className = 'titre-feedback';
            
            if (titre.length < 3) {
                titreFeedback.innerHTML = '⚠️ Le titre doit contenir au moins 3 caractères';
                titreFeedback.className = 'titre-feedback warning';
                return;
            }
            
            // Attendre 500ms après la dernière frappe pour éviter trop de requêtes
            checkTimeout = setTimeout(() => {
                checkTitreExiste(titre);
            }, 500);
        });

        function checkTitreExiste(titre) {
            fetch('../api/check_titre_concours.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'titre=' + encodeURIComponent(titre)
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    titreFeedback.innerHTML = '❌ Ce titre existe déjà';
                    titreFeedback.className = 'titre-feedback error';
                    titreInput.setCustomValidity('Ce titre existe déjà');
                } else {
                    titreFeedback.innerHTML = '✅ Titre disponible';
                    titreFeedback.className = 'titre-feedback success';
                    titreInput.setCustomValidity('');
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification:', error);
            });
        }
    </script>
    <style>
        .titre-feedback {
            margin-top: 5px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .titre-feedback.warning {
            color: #f39c12;
        }
        .titre-feedback.error {
            color: #e74c3c;
        }
        .titre-feedback.success {
            color: #27ae60;
        }
    </style>
</body>
</html>