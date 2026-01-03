<?php
/**
 * GRAFIK - Classe Employee
 * Gestion des employés
 */

require_once __DIR__ . '/Firebase.php';

class Employee {
    private $firebase;

    public function __construct() {
        $this->firebase = Firebase::getInstance();
    }

    /**
     * Récupérer tous les employés actifs
     */
    public function getAll($active_only = true) {
        if (!$this->firebase || !$this->firebase->isConnected()) {
            return [];
        }
        $allEmployees = $this->firebase->getAllEmployees();
        $result = [];
        
        foreach ($allEmployees as $id => $employee) {
            if ($active_only && (!isset($employee['is_active']) || !$employee['is_active'])) {
                continue;
            }
            $employee['id'] = $id;
            $result[] = $employee;
        }
        
        // Trier par nom
        usort($result, function($a, $b) {
            $lastNameCmp = strcmp($a['last_name'] ?? '', $b['last_name'] ?? '');
            if ($lastNameCmp !== 0) return $lastNameCmp;
            return strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
        });
        
        return $result;
    }

    /**
     * Récupérer un employé par ID
     */
    public function getById($id) {
        if (!$this->firebase || !$this->firebase->isConnected()) {
            return null;
        }
        $employee = $this->firebase->getEmployee($id);
        if ($employee) {
            $employee['id'] = $id;
        }
        return $employee;
    }

    /**
     * Récupérer un employé par PIN
     */
    public function getByPin($pin) {
        if (!$this->firebase || !$this->firebase->isConnected()) {
            return null;
        }
        $allEmployees = $this->firebase->getAllEmployees();
        foreach ($allEmployees as $id => $employee) {
            if (isset($employee['pin']) && $employee['pin'] === $pin &&
                isset($employee['is_active']) && $employee['is_active']) {
                $employee['id'] = $id;
                return $employee;
            }
        }
        return null;
    }

    /**
     * Récupérer un employé par QR code
     */
    public function getByQr($qr_code) {
        if (!$this->firebase || !$this->firebase->isConnected()) {
            return null;
        }
        $allEmployees = $this->firebase->getAllEmployees();
        foreach ($allEmployees as $id => $employee) {
            if (isset($employee['qr_code']) && $employee['qr_code'] === $qr_code &&
                isset($employee['is_active']) && $employee['is_active']) {
                $employee['id'] = $id;
                return $employee;
            }
        }
        return null;
    }

    /**
     * Créer un nouvel employé
     */
    public function create($first_name, $last_name, $phone, $pin, $employee_type = 'Autre') {
        // Générer un QR code unique
        $qr_code = $this->generateUniqueQr();
        
        // Générer un ID unique
        $employee_id = $this->firebase->generateEmployeeId();
        
        $data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'pin' => $pin,
            'employee_type' => $employee_type,
            'qr_code' => $qr_code,
            'is_active' => true,
            'created_at' => date('Y-m-d\TH:i:s'),
            'updated_at' => date('Y-m-d\TH:i:s')
        ];
        
        if ($this->firebase->saveEmployee($employee_id, $data)) {
            return $employee_id;
        }
        
        return false;
    }

    /**
     * Mettre à jour un employé
     */
    public function update($id, $first_name, $last_name, $phone, $pin = null, $employee_type = null) {
        $employee = $this->firebase->getEmployee($id);
        if (!$employee) {
            return false;
        }
        
        $employee['first_name'] = $first_name;
        $employee['last_name'] = $last_name;
        $employee['phone'] = $phone;
        if ($pin !== null) {
            $employee['pin'] = $pin;
        }
        if ($employee_type !== null) {
            $employee['employee_type'] = $employee_type;
        }
        $employee['updated_at'] = date('Y-m-d\TH:i:s');
        
        return $this->firebase->saveEmployee($id, $employee);
    }

    /**
     * Activer/désactiver un employé
     */
    public function setActive($id, $is_active) {
        $employee = $this->firebase->getEmployee($id);
        if (!$employee) {
            return false;
        }
        
        $employee['is_active'] = (bool)$is_active;
        $employee['updated_at'] = date('Y-m-d\TH:i:s');
        
        return $this->firebase->saveEmployee($id, $employee);
    }

    /**
     * Supprimer un employé
     */
    public function delete($id) {
        return $this->firebase->deleteEmployee($id);
    }

    /**
     * Générer un QR code unique
     */
    private function generateUniqueQr() {
        do {
            $qr_code = bin2hex(random_bytes(16));
            $exists = false;
            $allEmployees = $this->firebase->getAllEmployees();
            foreach ($allEmployees as $employee) {
                if (isset($employee['qr_code']) && $employee['qr_code'] === $qr_code) {
                    $exists = true;
                    break;
                }
            }
        } while ($exists);
        
        return $qr_code;
    }

    /**
     * Vérifier si un PIN existe déjà
     */
    public function pinExists($pin, $exclude_id = null) {
        $allEmployees = $this->firebase->getAllEmployees();
        foreach ($allEmployees as $id => $employee) {
            if ($exclude_id && $id === $exclude_id) {
                continue;
            }
            if (isset($employee['pin']) && $employee['pin'] === $pin) {
                return true;
            }
        }
        return false;
    }
}

