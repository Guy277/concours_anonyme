<?php
/**
 * Script de téléchargement sécurisé - Version racine
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/anonymisation.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Accès non autorisé');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Vérification des paramètres
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Paramètres invalides');
}

$copie_id = (int)$_GET['id'];
$action = $_GET['action'] ?? 'download';

try {
    // Vérification selon le rôle
    if ($user_role === 'candidat') {
        // Pour les candidats : vérifier que la copie leur appartient
        $sql = "SELECT c.*, co.titre as concours_titre
                FROM copies c
                INNER JOIN concours co ON c.concours_id = co.id
                WHERE c.id = ? AND c.candidat_id = ?";
        $params = [$copie_id, $user_id];
    } else {
        // Pour les admins/correcteurs : accès à toutes les copies
        $sql = "SELECT c.*, co.titre as concours_titre
                FROM copies c
                INNER JOIN concours co ON c.concours_id = co.id
                WHERE c.id = ?";
        $params = [$copie_id];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $copie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$copie) {
        http_response_code(404);
        die('Copie introuvable ou accès non autorisé');
    }
    
    // Déchiffrement du chemin du fichier
    $anonymisation = new Anonymisation($conn);
    $fichier_path = $anonymisation->decrypt($copie['fichier_path']);
    
    if (!$fichier_path || !file_exists($fichier_path)) {
        http_response_code(404);
        die('Fichier physique introuvable: ' . ($fichier_path ?? 'NULL'));
    }
    
    // Détermination du type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $fichier_path);
    finfo_close($finfo);
    
    // Nom du fichier pour le téléchargement
    $nom_fichier = $copie['identifiant_anonyme'] . '_' . basename($fichier_path);
    
    // Headers selon l'action
    if ($action === 'view') {
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . $nom_fichier . '"');
    } else {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
    }
    
    // Headers communs
    header('Content-Length: ' . filesize($fichier_path));
    header('Cache-Control: private, must-revalidate');
    header('Pragma: private');
    header('Expires: 0');
    
    // Nettoyage du buffer de sortie
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Lecture et envoi du fichier
    readfile($fichier_path);
    
    // Log de l'audit
    $action_log = ($action === 'view') ? 'Consultation' : 'Téléchargement';
    $anonymisation->logAudit($user_id, $action_log . ' Fichier', 
        "Copie ID: {$copie_id}, Fichier: " . basename($fichier_path));
    
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    die('Erreur: ' . $e->getMessage());
}
?>
