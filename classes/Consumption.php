<?php
/**
 * GRAFIK - Classe Consumption
 * Gestion des consommations employés
 */

require_once __DIR__ . '/Firebase.php';
require_once __DIR__ . '/Employee.php';

class Consumption {
    private $firebase;

    public function __construct() {
        $this->firebase = Firebase::getInstance();
    }

    /**
     * Ajouter une consommation
     */
    public function add($employee_id, $item_name, $original_price, $discount_percent = 50) {
        $discounted_price = $original_price * (1 - $discount_percent / 100);
        $consumption_date = date('Y-m-d');
        $consumption_time = date('H:i:s');
        
        $consumption_id = $this->firebase->generateConsumptionId();
        
        $data = [
            'employee_id' => $employee_id,
            'item_name' => $item_name,
            'original_price' => $original_price,
            'discounted_price' => $discounted_price,
            'discount_percent' => $discount_percent,
            'consumption_date' => $consumption_date,
            'consumption_time' => $consumption_time,
            'created_at' => date('Y-m-d\TH:i:s')
        ];
        
        if ($this->firebase->saveConsumption($consumption_id, $data)) {
            return $consumption_id;
        }
        
        return false;
    }

    /**
     * Récupérer les consommations d'un employé
     */
    public function getForEmployee($employee_id, $limit = 50) {
        return $this->firebase->getConsumptionsForEmployee($employee_id, $limit);
    }

    /**
     * Récupérer les consommations d'un employé pour une période
     */
    public function getForEmployeePeriod($employee_id, $start_date, $end_date) {
        return $this->firebase->getConsumptionsForEmployeePeriod($employee_id, $start_date, $end_date);
    }

    /**
     * Calculer le total des consommations pour un employé sur une période
     */
    public function getTotalForPeriod($employee_id, $start_date, $end_date) {
        return $this->firebase->getConsumptionTotalForPeriod($employee_id, $start_date, $end_date);
    }

    /**
     * Supprimer une consommation
     */
    public function delete($id) {
        return $this->firebase->deleteConsumption($id);
    }

    /**
     * Récupérer les consommations du jour pour un employé
     */
    public function getTodayForEmployee($employee_id) {
        return $this->firebase->getTodayConsumptionsForEmployee($employee_id);
    }

    /**
     * Récupérer les consommations du mois pour un employé
     */
    public function getMonthForEmployee($employee_id, $year, $month) {
        $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        
        return $this->getForEmployeePeriod($employee_id, $start_date, $end_date);
    }

    /**
     * Récupérer les dernières consommations (pour admin dashboard)
     */
    public function getRecent($limit = 10) {
        $consumptions = $this->firebase->getRecentConsumptions($limit);
        
        // Enrichir avec les noms des employés
        $employee = new Employee();
        foreach ($consumptions as &$consumption) {
            if (isset($consumption['employee_id'])) {
                $emp = $employee->getById($consumption['employee_id']);
                if ($emp) {
                    $consumption['first_name'] = $emp['first_name'] ?? '';
                    $consumption['last_name'] = $emp['last_name'] ?? '';
                }
            }
            // Créer un champ consumption_datetime pour compatibilité
            $consumption['consumption_datetime'] = ($consumption['consumption_date'] ?? '') . ' ' . ($consumption['consumption_time'] ?? '');
            $consumption['paid_price'] = $consumption['discounted_price'] ?? 0;
        }
        
        return $consumptions;
    }

    /**
     * Récupérer les boissons du jour pour un employé (pour vérifier les gratuites)
     */
    public function getTodayDrinksForEmployee($employee_id) {
        $today_consumptions = $this->getTodayForEmployee($employee_id);
        
        $drinks = [
            'tea' => 0,
            'coffee' => 0,
            'other' => 0
        ];
        
        foreach ($today_consumptions as $cons) {
            $item_name = strtolower($cons['item_name'] ?? '');
            
            if (strpos($item_name, 'tēja') !== false || strpos($item_name, 'tea') !== false) {
                $drinks['tea']++;
            } elseif (strpos($item_name, 'kafija') !== false || strpos($item_name, 'coffee') !== false || strpos($item_name, 'café') !== false) {
                $drinks['coffee']++;
            } elseif (strpos($item_name, 'cits') !== false || strpos($item_name, 'other') !== false) {
                $drinks['other']++;
            }
        }
        
        return $drinks;
    }
}
