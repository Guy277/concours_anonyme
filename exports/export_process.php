<?php
/**
 * Script de traitement des exports
 * Génère les fichiers Excel, PDF et CSV
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/note_calculator.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

// Récupération des paramètres
$format = $_GET['format'] ?? '';
$concours_id = (int)($_GET['concours_id'] ?? 0);
$include_personal_data = (bool)($_GET['include_personal_data'] ?? false);
$include_comments = (bool)($_GET['include_comments'] ?? false);

if (!in_array($format, ['excel', 'pdf', 'csv'])) {
    header('Location: resultats.php?error=format_invalide');
    exit();
}

try {
    // Construction de la requête selon les options
    $sql = "SELECT 
                cp.identifiant_anonyme,
                co.titre as concours_titre,
                cp.date_depot,
                cor.date_correction,
                cor.evaluation_data_json,
                CONCAT(u_correcteur.prenom, ' ', u_correcteur.nom) as correcteur_nom";
    
    if ($include_personal_data) {
        $sql .= ", CONCAT(u_candidat.prenom, ' ', u_candidat.nom) as candidat_nom,
                  u_candidat.email as candidat_email";
    }
    
    $sql .= " FROM corrections cor
              INNER JOIN copies cp ON cor.copie_id = cp.id
              INNER JOIN concours co ON cp.concours_id = co.id
              INNER JOIN utilisateurs u_correcteur ON cor.correcteur_id = u_correcteur.id";
    
    if ($include_personal_data) {
        $sql .= " INNER JOIN utilisateurs u_candidat ON cp.candidat_id = u_candidat.id";
    }
    
    $sql .= " WHERE cp.statut = 'corrigee'";
    
    if ($concours_id > 0) {
        $sql .= " AND cp.concours_id = ?";
    }
    
    $sql .= " ORDER BY co.titre, cp.date_depot";
    
    $stmt = $conn->prepare($sql);
    if ($concours_id > 0) {
        $stmt->execute([$concours_id]);
    } else {
        $stmt->execute();
    }
    
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($resultats)) {
        header('Location: resultats.php?error=aucun_resultat');
        exit();
    }
    
    // Calculer les notes avec la classe unifiée
    foreach ($resultats as &$resultat) {
        if ($resultat['evaluation_data_json']) {
            $evaluation_data = json_decode($resultat['evaluation_data_json'], true);
            $resultat['note_finale'] = NoteCalculator::calculerNoteFinale($evaluation_data);
            $resultat['commentaire_general'] = $evaluation_data['commentaire_general'] ?? '';
        } else {
            $resultat['note_finale'] = 0;
            $resultat['commentaire_general'] = '';
        }
    }
    
    // Génération du nom de fichier
    $timestamp = date('Y-m-d_H-i-s');
    $concours_name = '';
    
    if ($concours_id > 0) {
        $sql = "SELECT titre FROM concours WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$concours_id]);
        $concours_data = $stmt->fetch();
        $concours_name = '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $concours_data['titre']);
    }
    
    $filename_base = "resultats_concours{$concours_name}_{$timestamp}";
    
    // Génération selon le format
    switch ($format) {
        case 'csv':
            generateCSV($resultats, $filename_base, $include_personal_data, $include_comments);
            break;
        case 'excel':
            generateExcel($resultats, $filename_base, $include_personal_data, $include_comments);
            break;
        case 'pdf':
            generatePDF($resultats, $filename_base, $include_personal_data, $include_comments);
            break;
    }
    
} catch (Exception $e) {
    header('Location: resultats.php?error=' . urlencode($e->getMessage()));
    exit();
}

/**
 * Génération CSV
 */
function generateCSV($data, $filename, $include_personal_data, $include_comments) {
    $filename .= '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes
    $headers = ['ID Anonyme', 'Concours', 'Date Dépôt', 'Date Correction', 'Note', 'Correcteur'];
    
    if ($include_personal_data) {
        $headers[] = 'Candidat';
        $headers[] = 'Email Candidat';
    }
    
    if ($include_comments) {
        $headers[] = 'Commentaire';
    }
    
    fputcsv($output, $headers, ';');
    
    // Données
    foreach ($data as $row) {
        $csv_row = [
            $row['identifiant_anonyme'],
            $row['concours_titre'],
            date('d/m/Y H:i', strtotime($row['date_depot'])),
            date('d/m/Y H:i', strtotime($row['date_correction'])),
            number_format($row['note_finale'], 1) . '/20',
            $row['correcteur_nom']
        ];
        
        if ($include_personal_data) {
            $csv_row[] = $row['candidat_nom'] ?? '';
            $csv_row[] = $row['candidat_email'] ?? '';
        }
        
        if ($include_comments) {
            $csv_row[] = $row['commentaire_general'] ?? '';
        }
        
        fputcsv($output, $csv_row, ';');
    }
    
    fclose($output);
    exit();
}

/**
 * Génération Excel (version simplifiée - fallback vers CSV)
 */
function generateExcel($data, $filename, $include_personal_data, $include_comments) {
    // Pour l'instant, on utilise CSV comme fallback
    // PhpSpreadsheet nécessite une installation et configuration complexe
    generateCSV($data, $filename, $include_personal_data, $include_comments);
}

/**
 * Génération PDF (version simplifiée - fallback vers CSV)
 */
function generatePDF($data, $filename, $include_personal_data, $include_comments) {
    // Pour l'instant, on utilise CSV comme fallback
    // TCPDF nécessite une installation et configuration complexe
    generateCSV($data, $filename, $include_personal_data, $include_comments);
}
?>
