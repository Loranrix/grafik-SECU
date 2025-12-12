<?php
/**
 * GRAFIK - Classe Punch
 * Gestion des pointages
 */

require_once __DIR__ . '/Firebase.php';
require_once __DIR__ . '/Shift.php';

class Punch {
    private $firebase;
    private $shift;

    public function __construct() {
        $this->firebase = Firebase::getInstance();
        $this->shift = new Shift();
    }

    /**
     * Enregistrer un pointage (arrivée ou départ)
     */
    public function record($employee_id, $type, $datetime = null, $boxes_count = null) {
        if ($datetime === null) {
            $datetime = date('Y-m-d H:i:s');
        }

        // Vérifier le dernier pointage pour éviter les doublons
        // On vérifie seulement le dernier pointage du JOUR pour permettre plusieurs pointages dans la journée
        $today = date('Y-m-d', strtotime($datetime));
        $lastPunchToday = $this->getLastPunchForDate($employee_id, $today);
        
        if ($lastPunchToday && $lastPunchToday['punch_type'] === $type) {
            // Si le dernier pointage du jour est du même type, on refuse
            $type_label = $type === 'in' ? 'arrivée' : 'départ';
            throw new Exception("Impossible d'enregistrer deux fois une {$type_label} le même jour. Veuillez enregistrer l'action opposée.");
        }
        
        // Pour les vérifications d'oubli, utiliser le dernier pointage global
        $lastPunch = $this->getLastPunch($employee_id);
        
        // Vérifier les oublis de scan
        $missing_scan_message = null;
        if ($lastPunch) {
            $last_type = $lastPunch['punch_type'] ?? '';
            
            // Si on pointe "arrivée" mais le dernier était aussi "arrivée" (pas de départ entre les deux)
            if ($type === 'in' && $last_type === 'in') {
                $missing_scan_message = "⚠️ Warning: You are registering arrival, but the previous departure was not registered. Please contact Loran on WhatsApp about the forgotten scan.";
            }
            // Si on pointe "départ" mais le dernier était aussi "départ" (pas d'arrivée entre les deux)
            elseif ($type === 'out' && $last_type === 'out') {
                $missing_scan_message = "⚠️ Warning: You are registering departure, but the previous arrival was not registered. Please contact Loran on WhatsApp about the forgotten scan.";
            }
        } else {
            // Pas de dernier pointage : si on pointe "départ" sans avoir pointé "arrivée" avant
            if ($type === 'out') {
                $missing_scan_message = "⚠️ Warning: You are registering departure without a previous arrival. Please contact Loran on WhatsApp about the forgotten scan.";
            }
        }

        // Trouver le shift correspondant si existe
        $shift_id = $this->findShiftForPunch($employee_id, $datetime);

        // Préparer les données pour Firebase
        $punch_data = [
            'type' => $type,
            'punch_type' => $type,
            'datetime' => str_replace(' ', 'T', $datetime),
            'punch_datetime' => $datetime,
            'shift_id' => $shift_id,
            'created_at' => date('Y-m-d\TH:i:s')
        ];
        
        // Ajouter le nombre de boîtes si présent
        if ($boxes_count !== null && $boxes_count >= 0) {
            $punch_data['boxes_count'] = $boxes_count;
        }

        $punch_id = $this->firebase->savePunch($employee_id, $punch_data);
        return $punch_id;
    }

    /**
     * Trouver le shift correspondant pour un pointage
     */
    private function findShiftForPunch($employee_id, $datetime) {
        $date = date('Y-m-d', strtotime($datetime));
        $schedules = $this->firebase->getSchedulesByEmployeeMonth($employee_id, date('Y', strtotime($date)), date('n', strtotime($date)));
        
        foreach ($schedules as $schedule) {
            if (isset($schedule['schedule_date']) && $schedule['schedule_date'] === $date) {
                return $schedule['id'] ?? null;
            }
        }
        return null;
    }

    /**
     * Récupérer le dernier pointage d'un employé
     */
    public function getLastPunch($employee_id) {
        $punches = $this->firebase->getPunches($employee_id);
        if (empty($punches)) {
            return null;
        }
        return $punches[0]; // Déjà trié par date décroissante
    }

