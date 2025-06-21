<?php
/**
 * Gestionnaire d'erreurs centralisé
 * Gère les erreurs, logs et exceptions de manière sécurisée
 */

class ErrorHandler {
    private static $logFile = null;
    
    public static function init() {
        // Définir le fichier de log
        self::$logFile = dirname(__DIR__) . '/logs/error.log';
        
        // Créer le dossier de logs s'il n'existe pas
        $logDir = dirname(self::$logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Configurer les gestionnaires d'erreurs
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
        
        // Configuration pour la production
        if (!defined('DEBUG') || !DEBUG) {
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            ini_set('error_log', self::$logFile);
        }
    }
    
    /**
     * Gestionnaire d'erreurs PHP
     */
    public static function handleError($severity, $message, $file, $line) {
        // Ne pas traiter les erreurs supprimées avec @
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = self::getErrorType($severity);
        $logMessage = "[{$errorType}] {$message} in {$file} on line {$line}";
        
        self::logError($logMessage);
        
        // En mode debug, afficher l'erreur
        if (defined('DEBUG') && DEBUG) {
            echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 4px;'>";
            echo "<strong>{$errorType}:</strong> {$message}<br>";
            echo "<small>File: {$file} | Line: {$line}</small>";
            echo "</div>";
        }
        
        return true;
    }
    
    /**
     * Gestionnaire d'exceptions non catchées
     */
    public static function handleException($exception) {
        $message = "Uncaught Exception: " . $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();
        
        $logMessage = "[EXCEPTION] {$message} in {$file} on line {$line}\nStack trace:\n{$trace}";
        
        self::logError($logMessage);
        
        // Affichage sécurisé pour l'utilisateur
        if (defined('DEBUG') && DEBUG) {
            echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 4px;'>";
            echo "<strong>Exception:</strong> " . htmlspecialchars($message) . "<br>";
            echo "<small>File: " . htmlspecialchars($file) . " | Line: {$line}</small>";
            echo "<pre style='margin-top: 10px; font-size: 12px;'>" . htmlspecialchars($trace) . "</pre>";
            echo "</div>";
        } else {
            echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 4px;'>";
            echo "<strong>Erreur:</strong> Une erreur inattendue s'est produite. Veuillez réessayer plus tard.";
            echo "</div>";
        }
    }
    
    /**
     * Gestionnaire d'erreurs fatales
     */
    public static function handleFatalError() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = $error['message'];
            $file = $error['file'];
            $line = $error['line'];
            
            $logMessage = "[FATAL] {$message} in {$file} on line {$line}";
            self::logError($logMessage);
            
            // Affichage sécurisé
            if (!(defined('DEBUG') && DEBUG)) {
                echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 4px;'>";
                echo "<strong>Erreur fatale:</strong> Le système a rencontré une erreur critique. Veuillez contacter l'administrateur.";
                echo "</div>";
            }
        }
    }
    
    /**
     * Log une erreur dans le fichier de log
     */
    public static function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $logEntry = "[{$timestamp}] IP: {$ip} | URI: {$uri} | {$message}\n";
        $logEntry .= "User-Agent: {$userAgent}\n";
        $logEntry .= str_repeat('-', 80) . "\n";
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log une action utilisateur
     */
    public static function logUserAction($userId, $action, $details = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $logEntry = "[{$timestamp}] USER_ACTION | User ID: {$userId} | IP: {$ip} | URI: {$uri} | Action: {$action}";
        if ($details) {
            $logEntry .= " | Details: {$details}";
        }
        $logEntry .= "\n";
        
        $userLogFile = dirname(self::$logFile) . '/user_actions.log';
        file_put_contents($userLogFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log une tentative de sécurité suspecte
     */
    public static function logSecurityEvent($event, $details = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $logEntry = "[{$timestamp}] SECURITY | IP: {$ip} | URI: {$uri} | Event: {$event}";
        if ($details) {
            $logEntry .= " | Details: {$details}";
        }
        $logEntry .= "\nUser-Agent: {$userAgent}\n";
        $logEntry .= str_repeat('=', 80) . "\n";
        
        $securityLogFile = dirname(self::$logFile) . '/security.log';
        file_put_contents($securityLogFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Obtient le type d'erreur en texte
     */
    private static function getErrorType($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'FATAL ERROR';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
            case E_STRICT:
                return 'STRICT';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'DEPRECATED';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Nettoie les anciens logs (à appeler périodiquement)
     */
    public static function cleanOldLogs($days = 30) {
        $logDir = dirname(self::$logFile);
        $files = glob($logDir . '/*.log');
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

// Initialiser le gestionnaire d'erreurs
ErrorHandler::init();
?>
