<?php
/**
 * Règles de soumission conformes aux standards internationaux
 * Gère les phases de soumission et les autorisations de modification
 */

class SubmissionRules {
    private $conn;
    
    // Constantes de configuration (en minutes)
    const GRACE_PERIOD_MINUTES = 30; // Délai de grâce après date limite
    const MAX_MODIFICATIONS_GRACE = 1; // Nombre max de modifications en délai de grâce
    
    public function __construct($conn) {
        $this->conn = $conn;
        // Créer la table d'historique si elle n'existe pas
        $this->createHistoryTableIfNotExists();
    }
    
    /**
     * Détermine le statut de soumission d'une copie
     */
    public function getSubmissionStatus($copie_id) {
        try {
            // Vérifier d'abord si la table historique existe
            $sql = "SHOW TABLES LIKE 'historique_modifications'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $table_exists = $stmt->fetch();

            if ($table_exists) {
                $sql = "SELECT
                            c.*,
                            co.date_fin,
                            co.titre as concours_titre,
                            COUNT(h.id) as nb_modifications
                        FROM copies c
                        INNER JOIN concours co ON c.concours_id = co.id
                        LEFT JOIN historique_modifications h ON c.id = h.copie_id
                        WHERE c.id = ?
                        GROUP BY c.id";
            } else {
                // Fallback sans historique
                $sql = "SELECT
                            c.*,
                            co.date_fin,
                            co.titre as concours_titre,
                            0 as nb_modifications
                        FROM copies c
                        INNER JOIN concours co ON c.concours_id = co.id
                        WHERE c.id = ?";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$copie_id]);
            $copie = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$copie) {
                return ['status' => 'NOT_FOUND'];
            }
        } catch (Exception $e) {
            // En cas d'erreur, fallback simple
            $sql = "SELECT
                        c.*,
                        co.date_fin,
                        co.titre as concours_titre,
                        0 as nb_modifications
                    FROM copies c
                    INNER JOIN concours co ON c.concours_id = co.id
                    WHERE c.id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$copie_id]);
            $copie = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$copie) {
                return ['status' => 'NOT_FOUND'];
            }
        }
        
        $now = new DateTime();
        $deadline = new DateTime($copie['date_fin']);
        $grace_end = clone $deadline;
        $grace_end->add(new DateInterval('PT' . self::GRACE_PERIOD_MINUTES . 'M'));
        
        // Déterminer la phase
        if ($now <= $deadline) {
            return [
                'status' => 'OPEN',
                'phase' => 'SUBMISSION',
                'can_modify' => true,
                'can_replace' => true,
                'message' => 'Vous pouvez modifier votre copie librement jusqu\'à la date limite.',
                'deadline' => $deadline,
                'time_remaining' => $deadline->diff($now)
            ];
        } elseif ($now <= $grace_end) {
            $can_modify = $copie['nb_modifications'] < self::MAX_MODIFICATIONS_GRACE;
            return [
                'status' => 'GRACE_PERIOD',
                'phase' => 'GRACE',
                'can_modify' => $can_modify,
                'can_replace' => $can_modify,
                'message' => $can_modify 
                    ? 'Délai de grâce : vous pouvez encore modifier votre copie UNE SEULE FOIS.'
                    : 'Délai de grâce expiré : vous avez déjà utilisé votre modification.',
                'deadline' => $deadline,
                'grace_end' => $grace_end,
                'modifications_used' => $copie['nb_modifications'],
                'modifications_remaining' => max(0, self::MAX_MODIFICATIONS_GRACE - $copie['nb_modifications'])
            ];
        } else {
            return [
                'status' => 'LOCKED',
                'phase' => 'LOCKED',
                'can_modify' => false,
                'can_replace' => false,
                'message' => 'La période de soumission est terminée. Votre copie est verrouillée.',
                'deadline' => $deadline,
                'grace_end' => $grace_end
            ];
        }
    }
    
    /**
     * Vérifie si une modification est autorisée
     */
    public function canModify($copie_id, $user_id) {
        // Vérifier que l'utilisateur est propriétaire de la copie
        $sql = "SELECT candidat_id FROM copies WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$copie_id]);
        $owner = $stmt->fetchColumn();
        
        if ($owner != $user_id) {
            return [
                'allowed' => false,
                'reason' => 'UNAUTHORIZED',
                'message' => 'Vous n\'êtes pas autorisé à modifier cette copie.'
            ];
        }
        
        // Vérifier le statut de soumission
        $status = $this->getSubmissionStatus($copie_id);
        
        if (!$status['can_modify']) {
            return [
                'allowed' => false,
                'reason' => $status['status'],
                'message' => $status['message']
            ];
        }
        
        return [
            'allowed' => true,
            'status' => $status
        ];
    }
    
    /**
     * Enregistre une modification dans l'historique
     */
    public function logModification($copie_id, $user_id, $ancien_fichier, $nouveau_fichier, $raison = 'Modification candidat') {
        try {
            // Créer la table d'historique si elle n'existe pas
            $this->createHistoryTableIfNotExists();

            $sql = "INSERT INTO historique_modifications
                    (copie_id, user_id, ancien_fichier, nouveau_fichier, raison, date_modification)
                    VALUES (?, ?, ?, ?, ?, NOW())";

            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$copie_id, $user_id, $ancien_fichier, $nouveau_fichier, $raison]);
        } catch (Exception $e) {
            // En cas d'erreur, on continue sans enregistrer l'historique
            if (defined('DEBUG') && DEBUG) {
                error_log("Erreur lors de l'enregistrement de l'historique: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Obtient l'historique des modifications d'une copie
     */
    public function getModificationHistory($copie_id) {
        try {
            // Vérifier si la table existe
            $sql = "SHOW TABLES LIKE 'historique_modifications'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $table_exists = $stmt->fetch();

            if (!$table_exists) {
                return []; // Retourner un tableau vide si la table n'existe pas
            }

            $sql = "SELECT
                        h.*,
                        CONCAT(u.prenom, ' ', u.nom) as user_name
                    FROM historique_modifications h
                    LEFT JOIN utilisateurs u ON h.user_id = u.id
                    WHERE h.copie_id = ?
                    ORDER BY h.date_modification DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$copie_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // En cas d'erreur, retourner un tableau vide
            return [];
        }
    }
    
    /**
     * Génère un message d'avertissement pour les modifications
     */
    public function getModificationWarning($copie_id) {
        $status = $this->getSubmissionStatus($copie_id);
        
        switch ($status['phase']) {
            case 'SUBMISSION':
                return [
                    'type' => 'info',
                    'title' => 'Modification autorisée',
                    'message' => 'Vous pouvez modifier votre copie librement jusqu\'à la date limite.',
                    'show_countdown' => true
                ];
                
            case 'GRACE':
                if ($status['can_modify']) {
                    return [
                        'type' => 'warning',
                        'title' => 'Délai de grâce - Dernière chance',
                        'message' => 'ATTENTION : Vous êtes en délai de grâce. Vous ne pouvez modifier votre copie qu\'UNE SEULE FOIS.',
                        'show_countdown' => true,
                        'require_confirmation' => true
                    ];
                } else {
                    return [
                        'type' => 'error',
                        'title' => 'Modification impossible',
                        'message' => 'Vous avez déjà utilisé votre modification en délai de grâce.',
                        'show_countdown' => false
                    ];
                }
                
            case 'LOCKED':
                return [
                    'type' => 'error',
                    'title' => 'Copie verrouillée',
                    'message' => 'La période de soumission est terminée. Votre copie ne peut plus être modifiée.',
                    'show_countdown' => false
                ];
                
            default:
                return [
                    'type' => 'error',
                    'title' => 'Statut inconnu',
                    'message' => 'Impossible de déterminer le statut de votre copie.',
                    'show_countdown' => false
                ];
        }
    }
    
    /**
     * Crée la table d'historique si elle n'existe pas
     */
    private function createHistoryTableIfNotExists() {
        try {
            // Vérifier si la table existe déjà
            $sql = "SHOW TABLES LIKE 'historique_modifications'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $table_exists = $stmt->fetch();

            if (!$table_exists) {
                $sql = "CREATE TABLE historique_modifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    copie_id INT NOT NULL,
                    user_id INT NOT NULL,
                    ancien_fichier TEXT COMMENT 'Chemin de l\'ancien fichier (chiffré)',
                    nouveau_fichier TEXT COMMENT 'Chemin du nouveau fichier (chiffré)',
                    raison VARCHAR(255) DEFAULT 'Modification candidat',
                    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (copie_id) REFERENCES copies(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
                    INDEX idx_copie_date (copie_id, date_modification),
                    INDEX idx_user_date (user_id, date_modification)
                )";

                $this->conn->exec($sql);

                // Log de la création de table
                if (defined('DEBUG') && DEBUG) {
                    error_log("Table historique_modifications créée automatiquement");
                }
            }
        } catch (Exception $e) {
            // En cas d'erreur, on continue sans historique
            if (defined('DEBUG') && DEBUG) {
                error_log("Erreur création table historique_modifications: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Obtient les statistiques de soumission pour un concours
     */
    public function getSubmissionStats($concours_id) {
        $sql = "SELECT 
                    COUNT(*) as total_copies,
                    COUNT(CASE WHEN c.date_depot <= co.date_fin THEN 1 END) as soumises_a_temps,
                    COUNT(CASE WHEN c.date_depot > co.date_fin THEN 1 END) as soumises_en_retard,
                    AVG(CASE WHEN h.nb_modifications IS NOT NULL THEN h.nb_modifications ELSE 0 END) as avg_modifications
                FROM copies c
                INNER JOIN concours co ON c.concours_id = co.id
                LEFT JOIN (
                    SELECT copie_id, COUNT(*) as nb_modifications
                    FROM historique_modifications
                    GROUP BY copie_id
                ) h ON c.id = h.copie_id
                WHERE c.concours_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$concours_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
