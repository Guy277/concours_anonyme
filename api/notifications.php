<?php
/**
 * API pour la gestion des notifications
 * Permet de marquer les notifications comme lues
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/notifications.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

// Vérification de la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

// Récupération des données POST
$action = $_POST['action'] ?? null;
$index = isset($_POST['index']) ? (int)$_POST['index'] : null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action manquante']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notificationManager = new NotificationManager($conn);

try {
    switch ($action) {
        case 'mark_read':
            if ($index === null) {
                throw new Exception('Index de notification manquant');
            }
            $result = $notificationManager->markAsRead($user_id, $index);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue']);
            } else {
                throw new Exception('Impossible de marquer la notification comme lue');
            }
            break;
            
        case 'mark_all_read':
            $result = $notificationManager->markAllAsRead($user_id);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
            } else {
                throw new Exception('Impossible de marquer toutes les notifications comme lues');
            }
            break;
            
        case 'delete':
            if ($index === null) {
                throw new Exception('Index de notification manquant');
            }
            $result = $notificationManager->deleteNotification($user_id, $index);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Notification supprimée']);
            } else {
                throw new Exception('Impossible de supprimer la notification');
            }
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
