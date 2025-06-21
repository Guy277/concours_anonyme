<?php
/**
 * Interface d'évaluation des copies
 * Permet aux correcteurs d'évaluer les copies anonymes
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/anonymisation.php';

// Vérification de l'authentification et du rôle correcteur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'correcteur') {
    header('Location: ../login.php');
    exit();
}

$error = null;
$success = null;

$correcteur_id = $_SESSION['user_id'];

// Récupérer l'ID de la copie à évaluer
if (!isset($_GET['copie_id'])) {
    header('Location: ../dashboard/correcteur.php');
    exit();
}

$copie_id = (int)$_GET['copie_id'];

$anonymisation = new Anonymisation($conn);

// Vérifier si le correcteur a accès à cette copie
if (!$anonymisation->verifierAccesCorrecteur($copie_id, $correcteur_id)) {
    $error = "Vous n'avez pas l'autorisation d'évaluer cette copie.";
    // Optionnel: Rediriger ou afficher un message d'erreur plus explicite
    // header('Location: ../dashboard/correcteur.php');
    // exit();
}

$copie_info = $anonymisation->getCopieAnonyme($copie_id);

$grading_grid = null;
if ($copie_info && isset($copie_info['concours_id'])) {
    $stmt_grid = $conn->prepare("SELECT grading_grid_json FROM concours WHERE id = :concours_id");
    $stmt_grid->execute(['concours_id' => $copie_info['concours_id']]);
    $result_grid = $stmt_grid->fetch();
    if ($result_grid && $result_grid['grading_grid_json']) {
        $grading_grid = json_decode($result_grid['grading_grid_json'], true);
    }
}

if (!$copie_info) {
    $error = "Copie introuvable.";
} else if (!$grading_grid) {
    $error = "Grille d'évaluation introuvable pour ce concours.";
}

// Traitement du formulaire d'évaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $evaluation_data = [];
    if ($grading_grid) {
        foreach ($grading_grid as $item) {
            $field_name = 'field_' . $item['id'];
            if (isset($_POST[$field_name])) {
                $evaluation_data[$item['id']] = $_POST[$field_name];
            } else {
                $evaluation_data[$item['id']] = null; // Or handle missing fields as an error
            }
        }
    }
    $evaluation_data_json = json_encode($evaluation_data);

    // Enregistrer l'évaluation dans la base de données
    $sql = "INSERT INTO corrections (copie_id, correcteur_id, evaluation_data_json, date_correction) VALUES (:copie_id, :correcteur_id, :evaluation_data_json, NOW())";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([
        'copie_id' => $copie_id,
        'correcteur_id' => $correcteur_id,
        'evaluation_data_json' => $evaluation_data_json
    ])) {
        $success = "Évaluation enregistrée avec succès.";
        $anonymisation->logAudit($correcteur_id, 'Evaluation Copie', 'Copie ID: ' . $copie_id);
    } else {
        $error = "Erreur lors de l'enregistrement de l'évaluation : " . implode(" ", $stmt->errorInfo());
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évaluer une copie - Concours Anonyme</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="dashboard-layout">
                <!-- Navigation du dashboard -->
                <?php include '../includes/dashboard_nav.php'; ?>
                
                <!-- Contenu principal -->
                <div class="dashboard-content">
                    <h1>Évaluer une copie</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($copie_info && !$error): ?>
                        <section class="evaluation-section">
                            <h2>Copie Anonyme : <?php echo htmlspecialchars($copie_info['identifiant_anonyme']); ?></h2>
                            <p>Concours : <?php echo htmlspecialchars($copie_info['concours_titre']); ?></p>
                            <p>Fichier : <a href="<?php echo htmlspecialchars($copie_info['fichier_path']); ?>" target="_blank">Télécharger la copie</a></p>

                            <h3>Grille d'évaluation</h3>
                            <form method="POST" class="evaluation-form">
                                <?php if ($grading_grid): ?>
                                    <?php foreach ($grading_grid as $item): ?>
                                        <div class="form-group">
                                            <label for="field_<?php echo htmlspecialchars($item['id']); ?>"><?php echo htmlspecialchars($item['label']); ?> :</label>
                                            <?php if ($item['type'] === 'number'): ?>
                                                <input type="number" id="field_<?php echo htmlspecialchars($item['id']); ?>" name="field_<?php echo htmlspecialchars($item['id']); ?>" min="<?php echo isset($item['min']) ? htmlspecialchars($item['min']) : ''; ?>" max="<?php echo isset($item['max']) ? htmlspecialchars($item['max']) : ''; ?>" step="<?php echo isset($item['step']) ? htmlspecialchars($item['step']) : '1'; ?>" <?php echo isset($item['required']) && $item['required'] ? 'required' : ''; ?>>
                                            <?php elseif ($item['type'] === 'textarea'): ?>
                                                <textarea id="field_<?php echo htmlspecialchars($item['id']); ?>" name="field_<?php echo htmlspecialchars($item['id']); ?>" rows="<?php echo isset($item['rows']) ? htmlspecialchars($item['rows']) : '5'; ?>" <?php echo isset($item['required']) && $item['required'] ? 'required' : ''; ?>></textarea>
                                            <?php elseif ($item['type'] === 'select' && isset($item['options'])): ?>
                                                <select id="field_<?php echo htmlspecialchars($item['id']); ?>" name="field_<?php echo htmlspecialchars($item['id']); ?>" <?php echo isset($item['required']) && $item['required'] ? 'required' : ''; ?>>
                                                    <?php foreach ($item['options'] as $option): ?>
                                                        <option value="<?php echo htmlspecialchars($option['value']); ?>"><?php echo htmlspecialchars($option['label']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <input type="text" id="field_<?php echo htmlspecialchars($item['id']); ?>" name="field_<?php echo htmlspecialchars($item['id']); ?>" <?php echo isset($item['required']) && $item['required'] ? 'required' : ''; ?>>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Aucune grille d'évaluation définie pour ce concours.</p>
                                <?php endif; ?>
                                <div class="form-actions">
                                    <button type="submit" class="btn">Enregistrer l'évaluation</button>
                                    <a href="../dashboard/correcteur.php" class="btn btn-secondary">Retour</a>
                                </div>
                            </form>
                        </section>
                    <?php elseif (!$error): ?>
                        <p>Aucune copie à évaluer ou copie introuvable.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/main.js"></script>
</body>
</html>