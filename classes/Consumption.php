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
     * Vérifier si un item est une boisson gratuite (Tea, Coffee, Coffee with milk, Drink)
     */
    private function isFreeDrink($item_name) {
        $free_drinks = ['Tēja', 'Kafija', 'Kafija ar pienu', 'Dzēriens'];
        return in_array($item_name, $free_drinks);
    }

    /**
     * Vérifier si un item est une boisson vraiment gratuite la première fois (exclut Dzēriens)
     */
    private function isTrulyFreeDrink($item_name) {
        $truly_free_drinks = ['Tēja', 'Kafija', 'Kafija ar pienu'];
        return in_array($item_name, $truly_free_drinks);
    }

    /**
     * Compter les consommations gratuites du jour pour un employé (exclut Dzēriens)
     */
    public function countFreeDrinksToday($employee_id) {
        $today = date('Y-m-d');
        $consumptions = $this->getForEmployeePeriod($employee_id, $today, $today);
        
        $count = 0;
        foreach ($consumptions as $consumption) {
            if ($this->isTrulyFreeDrink($consumption['item_name'] ?? '')) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Ajouter une consommation
     */
    public function add($employee_id, $item_name, $original_price, $discount_percent = 50) {
        $consumption_date = date('Y-m-d');
        $consumption_time = date('H:i:s');
        
        // Vérifier si c'est une boisson gratuite
        $is_free_drink = $this->isFreeDrink($item_name);
        $is_truly_free_drink = $this->isTrulyFreeDrink($item_name);
        $free_drinks_count = $this->countFreeDrinksToday($employee_id);
        
        // Dzēriens est toujours payant, même la première fois
        if ($item_name === 'Dzēriens') {
            // Dzēriens : toujours payant avec -50%
            if ($original_price <= 0) {
                return false;
            }
            $discounted_price = $original_price * (1 - $discount_percent / 100);
        } elseif ($is_truly_free_drink && $free_drinks_count === 0) {
            // Première boisson vraiment gratuite (Tēja, Kafija, Kafija ar pienu) : forcer à 0
            $original_price = 0;
            $discounted_price = 0;
            $discount_percent = 0;
        } elseif ($is_truly_free_drink && $free_drinks_count >= 1) {
            // Deuxième boisson gratuite ou plus : doit être payante avec -50%
            // Le prix DOIT être fourni et > 0 (vérifié avant dans employee/consumption.php)
            if ($original_price <= 0) {
                // Si le prix n'a pas été fourni ou est 0, on ne peut pas continuer
                return false; // Retourner false au lieu de lancer une exception
            }
            $discounted_price = $original_price * (1 - $discount_percent / 100);
        } else {
            // Consommation normale : appliquer la réduction normale
            $discounted_price = $original_price * (1 - $discount_percent / 100);
        }
        
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
}
