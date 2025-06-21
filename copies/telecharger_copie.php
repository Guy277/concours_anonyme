<?php
/**
 * Script de téléchargement sécurisé pour les copies des candidats
 */

// Debug temporaire
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../includes/config.php';
require_once '../includes/anonymisation.php';

// Vérification de l'authentification et du rôle candidat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidat') {
    http_response_code(403);
    die('Accès non autorisé');
}

$user_id = $_SESSION['user_id'];

// Vérification des paramètres
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Paramètres invalides');
}

$copie_id = (int)$_GET['id'];
$action = $_GET['action'] ?? 'download'; // 'view' ou 'download'

try {
    // Vérification que la copie existe et appartient au candidat
    $sql = "SELECT c.*, co.titre as concours_titre
            FROM copies c
            INNER JOIN concours co ON c.concours_id = co.id
            WHERE c.id = ? AND c.candidat_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$copie_id, $user_id]);
    $copie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$copie) {
        http_response_code(404);
        die('Copie introuvable ou accès non autorisé');
    }
    
    // Récupération du fichier via le système d'anonymisation (même méthode que admin/correcteur)
    $anonymisation = new Anonymisation($conn);
    $copie_anonyme = $anonymisation->getCopieAnonyme($copie_id);

    if (!$copie_anonyme || !isset($copie_anonyme['fichier_path'])) {
        http_response_code(404);
        die('Fichier de copie introuvable');
    }

    $fichier_path = $copie_anonyme['fichier_path'];

    // Vérification que le fichier existe
    if (!file_exists($fichier_path)) {
        http_response_code(404);
        die('Fichier physique introuvable');
    }
    
    // Détermination du type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $fichier_path);
    finfo_close($finfo);
    
    // Nom du fichier pour le téléchargement
    $nom_fichier = $copie['identifiant_anonyme'] . '_' . basename($fichier_path);
    
    // Headers selon l'action
    if ($action === 'view') {
        // Pour visualisation dans le navigateur
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . $nom_fichier . '"');
    } else {
        // Pour téléchargement
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
    $anonymisation->logAudit($user_id, $action_log . ' Copie Candidat', 
        "Copie ID: {$copie_id}, Fichier: " . basename($fichier_path));
    
    exit();
    
} catch (PDOException $e) {
    http_response_code(500);
    die('Erreur de base de données');
} catch (Exception $e) {
    http_response_code(500);
    die('Erreur serveur: ' . $e->getMessage());
}
?>
