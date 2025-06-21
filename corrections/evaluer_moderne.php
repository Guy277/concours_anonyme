<?php
/**
 * Interface moderne d'évaluation des copies
 * Interface avancée pour les correcteurs avec grille d'évaluation interactive
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

// Récupération de l'ID de la copie
if (!isset($_GET['copie_id'])) {
    header('Location: ../dashboard/correcteur.php');
    exit();
}

$copie_id = (int)$_GET['copie_id'];

// Vérification de l'accès à la copie
$anonymisation = new Anonymisation($conn);

// Vérifier si le correcteur a accès à cette copie
$sql = "SELECT ac.*, c.*, co.titre as concours_titre, co.grading_grid_json
        FROM attributions_copies ac
        INNER JOIN copies c ON ac.copie_id = c.id
        INNER JOIN concours co ON c.concours_id = co.id
        WHERE ac.copie_id = ? AND ac.correcteur_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$copie_id, $correcteur_id]);
$copie_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$copie_info) {
    $error = "Vous n'avez pas l'autorisation d'évaluer cette copie ou elle n'existe pas.";
}

// Vérifier si la copie n'est pas déjà validée (on peut modifier les corrections soumises mais pas validées)
$sql = "SELECT c.id, cp.statut FROM corrections c
        INNER JOIN copies cp ON c.copie_id = cp.id
        WHERE c.copie_id = ? AND c.correcteur_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$copie_id, $correcteur_id]);
$correction_existante = $stmt->fetch();

if ($correction_existante && $correction_existante['statut'] === 'corrigee') {
    $error = "Cette copie a déjà été corrigée et validée. Modification impossible.";
}

// Récupération de la grille d'évaluation
$grading_grid = null;
if ($copie_info && $copie_info['grading_grid_json']) {
    $grading_grid = json_decode($copie_info['grading_grid_json'], true);
}

// Récupération de la correction existante pour pré-remplissage
$correction_existante_data = null;
$is_modification = false;
if ($copie_info && !$error) {
    $sql = "SELECT * FROM corrections WHERE copie_id = ? AND correcteur_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$copie_id, $correcteur_id]);
    $correction_existante_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($correction_existante_data) {
        $is_modification = true;
        // Décoder les données JSON pour pré-remplissage
        if ($correction_existante_data['evaluation_data_json']) {
            $evaluation_existante = json_decode($correction_existante_data['evaluation_data_json'], true);
        }
    }
}

// Traitement du formulaire d'évaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $evaluation_data = [];
    $note_totale = 0;
    $commentaire_general = trim($_POST['commentaire_general'] ?? '');

    // Validation et calcul de la note totale
    $notes_pour_calcul = []; // Initialisation
    if ($grading_grid) {
        foreach ($grading_grid as $critere) {
            $field_name = 'critere_' . $critere['id'];
            $commentaire_name = 'commentaire_' . $critere['id'];

            if ($critere['type'] === 'number') {
                $note = filter_input(INPUT_POST, $field_name, FILTER_VALIDATE_FLOAT);
                $max_points = $critere['max'] ?? 20;

                if ($note === false || $note < 0 || $note > $max_points) {
                    $error = "La note pour '{$critere['label']}' doit être comprise entre 0 et {$max_points}.";
                    break;
                }

                $evaluation_data[$critere['id']] = [
                    'note' => $note,
                    'max' => $max_points,
                    'commentaire' => trim($_POST[$commentaire_name] ?? '')
                ];

                // Stockage pour calcul simple
                $notes_pour_calcul[] = [
                    'note' => $note,
                    'max' => $max_points,
                    'weight' => $critere['weight'] ?? 1
                ];

            } elseif ($critere['type'] === 'textarea') {
                $evaluation_data[$critere['id']] = [
                    'commentaire' => trim($_POST[$field_name] ?? '')
                ];
            }
        }

        // Calcul de la note totale - MÉTHODE CORRIGÉE
        if (!empty($notes_pour_calcul)) {
            $total_points = 0;
            $total_max = 0;
            $total_weight = 0;

            foreach ($notes_pour_calcul as $note_data) {
                $weight = $note_data['weight'];
                $total_points += ($note_data['note'] / $note_data['max']) * $weight;
                $total_weight += $weight;
            }

            // Si pas de poids définis, utiliser calcul simple
            if ($total_weight == count($notes_pour_calcul)) {
                // Calcul simple : moyenne des pourcentages
                $total_simple = 0;
                $total_max_simple = 0;
                foreach ($notes_pour_calcul as $note_data) {
                    $total_simple += $note_data['note'];
                    $total_max_simple += $note_data['max'];
                }
                $note_totale = ($total_simple / $total_max_simple) * 20;
            } else {
                // Calcul avec poids
                $note_totale = ($total_points / $total_weight) * 20;
            }
        }
    }

    if (!$error) {
        // Préparation des données d'évaluation
        $evaluation_json = json_encode([
            'note_totale' => round($note_totale, 2),
            'commentaire_general' => $commentaire_general,
            'criteres' => $evaluation_data,
            'date_evaluation' => date('Y-m-d H:i:s')
        ]);

        try {
            $conn->beginTransaction();

            if ($is_modification && $correction_existante_data) {
                // MISE À JOUR de la correction existante
                $sql = "UPDATE corrections
                        SET evaluation_data_json = ?, date_correction = NOW()
                        WHERE copie_id = ? AND correcteur_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$evaluation_json, $copie_id, $correcteur_id]);

                $action_type = "Modification Correction";
                $success_message = "Correction modifiée avec succès. Note finale : " . round($note_totale, 2) . "/20.";
            } else {
                // NOUVELLE correction
                $sql = "INSERT INTO corrections (copie_id, correcteur_id, evaluation_data_json, date_correction)
                        VALUES (?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$copie_id, $correcteur_id, $evaluation_json]);

                $action_type = "Nouvelle Correction";
                $success_message = "Évaluation soumise avec succès. Note finale : " . round($note_totale, 2) . "/20.";
            }

            // Mise à jour du statut de la copie (en attente de validation admin)
            $sql = "UPDATE copies SET statut = 'correction_soumise', note_totale = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([round($note_totale, 2), $copie_id]);

            $conn->commit();

            // Log de l'audit
            $anonymisation->logAudit($correcteur_id, $action_type,
                "Copie ID: {$copie_id}, Note: " . round($note_totale, 2) . "/20");

            $success = $success_message . " En attente de validation par l'administrateur.";

            // Redirection après 5 secondes
            header("refresh:5;url=../dashboard/correcteur.php");

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

// Récupération du fichier de la copie (déchiffré)
$fichier_path = null;
if ($copie_info && !$error) {
    $copie_anonyme = $anonymisation->getCopieAnonyme($copie_id);
    $fichier_path = $copie_anonyme['fichier_path'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/concours_anonyme/">
    <title>Évaluation de copie - Concours Anonyme</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .evaluation-container { max-width: 1200px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e9ecef; }
        .page-header h1 { margin: 0; color: var(--primary-color); }
        .header-actions { display: flex; gap: 10px; }
        .evaluation-layout { display: grid; grid-template-columns: 1fr 400px; gap: 30px; margin-top: 20px; }
        .copie-viewer { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .evaluation-panel { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: sticky; top: 20px; max-height: calc(100vh - 40px); overflow-y: auto; }
        .copie-info { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .critere-group { margin-bottom: 25px; padding: 20px; border: 1px solid #e9ecef; border-radius: 8px; }
        .critere-header { display: flex; justify-content: between; align-items: center; margin-bottom: 15px; }
        .critere-title { font-weight: bold; color: #495057; }
        .critere-points { background: #007bff; color: white; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; }
        .note-input { width: 80px; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; text-align: center; }
        .commentaire-input { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; resize: vertical; min-height: 80px; }
        .note-totale { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0; }
        .note-display { font-size: 2rem; font-weight: bold; }
        .evaluation-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-evaluer { background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-evaluer:hover { background: #218838; }
        .fichier-viewer { border: 1px solid #ddd; border-radius: 8px; min-height: 400px; }
        .fichier-link { display: inline-block; margin: 10px 0; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; }
        .fichier-link:hover { background: #0056b3; }
        @media (max-width: 768px) {
            .evaluation-layout { grid-template-columns: 1fr; }
            .evaluation-panel { position: static; max-height: none; }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main>
        <div class="evaluation-container">
            <div class="container">
                <div class="page-header">
                    <h1>📝 Évaluation de copie</h1>
                    <div class="header-actions">
                        <a href="<?php echo APP_URL; ?>/dashboard/correcteur.php" class="btn btn-secondary">← Retour au dashboard</a>
                        <a href="<?php echo APP_URL; ?>/corrections/copies_a_corriger.php" class="btn btn-outline">📋 Copies à corriger</a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <div style="text-align: center; margin: 20px 0;">
                        <a href="dashboard/correcteur.php" class="btn">Retour au dashboard</a>
                    </div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <div style="text-align: center; margin: 20px 0;">
                        <p>Redirection automatique dans 3 secondes...</p>
                        <a href="dashboard/correcteur.php" class="btn">Retour immédiat</a>
                    </div>
                <?php else: ?>

                <div class="evaluation-layout">
                    <!-- Visualiseur de copie -->
                    <div class="copie-viewer">
                        <div class="copie-info">
                            <h2>📄 Copie Anonyme</h2>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <p><strong>Identifiant :</strong> <?php echo htmlspecialchars($copie_info['identifiant_anonyme']); ?></p>
                                    <p><strong>Concours :</strong> <?php echo htmlspecialchars($copie_info['concours_titre']); ?></p>
                                </div>
                                <div>
                                    <p><strong>Date de dépôt :</strong> <?php echo date('d/m/Y H:i', strtotime($copie_info['date_depot'])); ?></p>
                                    <p><strong>Statut :</strong>
                                        <span class="status-badge status-<?php echo $copie_info['statut']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $copie_info['statut'])); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="fichier-section">
                            <h3>📎 Fichier de la copie</h3>
                            <?php if ($fichier_path): ?>
                                <a href="<?php echo APP_URL; ?>/corrections/telecharger_copie.php?copie_id=<?php echo $copie_id; ?>&action=download" class="fichier-link">
                                    📥 Télécharger la copie
                                </a>

                                <!-- Prévisualisation si c'est un PDF -->
                                <?php if (pathinfo($fichier_path, PATHINFO_EXTENSION) === 'pdf'): ?>
                                    <div class="fichier-viewer">
                                        <iframe src="<?php echo APP_URL; ?>/corrections/telecharger_copie.php?copie_id=<?php echo $copie_id; ?>&action=view"
                                                width="100%" height="600px"
                                                style="border: none; border-radius: 8px;">
                                            <p>Votre navigateur ne supporte pas l'affichage des PDF.
                                               <a href="<?php echo APP_URL; ?>/corrections/telecharger_copie.php?copie_id=<?php echo $copie_id; ?>&action=download" target="_blank">Télécharger le fichier</a>
                                            </p>
                                        </iframe>
                                    </div>
                                <?php else: ?>
                                    <div class="fichier-viewer">
                                        <div style="text-align: center; padding: 50px; color: #666;">
                                            <p>📄 Fichier disponible au téléchargement</p>
                                            <p><em>Type : <?php echo strtoupper(pathinfo($fichier_path, PATHINFO_EXTENSION)); ?></em></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    Fichier non disponible ou erreur de déchiffrement.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Panneau d'évaluation -->
                    <div class="evaluation-panel">
                        <h3>📋 Grille d'évaluation</h3>

                        <?php if ($is_modification): ?>
                            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 12px; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 1.2em;">🔄</span>
                                    <div>
                                        <strong>Mode modification</strong><br>
                                        <small style="color: #856404;">Vous modifiez une correction existante. Les champs sont pré-remplis avec vos données précédentes.</small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($grading_grid): ?>
                            <form method="POST" id="evaluationForm">
                                <?php
                                // Préparer les données existantes pour pré-remplissage
                                $criteres_existants = [];
                                if ($is_modification && isset($evaluation_existante['criteres'])) {
                                    $criteres_existants = $evaluation_existante['criteres'];
                                }
                                ?>

                                <?php foreach ($grading_grid as $critere): ?>
                                    <?php
                                    // Récupérer les valeurs existantes pour ce critère
                                    $valeur_existante = $criteres_existants[$critere['id']] ?? null;
                                    $note_existante = $valeur_existante['note'] ?? '';
                                    $commentaire_existant = $valeur_existante['commentaire'] ?? '';
                                    ?>

                                    <div class="critere-group">
                                        <div class="critere-header">
                                            <span class="critere-title"><?php echo htmlspecialchars($critere['label']); ?></span>
                                            <?php if ($critere['type'] === 'number'): ?>
                                                <span class="critere-points">/ <?php echo $critere['max'] ?? 20; ?> pts</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (isset($critere['description'])): ?>
                                            <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">
                                                <?php echo htmlspecialchars($critere['description']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($critere['type'] === 'number'): ?>
                                            <div style="margin-bottom: 15px;">
                                                <label for="critere_<?php echo $critere['id']; ?>">Note :</label>
                                                <input type="number"
                                                       id="critere_<?php echo $critere['id']; ?>"
                                                       name="critere_<?php echo $critere['id']; ?>"
                                                       class="note-input"
                                                       min="0"
                                                       max="<?php echo $critere['max'] ?? 20; ?>"
                                                       step="0.5"
                                                       value="<?php echo htmlspecialchars($note_existante); ?>"
                                                       required
                                                       onchange="calculateTotal()">
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <label for="commentaire_<?php echo $critere['id']; ?>">Commentaire :</label>
                                            <textarea id="commentaire_<?php echo $critere['id']; ?>"
                                                      name="commentaire_<?php echo $critere['id']; ?>"
                                                      class="commentaire-input"
                                                      placeholder="Commentaire détaillé pour ce critère..."><?php echo htmlspecialchars($commentaire_existant); ?></textarea>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Note totale calculée -->
                                <div class="note-totale" id="noteTotale" style="display: none;">
                                    <div>Note totale calculée :</div>
                                    <div class="note-display" id="noteDisplay">0.0</div>
                                    <div>/20</div>
                                </div>

                                <!-- Commentaire général -->
                                <div class="critere-group">
                                    <div class="critere-header">
                                        <span class="critere-title">💬 Commentaire général</span>
                                    </div>
                                    <textarea name="commentaire_general"
                                              class="commentaire-input"
                                              style="min-height: 120px;"
                                              placeholder="Commentaire général sur la copie, conseils, points forts et axes d'amélioration..."
                                              required><?php
                                              if ($is_modification && isset($evaluation_existante['commentaire_general'])) {
                                                  echo htmlspecialchars($evaluation_existante['commentaire_general']);
                                              }
                                              ?></textarea>
                                </div>

                                <!-- Actions -->
                                <div class="evaluation-actions">
                                    <?php if ($is_modification): ?>
                                        <button type="submit" class="btn-evaluer" onclick="return confirm('Êtes-vous sûr de vouloir modifier cette correction ?')">
                                            🔄 Modifier la correction
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn-evaluer" onclick="return confirm('Êtes-vous sûr de vouloir soumettre cette évaluation ?')">
                                            ✅ Soumettre l'évaluation
                                        </button>
                                    <?php endif; ?>
                                    <a href="dashboard/correcteur.php" class="btn btn-secondary">Annuler</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p>Aucune grille d'évaluation n'est définie pour ce concours.</p>
                                <p>Contactez l'administrateur pour configurer la grille d'évaluation.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script>
        // Calcul automatique de la note totale - CORRIGÉ
        function calculateTotal() {
            const form = document.getElementById('evaluationForm');
            if (!form) return;

            const grading_grid = <?php echo json_encode($grading_grid ?: []); ?>;
            let notes_data = [];

            grading_grid.forEach(critere => {
                if (critere.type === 'number') {
                    const input = document.getElementById('critere_' + critere.id);
                    const note = parseFloat(input.value) || 0;
                    const max = critere.max || 20;
                    const weight = critere.weight || 1;

                    notes_data.push({note: note, max: max, weight: weight});
                }
            });

            if (notes_data.length > 0) {
                let totalWeight = notes_data.reduce((sum, item) => sum + item.weight, 0);
                let noteFinale;

                // Si pas de poids définis (tous = 1), calcul simple
                if (totalWeight === notes_data.length) {
                    let totalSimple = notes_data.reduce((sum, item) => sum + item.note, 0);
                    let totalMaxSimple = notes_data.reduce((sum, item) => sum + item.max, 0);
                    noteFinale = (totalSimple / totalMaxSimple) * 20;
                } else {
                    // Calcul avec poids
                    let totalPondere = notes_data.reduce((sum, item) => sum + (item.note / item.max) * item.weight, 0);
                    noteFinale = (totalPondere / totalWeight) * 20;
                }

                document.getElementById('noteDisplay').textContent = noteFinale.toFixed(1);
                document.getElementById('noteTotale').style.display = 'block';
            }
        }

        // Validation du formulaire
        document.getElementById('evaluationForm')?.addEventListener('submit', function(e) {
            const commentaireGeneral = document.querySelector('textarea[name="commentaire_general"]').value.trim();
            if (commentaireGeneral.length < 10) {
                e.preventDefault();
                alert('Le commentaire général doit contenir au moins 10 caractères.');
                return false;
            }

            // Vérifier que toutes les notes sont remplies
            const noteInputs = document.querySelectorAll('.note-input');
            for (let input of noteInputs) {
                if (!input.value) {
                    e.preventDefault();
                    alert('Veuillez remplir toutes les notes.');
                    input.focus();
                    return false;
                }
            }
        });

        // Calcul initial si des valeurs sont déjà présentes
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
        });
    </script>
</body>
</html>