<?php
/**
 * Système d'anonymisation des copies
 * Gère la génération d'identifiants anonymes et la séparation des données
 */

class Anonymisation {
    private $conn;
    private $encryptionKey;
    private $iv;
    private $cipher = 'aes-256-cbc';
    
    public function __construct($conn) {
        $this->conn = $conn;
        // Utiliser la clé et l'IV définis dans la configuration
        if (!defined('ENCRYPTION_KEY') || !defined('ENCRYPTION_IV')) {
            throw new Exception("Les constantes de chiffrement ne sont pas définies.");
        }
        $this->encryptionKey = hex2bin(ENCRYPTION_KEY);
        $this->iv = hex2bin(ENCRYPTION_IV);
    }

    /**
     * Chiffre les données en utilisant AES-256-CBC.
     */
    public function encrypt($data) {
        if (!extension_loaded('openssl')) {
            throw new Exception('OpenSSL extension is not loaded.');
        }
        $encrypted = openssl_encrypt($data, $this->cipher, $this->encryptionKey, 0, $this->iv);
        return base64_encode($encrypted);
    }

    /**
     * Déchiffre les données en utilisant AES-256-CBC.
     */
    public function decrypt($data) {
        if (!extension_loaded('openssl')) {
            throw new Exception('OpenSSL extension is not loaded.');
        }
        $decodedData = base64_decode($data);
        return openssl_decrypt($decodedData, $this->cipher, $this->encryptionKey, 0, $this->iv);
    }
    
    /**
     * Génère un identifiant anonyme unique
     * Format: CONC-YYYY-XXXXX où:
     * - CONC: Préfixe fixe
     * - YYYY: Année en cours
     * - XXXXX: Identifiant unique basé sur timestamp + random
     */
    public function genererIdentifiantAnonyme($concours_id) {
        $annee = date('Y');
        $prefixe = "CONC-{$annee}-";

        // Génération d'un identifiant unique avec retry en cas de collision
        $max_attempts = 10;
        $attempt = 0;

        do {
            $attempt++;

            // Génère un identifiant basé sur timestamp + random pour éviter les collisions
            $timestamp = substr(time(), -4); // 4 derniers chiffres du timestamp
            $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT); // 3 chiffres aléatoires
            $identifiant = $prefixe . $timestamp . $random;

            // Vérifie l'unicité en base
            $sql = "SELECT COUNT(*) as count FROM copies WHERE identifiant_anonyme = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$identifiant]);
            $exists = $stmt->fetchColumn() > 0;

            if (!$exists) {
                return $identifiant;
            }

            // Attendre un peu avant de réessayer pour éviter les collisions
            usleep(1000); // 1ms

        } while ($attempt < $max_attempts);

        // Fallback avec UUID si toutes les tentatives échouent
        $uuid_part = substr(str_replace('-', '', uniqid()), 0, 7);
        return $prefixe . strtoupper($uuid_part);
    }
    
    /**
     * Dépose une copie de manière anonyme
     */
    public function deposerCopie($concours_id, $candidat_id, $fichier_path) {
        try {
            // Démarrer une transaction pour assurer la cohérence
            $this->conn->beginTransaction();

            // Vérifications préliminaires
            if (!file_exists($fichier_path)) {
                throw new Exception("Le fichier spécifié n'existe pas");
            }

            // Vérifier que le candidat existe
            $sql = "SELECT id FROM utilisateurs WHERE id = ? AND role = 'candidat'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$candidat_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Candidat introuvable");
            }

            // Vérifier que le concours existe et est actif
            $sql = "SELECT id FROM concours WHERE id = ? AND date_fin >= CURDATE()";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$concours_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Concours introuvable ou expiré");
            }

            // Génère l'identifiant anonyme
            $identifiant_anonyme = $this->genererIdentifiantAnonyme($concours_id);

            // Chiffre uniquement le chemin du fichier (données sensibles)
            $encrypted_fichier_path = $this->encrypt($fichier_path);

            // Insère la copie dans la base de données
            $sql = "INSERT INTO copies (concours_id, candidat_id, identifiant_anonyme, fichier_path, date_depot, statut)
                    VALUES (?, ?, ?, ?, NOW(), 'en_attente')";
            $stmt = $this->conn->prepare($sql);

            if (!$stmt->execute([
                $concours_id,
                $candidat_id,
                $identifiant_anonyme,
                $encrypted_fichier_path
            ])) {
                throw new Exception("Erreur lors de l'insertion en base");
            }

            $copie_id = $this->conn->lastInsertId();

            // Log l'action de dépôt de copie
            $this->logAudit($candidat_id, 'Depot Copie', "Copie ID: {$copie_id}, Identifiant Anonyme: {$identifiant_anonyme}");

            // Valider la transaction
            $this->conn->commit();

            return [
                'success' => true,
                'identifiant_anonyme' => $identifiant_anonyme,
                'copie_id' => $copie_id
            ];

        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->conn->rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère les informations d'une copie sans révéler l'identité du candidat
     */
    public function getCopieAnonyme($copie_id) {
        $sql = "SELECT c.*, co.titre as concours_titre
                FROM copies c
                INNER JOIN concours co ON c.concours_id = co.id
                WHERE c.id = :copie_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['copie_id' => $copie_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Déchiffrer uniquement le chemin du fichier (candidat_id reste accessible pour les FK)
            $result['fichier_path'] = $this->decrypt($result['fichier_path']);
            // Log l'accès à la copie anonyme
            $this->logAudit($_SESSION['user_id'] ?? 0, 'Acces Copie Anonyme', "Copie ID: {$copie_id}, Identifiant Anonyme: {$result['identifiant_anonyme']}");
        }
        return $result;
    }
    
    /**
     * Vérifie si un correcteur a accès à une copie
     */
    public function verifierAccesCorrecteur($copie_id, $correcteur_id) {
        $sql = "SELECT 1 FROM attributions_copies 
                WHERE copie_id = :copie_id AND correcteur_id = :correcteur_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'copie_id' => $copie_id,
            'correcteur_id' => $correcteur_id
        ]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Attribue une copie à un correcteur
     */
    public function attribuerCopie($copie_id, $correcteur_id) {
        // Vérifie si l'attribution existe déjà
        $sql = "SELECT 1 FROM attributions_copies 
                WHERE copie_id = :copie_id AND correcteur_id = :correcteur_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'copie_id' => $copie_id,
            'correcteur_id' => $correcteur_id
        ]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'Cette copie est déjà attribuée à ce correcteur'];
        }
        
        // Crée l'attribution
        $sql = "INSERT INTO attributions_copies (copie_id, correcteur_id, date_attribution) 
                VALUES (:copie_id, :correcteur_id, NOW())";
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt->execute([
            'copie_id' => $copie_id,
            'correcteur_id' => $correcteur_id
        ])) {
            // Met à jour le statut de la copie
            $sql = "UPDATE copies SET statut = 'en_correction' WHERE id = :copie_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['copie_id' => $copie_id]);
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => $stmt->errorInfo()[2] ?? 'Erreur inconnue'];
    }

    /**
     * Déchiffre un chemin de fichier
     */
    public function dechiffrerChemin($chemin_chiffre) {
        try {
            return $this->decrypt($chemin_chiffre);
        } catch (Exception $e) {
            error_log("Erreur déchiffrement: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enregistre une entrée dans le journal d'audit.
     */
    public function logAudit($userId, $action, $details = null) {
        $sql = "INSERT INTO audit_logs (user_id, action, details) VALUES (:user_id, :action, :details)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details
        ]);
    }
}