<?php
/**
 * Classe pour le calcul unifié des notes selon la logique :
 * (total_notes / total_max) * 20
 */
class NoteCalculator {
    
    /**
     * Calcule la note finale d'une copie basée sur les critères d'évaluation
     * 
     * @param array $evaluation_data Les données d'évaluation (JSON décodé)
     * @return float La note finale sur 20, arrondie à 2 décimales
     */
    public static function calculerNoteFinale($evaluation_data) {
        if (!is_array($evaluation_data)) {
            return 0;
        }
        
        // Si les données contiennent déjà une note_totale calculée, la retourner
        if (isset($evaluation_data['note_totale']) && is_numeric($evaluation_data['note_totale'])) {
            return round($evaluation_data['note_totale'], 2);
        }
        
        // Calculer la note basée sur les critères
        if (isset($evaluation_data['criteres']) && is_array($evaluation_data['criteres'])) {
            $total_notes = 0;
            $total_max = 0;
            
            foreach ($evaluation_data['criteres'] as $critere) {
                if (isset($critere['note']) && isset($critere['max']) && 
                    is_numeric($critere['note']) && is_numeric($critere['max'])) {
                    $total_notes += $critere['note'];
                    $total_max += $critere['max'];
                }
            }
            
            if ($total_max > 0) {
                return round(($total_notes / $total_max) * 20, 2);
            }
        }
        
        return 0;
    }
    
    /**
     * Calcule la note moyenne d'un ensemble de copies
     * 
     * @param array $copies Array de copies avec leurs notes
     * @return float La note moyenne sur 20, arrondie à 2 décimales
     */
    public static function calculerNoteMoyenne($copies) {
        if (empty($copies)) {
            return 0;
        }
        
        $total_notes = 0;
        $count_notes = 0;
        
        foreach ($copies as $copie) {
            $note = self::getNoteFromCopie($copie);
            if ($note > 0) {
                $total_notes += $note;
                $count_notes++;
            }
        }
        
        return $count_notes > 0 ? round($total_notes / $count_notes, 2) : 0;
    }
    
    /**
     * Extrait la note d'une copie selon différents formats possibles
     * 
     * @param array $copie Les données de la copie
     * @return float La note sur 20
     */
    public static function getNoteFromCopie($copie) {
        // Priorité 1 : note_finale dans la table copies
        if (isset($copie['note_finale']) && is_numeric($copie['note_finale'])) {
            return round($copie['note_finale'], 2);
        }

        // Priorité 2 : note_totale dans evaluation_data_json
        if (isset($copie['evaluation_data_json'])) {
            $evaluation_data = json_decode($copie['evaluation_data_json'], true);
            if (isset($evaluation_data['note_totale']) && is_numeric($evaluation_data['note_totale'])) {
                return round($evaluation_data['note_totale'], 2);
            }
            
            // Priorité 3 : calculer à partir des critères
                return self::calculerNoteFinale($evaluation_data);
        }
        
        // Priorité 4 : note_totale directe
        if (isset($copie['note_totale']) && is_numeric($copie['note_totale'])) {
            return round($copie['note_totale'], 2);
        }
        
        // Priorité 5 : note directe
        if (isset($copie['note']) && is_numeric($copie['note'])) {
            return round($copie['note'], 2);
        }

        return 0;
    }
    
    /**
     * Met à jour la note dans les données d'évaluation
     * 
     * @param array $evaluation_data Les données d'évaluation
     * @return array Les données d'évaluation avec la note mise à jour
     */
    public static function mettreAJourNote($evaluation_data) {
        if (!is_array($evaluation_data)) {
            return $evaluation_data;
        }
        
        $note_calculee = self::calculerNoteFinale($evaluation_data);
        $evaluation_data['note_totale'] = $note_calculee;
        
        return $evaluation_data;
    }
    
    /**
     * Valide la cohérence des notes dans une évaluation
     * 
     * @param array $evaluation_data Les données d'évaluation
     * @return array Array avec 'valid' => bool et 'message' => string
     */
    public static function validerEvaluation($evaluation_data) {
        if (!is_array($evaluation_data)) {
            return ['valid' => false, 'message' => 'Données d\'évaluation invalides'];
        }
        
        if (!isset($evaluation_data['criteres']) || !is_array($evaluation_data['criteres'])) {
            return ['valid' => false, 'message' => 'Aucun critère trouvé'];
        }
        
        $total_notes = 0;
        $total_max = 0;
        $criteres_valides = 0;
        
        foreach ($evaluation_data['criteres'] as $critere) {
            if (isset($critere['note']) && isset($critere['max']) && 
                is_numeric($critere['note']) && is_numeric($critere['max'])) {
                
                if ($critere['note'] < 0 || $critere['note'] > $critere['max']) {
                    return ['valid' => false, 'message' => "Note invalide pour le critère {$critere['nom']}: {$critere['note']}/{$critere['max']}"];
                }
                
                $total_notes += $critere['note'];
                $total_max += $critere['max'];
                $criteres_valides++;
            }
        }
        
        if ($criteres_valides == 0) {
            return ['valid' => false, 'message' => 'Aucun critère valide trouvé'];
        }
        
        if ($total_max == 0) {
            return ['valid' => false, 'message' => 'Total maximum des critères est zéro'];
        }
        
        $note_calculee = round(($total_notes / $total_max) * 20, 2);
        
        return [
            'valid' => true, 
            'message' => 'Évaluation valide',
            'note_calculee' => $note_calculee,
            'total_notes' => $total_notes,
            'total_max' => $total_max,
            'criteres_valides' => $criteres_valides
        ];
    }
    
    /**
     * Formate une note pour l'affichage
     * 
     * @param float $note La note à formater
     * @param bool $avec_max Afficher "/20" ou non
     * @return string La note formatée
     */
    public static function formaterNote($note, $avec_max = true) {
        $note_formatee = number_format($note, 2);
        return $avec_max ? "{$note_formatee}/20" : $note_formatee;
    }
    
    /**
     * Détermine la classe CSS pour une note
     * 
     * @param float $note La note
     * @return string La classe CSS
     */
    public static function getClasseNote($note) {
        if ($note >= 16) return 'excellent';
        if ($note >= 14) return 'tres-bien';
        if ($note >= 12) return 'bien';
        if ($note >= 10) return 'assez-bien';
        return 'insuffisant';
    }
    
    /**
     * Calcule les statistiques d'un ensemble de notes
     * 
     * @param array $notes Array de notes
     * @return array Statistiques (moyenne, min, max, ecart_type)
     */
    public static function calculerStatistiques($notes) {
        if (empty($notes)) {
            return [
                'moyenne' => 0,
                'min' => 0,
                'max' => 0,
                'ecart_type' => 0,
                'count' => 0
            ];
        }
        
        $count = count($notes);
        $moyenne = array_sum($notes) / $count;
        $min = min($notes);
        $max = max($notes);
        
        // Calcul de l'écart-type
        $variance = 0;
        foreach ($notes as $note) {
            $variance += pow($note - $moyenne, 2);
        }
        $ecart_type = $count > 1 ? sqrt($variance / ($count - 1)) : 0;
        
        return [
            'moyenne' => round($moyenne, 2),
            'min' => round($min, 2),
            'max' => round($max, 2),
            'ecart_type' => round($ecart_type, 2),
            'count' => $count
        ];
    }
}
?> 