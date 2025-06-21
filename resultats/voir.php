<?php
/**
 * Interface de consultation détaillée des résultats pour les candidats
 * Permet aux candidats de voir leurs résultats de correction en détail
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/anonymisation.php';
require_once '../includes/note_calculator.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = null;

// Vérification de l'ID de la copie
if (!isset($_GET['copie_id'])) {
    header('Location: ../dashboard/' . $role . '.php');
    exit();
}

$copie_id = (int)$_GET['copie_id'];

// Récupération des informations de la copie et de la correction
$sql = "SELECT 
            c.id as copie_id,
            c.identifiant_anonyme,
            c.date_depot,
            c.statut,
            co.titre as concours_titre,
            co.description as concours_description,
            co.date_debut,
            co.date_fin,
            co.grading_grid_json,
            corr.id as correction_id,
            corr.evaluation_data_json,
            corr.date_correction,
            CONCAT(u_correcteur.prenom, ' ', u_correcteur.nom) as correcteur_nom
        FROM copies c
        INNER JOIN concours co ON c.concours_id = co.id
        LEFT JOIN corrections corr ON c.id = corr.copie_id
        LEFT JOIN utilisateurs u_correcteur ON corr.correcteur_id = u_correcteur.id
        WHERE c.id = ?";

// Ajouter la condition selon le rôle
if ($role === 'candidat') {
    $sql .= " AND c.candidat_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$copie_id, $user_id]);
} else {
    // Admin ou correcteur peuvent voir toutes les corrections
    $stmt = $conn->prepare($sql);
    $stmt->execute([$copie_id]);
}

$resultat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resultat) {
    $error = "Résultat introuvable ou vous n'avez pas l'autorisation de le consulter.";
} elseif (!$resultat['correction_id']) {
    $error = "Cette copie n'a pas encore été corrigée.";
}

// Décoder les données d'évaluation et la grille
$evaluation_data = null;
$grading_grid = null;

if ($resultat && $resultat['evaluation_data_json']) {
    $evaluation_data = json_decode($resultat['evaluation_data_json'], true);
}

if ($resultat && $resultat['grading_grid_json']) {
    $grading_grid = json_decode($resultat['grading_grid_json'], true);
}

// Fonction pour obtenir le label d'un critère depuis la grille
function getCritereLabel($critere_id, $grading_grid) {
    if (!$grading_grid) return $critere_id;
    
    foreach ($grading_grid as $item) {
        if ($item['id'] === $critere_id) {
            return $item['label'] ?? $critere_id;
        }
    }
    return $critere_id;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat détaillé - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <div class="dashboard-content">
                    <div class="breadcrumb">
                        <?php if ($role === 'candidat'): ?>
                            <a href="<?php echo APP_URL; ?>/copies/mes_copies.php" class="btn btn-secondary">← Retour à mes copies</a>
                        <?php else: ?>
                            <a href="<?php echo APP_URL; ?>/dashboard/<?php echo $role; ?>.php" class="btn btn-secondary">← Retour au dashboard</a>
                        <?php endif; ?>
                    </div>
                    
                    <h1>📊 Résultat détaillé</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php else: ?>
                    
                    <!-- Informations générales -->
                    <section class="dashboard-section">
                        <h2>Informations générales</h2>
                        <div class="result-info">
                            <div class="info-row">
                                <strong>Concours :</strong>
                                <span><?php echo htmlspecialchars($resultat['concours_titre']); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Identifiant anonyme :</strong>
                                <span class="identifiant-anonyme"><?php echo htmlspecialchars($resultat['identifiant_anonyme']); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Date de dépôt :</strong>
                                <span><?php echo date('d/m/Y à H:i', strtotime($resultat['date_depot'])); ?></span>
                            </div>
                            <div class="info-row">
                                <strong>Date de correction :</strong>
                                <span><?php echo date('d/m/Y à H:i', strtotime($resultat['date_correction'])); ?></span>
                            </div>
                            <?php if ($role !== 'candidat'): ?>
                            <div class="info-row">
                                <strong>Correcteur :</strong>
                                <span><?php echo htmlspecialchars($resultat['correcteur_nom']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    
                    <!-- Note globale -->
                    <?php 
                    $note_totale = NoteCalculator::calculerNoteFinale($evaluation_data);
                    if ($note_totale !== null): 
                    ?>
                    <section class="dashboard-section">
                        <h2>Note globale</h2>
                        <div class="note-globale">
                            <div class="note-display">
                                <span class="note-value"><?php echo NoteCalculator::formaterNote($note_totale, false); ?></span>
                                <span class="note-max">/20</span>
                            </div>
                            <div class="note-appreciation">
                                <?php
                                $classe_note = NoteCalculator::getClasseNote($note_totale);
                                switch ($classe_note) {
                                    case 'excellent': echo "Excellent"; break;
                                    case 'tres-bien': echo "Très bien"; break;
                                    case 'bien': echo "Bien"; break;
                                    case 'assez-bien': echo "Assez bien"; break;
                                    default: echo "Insuffisant";
                                }
                                ?>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>
                    <!-- Détail de l'évaluation -->
                    <section class="dashboard-section">
                        <h2>Détail de l'évaluation</h2>
                        
                        <?php if ($evaluation_data && isset($evaluation_data['criteres']) && is_array($evaluation_data['criteres'])): ?>
                            <div class="evaluation-details">
                                <?php foreach ($evaluation_data['criteres'] as $critere): ?>
                                    <div class="evaluation-item">
                                        <div class="evaluation-header">
                                            <h3><?php echo htmlspecialchars($critere['nom'] ?? $critere['label'] ?? 'Critère'); ?></h3>
                                            <span class="evaluation-score">
                                                <?php echo $critere['note'] ?? 0; ?>/<?php echo $critere['max'] ?? 10; ?>
                                            </span>
                                        </div>

                                        <?php if (isset($critere['commentaire']) && !empty($critere['commentaire'])): ?>
                                        <div class="evaluation-content">
                                            <div class="commentaire">
                                                <?php echo nl2br(htmlspecialchars($critere['commentaire'])); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (isset($evaluation_data['commentaire_general']) && !empty($evaluation_data['commentaire_general'])): ?>
                                <div class="evaluation-item general-comment">
                                    <div class="evaluation-header">
                                        <h3>Commentaire général</h3>
                                    </div>
                                    <div class="evaluation-content">
                                        <div class="commentaire">
                                            <?php echo nl2br(htmlspecialchars($evaluation_data['commentaire_general'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($grading_grid): ?>
                            <div class="evaluation-details">
                                <?php foreach ($grading_grid as $item): ?>
                                    <?php if (isset($evaluation_data[$item['id']])): ?>
                                    <div class="evaluation-item">
                                        <div class="evaluation-header">
                                            <h3><?php echo htmlspecialchars($item['label']); ?></h3>
                                            <?php if ($item['type'] === 'number'): ?>
                                                <span class="evaluation-score">
                                                    <?php echo $evaluation_data[$item['id']]; ?>/<?php echo $item['max'] ?? 20; ?>
                                                </span>
                        <?php endif; ?>
                        </div>

                                        <div class="evaluation-content">
                                            <?php if ($item['type'] === 'textarea'): ?>
                                                <div class="commentaire">
                                                    <?php echo nl2br(htmlspecialchars($evaluation_data[$item['id']])); ?>
                </div>
                                            <?php elseif ($item['type'] === 'select'): ?>
                                                <div class="selection">
                                                    <strong>Sélection :</strong> <?php echo htmlspecialchars($evaluation_data[$item['id']]); ?>
            </div>
                                            <?php else: ?>
                                                <div class="valeur">
                                                    <?php echo htmlspecialchars($evaluation_data[$item['id']]); ?>
        </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="evaluation-simple">
                                <h3>Évaluation</h3>
                                <pre><?php echo htmlspecialchars(json_encode($evaluation_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Actions -->
                    <section class="dashboard-section">
                        <h2>Actions</h2>
                        <div class="result-actions">
                            <?php if ($role === 'candidat'): ?>
                                <a href="<?php echo APP_URL; ?>/copies/voir.php?id=<?php echo $resultat['copie_id']; ?>" class="btn btn-secondary">
                                    Voir ma copie
                                </a>
                                <button onclick="window.print()" class="btn btn-secondary">
                                    Imprimer ce résultat
                                </button>
                            <?php else: ?>
                                <a href="<?php echo APP_URL; ?>/copies/voir.php?id=<?php echo $resultat['copie_id']; ?>" class="btn btn-secondary">
                                    Voir la copie
                                </a>
                                <a href="<?php echo APP_URL; ?>/corrections/voir.php?id=<?php echo $resultat['correction_id']; ?>" class="btn btn-secondary">
                                    Voir la correction
                                </a>
                            <?php endif; ?>
                        </div>
                    </section>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>