    /**
     * Récupérer le dernier pointage d'un employé pour une date spécifique
     */
    public function getLastPunchForDate($employee_id, $date) {
        $punches = $this->firebase->getPunches($employee_id, $date, $date);
        if (empty($punches)) {
            return null;
        }
        // Trier par date décroissante pour avoir le plus récent
        usort($punches, function($a, $b) {
            return strcmp($b['punch_datetime'], $a['punch_datetime']);
        });
        return $punches[0] ?? null;
    }

    /**
     * Récupérer tous les pointages d'un employé pour une date
     */
    public function getByEmployeeAndDate($employee_id, $date) {
        $punches = $this->firebase->getPunches($employee_id, $date, $date);
        // Trier par heure croissante
        usort($punches, function($a, $b) {
            return strcmp($a['punch_datetime'], $b['punch_datetime']);
        });
        return $punches;
    }

    /**
     * Récupérer tous les pointages d'un employé pour une période
     */
    public function getByEmployeeDateRange($employee_id, $start_date, $end_date) {
        $punches = $this->firebase->getPunches($employee_id, $start_date, $end_date);
        // Trier par heure croissante
        usort($punches, function($a, $b) {
            return strcmp($a['punch_datetime'], $b['punch_datetime']);
        });
        return $punches;
    }

    /**
     * Calculer les heures travaillées pour un employé pour une date
     */
    public function calculateHours($employee_id, $date) {
        $punches = $this->getByEmployeeAndDate($employee_id, $date);
        
        $total_hours = 0;
        $in_time = null;

        foreach ($punches as $punch) {
            if ($punch['punch_type'] === 'in') {
                $in_time = strtotime($punch['punch_datetime']);
            } elseif ($punch['punch_type'] === 'out' && $in_time !== null) {
                $out_time = strtotime($punch['punch_datetime']);
                $hours = ($out_time - $in_time) / 3600; // Convertir en heures
                $total_hours += $hours;
                $in_time = null;
            }
        }

        return round($total_hours, 2);
    }

