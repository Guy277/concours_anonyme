<?php
/**
 * API endpoint pour vérifier si un titre de concours existe déjà lors de la modification
 * Exclut le concours en cours de modification
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit();
}

// Récupération du titre et de l'ID du concours
$titre = trim($_POST['titre'] ?? '');
$concours_id = (int)($_POST['concours_id'] ?? 0);

if (empty($titre)) {
    echo json_encode(['error' => 'Titre manquant']);
    exit();
}

if ($concours_id <= 0) {
    echo json_encode(['error' => 'ID de concours invalide']);
    exit();
}

try {
    // Vérification si le titre existe déjà (sauf pour ce concours)
    $sql = "SELECT id FROM concours WHERE titre = ? AND id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$titre, $concours_id]);
    
    $exists = $stmt->rowCount() > 0;
    
    echo json_encode([
        'exists' => $exists,
        'titre' => $titre,
        'concours_id' => $concours_id,
        'message' => $exists ? 'Ce titre existe déjà' : 'Titre disponible'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la vérification']);
}
?> 