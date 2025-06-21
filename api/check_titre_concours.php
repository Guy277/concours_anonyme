<?php
/**
 * API endpoint pour vérifier si un titre de concours existe déjà
 * Utilisé pour la validation en temps réel côté client
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

// Récupération du titre
$titre = trim($_POST['titre'] ?? '');

if (empty($titre)) {
    echo json_encode(['error' => 'Titre manquant']);
    exit();
}

try {
    // Vérification si le titre existe déjà
    $sql = "SELECT id FROM concours WHERE titre = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$titre]);
    
    $exists = $stmt->rowCount() > 0;
    
    echo json_encode([
        'exists' => $exists,
        'titre' => $titre,
        'message' => $exists ? 'Ce titre existe déjà' : 'Titre disponible'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la vérification']);
}
?> 