    /**
     * Calculer les heures travaillées pour une période
     */
    public function calculateHoursRange($employee_id, $start_date, $end_date) {
        $total = 0;
        $current_date = $start_date;
        
        while (strtotime($current_date) <= strtotime($end_date)) {
            $total += $this->calculateHours($employee_id, $current_date);
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return round($total, 2);
    }

    /**
     * Arrondir une heure selon les règles de paie
     * @param string $datetime Format: 'Y-m-d H:i:s'
     * @param string $punch_type 'in' ou 'out'
     * @param string $employee_type 'Bar', 'Cuisine', etc.
     * @return string Heure arrondie au format 'H:i'
     */
    private function roundTimeForPay($datetime, $punch_type, $employee_type) {
        $time = strtotime($datetime);
        $hour = (int)date('H', $time);
        $minute = (int)date('i', $time);
        $date = date('Y-m-d', $time);
        
        // Convertir en minutes depuis minuit
        $total_minutes = $hour * 60 + $minute;
        
        if ($punch_type === 'in') {
            // Règles pour les arrivées
            if ($employee_type === 'Bar') {
                // Bar : si avant ou à 11:45 → 11:45, sinon arrondi au 1/4 supérieur
                $limit_minutes = 11 * 60 + 45; // 11:45
                if ($total_minutes <= $limit_minutes) {
                    return '11:45';
                } else {
                    // Arrondi au 1/4 d'heure supérieur
                    $quarters = ceil($total_minutes / 15) * 15;
                    $rounded_hour = floor($quarters / 60);
                    $rounded_minute = $quarters % 60;
                    return sprintf('%02d:%02d', $rounded_hour, $rounded_minute);
                }
            } elseif ($employee_type === 'Cuisine') {
                // Cuisine : si avant ou à 11:30 → 11:30, sinon arrondi au 1/4 supérieur
                $limit_minutes = 11 * 60 + 30; // 11:30
                if ($total_minutes <= $limit_minutes) {
                    return '11:30';
                } else {
                    // Arrondi au 1/4 d'heure supérieur
                    $quarters = ceil($total_minutes / 15) * 15;
                    $rounded_hour = floor($quarters / 60);
                    $rounded_minute = $quarters % 60;
                    return sprintf('%02d:%02d', $rounded_hour, $rounded_minute);
                }
            }
        } elseif ($punch_type === 'out') {
            // Règles pour les départs (Bar et Cuisine)
            if ($employee_type === 'Bar' || $employee_type === 'Cuisine') {
                // Arrondi au 1/4 d'heure inférieur
                $quarters = floor($total_minutes / 15) * 15;
                $rounded_hour = floor($quarters / 60);
                $rounded_minute = $quarters % 60;
                return sprintf('%02d:%02d', $rounded_hour, $rounded_minute);
            }
        }
        
        // Par défaut, retourner l'heure originale
        return date('H:i', $time);
    }

    /**
     * Calculer les heures payées pour un employé pour une date (avec arrondi)
     */
    public function calculatePaidHours($employee_id, $date) {
        $punches = $this->getByEmployeeAndDate($employee_id, $date);
        
        // Récupérer le type d'employé
        $employeeModel = new Employee();
        $employee = $employeeModel->getById($employee_id);
        $employee_type = $employee['employee_type'] ?? 'Autre';
        
        $total_hours = 0;
        $in_time = null;
        $in_datetime = null;

        foreach ($punches as $punch) {
            if ($punch['punch_type'] === 'in') {
                $in_time = strtotime($punch['punch_datetime']);
                $in_datetime = $punch['punch_datetime'];
            } elseif ($punch['punch_type'] === 'out' && $in_time !== null) {
                $out_time = strtotime($punch['punch_datetime']);
                $out_datetime = $punch['punch_datetime'];
                
                // Arrondir les heures d'arrivée et de départ
                $rounded_in = $this->roundTimeForPay($in_datetime, 'in', $employee_type);
                $rounded_out = $this->roundTimeForPay($out_datetime, 'out', $employee_type);
                
                // Convertir en timestamp pour calculer la différence
                $date_part = date('Y-m-d', $in_time);
                $rounded_in_time = strtotime($date_part . ' ' . $rounded_in);
                $rounded_out_time = strtotime($date_part . ' ' . $rounded_out);
                
                // Si l'heure de départ arrondie est avant l'heure d'arrivée arrondie, 
                // c'est probablement le lendemain
                if ($rounded_out_time < $rounded_in_time) {
                    $rounded_out_time = strtotime($date_part . ' ' . $rounded_out . ' +1 day');
                }
                
                $hours = ($rounded_out_time - $rounded_in_time) / 3600;
                $total_hours += $hours;
                $in_time = null;
                $in_datetime = null;
            }
        }

        return round($total_hours, 2);
    }

    /**
     * Calculer les heures payées pour une période (avec arrondi)
     */
    public function calculatePaidHoursRange($employee_id, $start_date, $end_date) {
        $total = 0;
        $current_date = $start_date;
        
        while (strtotime($current_date) <= strtotime($end_date)) {
            $total += $this->calculatePaidHours($employee_id, $current_date);
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return round($total, 2);
    }

    /**
     * Ajouter manuellement un pointage (admin)
     * Permet d'ajouter même en cas de doublon ou d'anomalie (avec alerte dans l'interface admin)
     */
    public function addManual($employee_id, $type, $datetime) {
        // Pour l'admin, on ne bloque pas les doublons, on les laisse passer
        // La vérification des doublons sera faite dans l'interface admin qui affichera une alerte
        
        // Trouver le shift correspondant si existe
        $shift_id = $this->findShiftForPunch($employee_id, $datetime);
        
        // Préparer les données pour Firebase
        $punch_data = [
            'type' => $type,
            'punch_type' => $type,
            'datetime' => str_replace(' ', 'T', $datetime),
            'punch_datetime' => $datetime,
            'shift_id' => $shift_id,
            'created_at' => date('Y-m-d\TH:i:s')
        ];
        
        $punch_id = $this->firebase->savePunch($employee_id, $punch_data);
        return $punch_id;
    }

    /**
     * Mettre à jour un pointage (admin)
     */
    public function update($punch_id, $datetime, $boxes_count = null) {
        // Trouver le pointage dans Firebase
        $allEmployees = $this->firebase->getAllEmployees();
        foreach ($allEmployees as $employee_id => $employee) {
            try {
                $ref = $this->firebase->getDatabase()->getReference('grafik/punches/' . $employee_id);
                $employeePunches = $ref->getValue() ?? [];
                
                foreach ($employeePunches as $key => $punch) {
                    // Vérifier si c'est le bon pointage (par ID ou clé Firebase)
                    // Le punch_id peut être la clé Firebase ($key) ou l'ID stocké
                    if ($key === (string)$punch_id || $key === $punch_id || (isset($punch['id']) && $punch['id'] === $punch_id)) {
                        // Construire les données mises à jour
                        $punch_data = $punch; // Garder toutes les données existantes
                        $punch_data['type'] = $punch['type'] ?? $punch['punch_type'] ?? 'in';
                        $punch_data['punch_type'] = $punch['type'] ?? $punch['punch_type'] ?? 'in';
                        
                        // Mettre à jour la date/heure
                        $datetime_formatted = str_replace(' ', 'T', $datetime);
                        $punch_data['datetime'] = $datetime_formatted;
                        $punch_data['punch_datetime'] = $datetime;
                        
                        // Mettre à jour boxes_count si fourni
                        if ($boxes_count !== null) {
                            $punch_data['boxes_count'] = $boxes_count;
                        }
                        // Sinon, garder la valeur existante si présente
                        
                        // Mettre à jour dans Firebase
                        $ref = $this->firebase->getDatabase()->getReference('grafik/punches/' . $employee_id . '/' . $key);
                        $ref->update($punch_data);
                        return true;
                    }
                }
            } catch (Exception $e) {
                error_log("Erreur recherche pointage pour employé $employee_id: " . $e->getMessage());
                continue;
            }
        }
        return false;
    }

    /**
     * Supprimer un pointage
     * $id peut être soit la clé Firebase, soit l'ID stocké dans le pointage
     */
    public function delete($id) {
        if (empty($id)) {
            error_log("Tentative de suppression avec un ID vide");
            return false;
        }
        
        // L'ID peut être la clé Firebase directement ou un ID stocké
        // On doit parcourir tous les employés pour trouver le pointage
        $allEmployees = $this->firebase->getAllEmployees();
        
        foreach ($allEmployees as $employee_id => $employee) {
            try {
                $ref = $this->firebase->getDatabase()->getReference('grafik/punches/' . $employee_id);
                $employeePunches = $ref->getValue() ?? [];
                
                if (empty($employeePunches)) {
                    continue;
                }
                
                // Chercher le pointage par clé Firebase ou par ID
                foreach ($employeePunches as $key => $punch) {
                    // Vérifier si c'est la bonne clé Firebase ou si l'ID correspond
                    $keyMatch = ($key === (string)$id || $key === $id);
                    $idMatch = (isset($punch['id']) && ($punch['id'] === $id || (string)$punch['id'] === (string)$id));
                    
                    if ($keyMatch || $idMatch) {
                        // Supprimer le pointage de Firebase
                        try {
                            $punchRef = $this->firebase->getDatabase()->getReference('grafik/punches/' . $employee_id . '/' . $key);
                            $punchRef->remove();
                            error_log("Pointage supprimé avec succès: employee_id=$employee_id, key=$key, id=$id");
                            return true;
                        } catch (Exception $e) {
                            error_log("Erreur suppression pointage (clé: $key, employee_id: $employee_id): " . $e->getMessage());
                            // Essayer quand même de supprimer avec l'ID directement
                            try {
                                $punchRefById = $this->firebase->getDatabase()->getReference('grafik/punches/' . $employee_id . '/' . $id);
                                $punchRefById->remove();
                                error_log("Pointage supprimé avec ID directement: employee_id=$employee_id, id=$id");
                                return true;
                            } catch (Exception $e2) {
                                error_log("Erreur suppression avec ID: " . $e2->getMessage());
                                continue;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Erreur recherche pointage pour employé $employee_id: " . $e->getMessage());
                continue;
            }
        }
        
        error_log("Pointage non trouvé pour suppression (ID: $id). Employés vérifiés: " . count($allEmployees));
        return false;
    }

    /**
     * Récupérer tous les pointages pour une date (admin)
     */
    public function getAllByDate($date) {
        return $this->firebase->getAllPunchesByDate($date);
    }
}
