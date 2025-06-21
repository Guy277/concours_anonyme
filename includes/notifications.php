<?php
/**
 * Système de gestion des notifications
 * Gère l'affichage et la gestion des notifications utilisateur
 */

class NotificationManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Récupère les notifications d'un utilisateur
     */
    public function getNotifications($user_id) {
        try {
            $sql = "SELECT notifications_json FROM utilisateurs WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['notifications_json']) {
                return json_decode($result['notifications_json'], true) ?: [];
            }
            
            return [];
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupère les notifications non lues
     */
    public function getUnreadNotifications($user_id) {
        $notifications = $this->getNotifications($user_id);
        return array_filter($notifications, function($notif) {
            return !($notif['lu'] ?? false);
        });
    }
    
    /**
     * Marque une notification comme lue
     */
    public function markAsRead($user_id, $notification_index) {
        try {
            $notifications = $this->getNotifications($user_id);
            
            if (isset($notifications[$notification_index])) {
                $notifications[$notification_index]['lu'] = true;
                
                $sql = "UPDATE utilisateurs SET notifications_json = ? WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([json_encode($notifications), $user_id]);
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Marque toutes les notifications comme lues
     */
    public function markAllAsRead($user_id) {
        try {
            $notifications = $this->getNotifications($user_id);
            
            foreach ($notifications as &$notif) {
                $notif['lu'] = true;
            }
            
            $sql = "UPDATE utilisateurs SET notifications_json = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([json_encode($notifications), $user_id]);
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Ajoute une nouvelle notification
     */
    public function addNotification($user_id, $type, $message, $data = []) {
        try {
            $notifications = $this->getNotifications($user_id);
            
            $notification = [
                'type' => $type,
                'message' => $message,
                'date' => date('Y-m-d H:i:s'),
                'lu' => false
            ];
            
            // Ajouter les données supplémentaires
            $notification = array_merge($notification, $data);
            
            // Ajouter la nouvelle notification
            $notifications[] = $notification;
            
            // Garder seulement les 10 dernières notifications
            $notifications = array_slice($notifications, -10);
            
            $sql = "UPDATE utilisateurs SET notifications_json = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([json_encode($notifications), $user_id]);
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Supprime une notification
     */
    public function deleteNotification($user_id, $notification_index) {
        try {
            $notifications = $this->getNotifications($user_id);
            
            if (isset($notifications[$notification_index])) {
                unset($notifications[$notification_index]);
                $notifications = array_values($notifications); // Réindexer
                
                $sql = "UPDATE utilisateurs SET notifications_json = ? WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([json_encode($notifications), $user_id]);
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Génère le HTML pour afficher les notifications
     */
    public function renderNotifications($user_id) {
        $notifications = $this->getNotifications($user_id);
        $unread_count = count($this->getUnreadNotifications($user_id));
        
        if (empty($notifications)) {
            return '';
        }
        
        // Inverser le tableau pour l'affichage, mais conserver les clés originales
        $notifications_affiches = array_reverse($notifications, true);
        
        $html = '<div class="notifications-container">';
        $html .= '<div class="notifications-header">';
        $html .= '<h3>🔔 Notifications';
        if ($unread_count > 0) {
            $html .= ' <span class="notification-badge">' . $unread_count . '</span>';
        }
        $html .= '</h3>';
        if ($unread_count > 0) {
            $html .= '<button onclick="markAllNotificationsAsRead()" class="btn btn-small">Tout marquer comme lu</button>';
        }
        $html .= '</div>';
        
        $html .= '<div class="notifications-list">';
        foreach ($notifications_affiches as $index => $notif) { // Utiliser le tableau inversé
            $read_class = ($notif['lu'] ?? false) ? 'notification-read' : 'notification-unread';
            $type_icon = $this->getNotificationIcon($notif['type']);
            
            $html .= '<div class="notification-item ' . $read_class . '" data-index="' . $index . '">';
            $html .= '<div class="notification-icon">' . $type_icon . '</div>';
            $html .= '<div class="notification-content">';
            $html .= '<div class="notification-message">' . htmlspecialchars($notif['message']) . '</div>';
            
            if (isset($notif['commentaire_admin']) && $notif['commentaire_admin']) {
                $html .= '<div class="notification-details">';
                $html .= '<strong>Commentaire :</strong> ' . nl2br(htmlspecialchars($notif['commentaire_admin']));
                $html .= '</div>';
            }
            
            $html .= '<div class="notification-date">' . date('d/m/Y H:i', strtotime($notif['date'])) . '</div>';
            $html .= '</div>';
            
            if (!($notif['lu'] ?? false)) {
                $html .= '<button onclick="markNotificationAsRead(' . $index . ')" class="notification-mark-read">✓</button>';
            }

            $html .= '<button onclick="deleteNotification(' . $index . ')" class="notification-delete-btn" title="Supprimer la notification">&times;</button>';
            
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Retourne l'icône selon le type de notification
     */
    private function getNotificationIcon($type) {
        switch ($type) {
            case 'correction_rejetee':
                return '❌';
            case 'correction_validee':
                return '✅';
            case 'copie_attribuee':
                return '📝';
            case 'resultat_disponible':
                return '📊';
            default:
                return '🔔';
        }
    }
}
?>
