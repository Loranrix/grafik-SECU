<?php
/**
 * GRAFIK - Classe Firebase
 * Gestion de la connexion et des opérations Firebase
 */

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class Firebase {
    private static $instance = null;
    private $database = null;
    private $auth = null;
    private $isConnected = false;

    private function __construct() {
        $this->connect();
    }

    /**
     * Obtenir l'instance singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Connexion à Firebase
     */
    private function connect() {
        try {
            $configPath = __DIR__ . '/../firebase-config.json';
            
            if (!file_exists($configPath)) {
                throw new Exception("Fichier de configuration Firebase introuvable : $configPath");
            }

            $firebase = (new Factory)
                ->withServiceAccount($configPath)
                ->withDatabaseUri('https://grafik-napo-default-rtdb.europe-west1.firebasedatabase.app');
            
            $this->database = $firebase->createDatabase();
            $this->auth = $firebase->createAuth();
            $this->isConnected = true;
            
        } catch (Exception $e) {
            error_log("Firebase Connection Error: " . $e->getMessage());
            $this->isConnected = false;
        }
    }

    /**
     * Vérifier si Firebase est connecté
     */
    public function isConnected() {
        return $this->isConnected;
    }

    /**
     * Obtenir la référence de la base de données
     */
    public function getDatabase() {
        if (!$this->isConnected) {
            throw new Exception("Firebase n'est pas connecté");
        }
        return $this->database;
    }

    /**
     * Obtenir la référence d'authentification
     */
    public function getAuth() {
        if (!$this->isConnected) {
            throw new Exception("Firebase n'est pas connecté");
        }
        return $this->auth;
    }

    /**
     * Sauvegarder un employé dans Firebase
     */
    public function saveEmployee($employee_id, $data) {
        try {
            $ref = $this->database->getReference('grafik/employees/' . $employee_id);
            $ref->set($data);
            return true;
        } catch (Exception $e) {
            error_log("Firebase saveEmployee Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer un employé depuis Firebase
     */
    public function getEmployee($employee_id) {
        try {
            $ref = $this->database->getReference('grafik/employees/' . $employee_id);
            return $ref->getValue();
        } catch (Exception $e) {
            error_log("Firebase getEmployee Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer tous les employés depuis Firebase
     */
    public function getAllEmployees() {
        try {
            $ref = $this->database->getReference('grafik/employees');
            return $ref->getValue() ?? [];
        } catch (Exception $e) {
            error_log("Firebase getAllEmployees Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprimer un employé de Firebase
     */
    public function deleteEmployee($employee_id) {
        try {
            $ref = $this->database->getReference('grafik/employees/' . $employee_id);
            $ref->remove();
            return true;
        } catch (Exception $e) {
            error_log("Firebase deleteEmployee Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sauvegarder un pointage dans Firebase
     */
    public function savePunch($employee_id, $punch_data) {
        try {
            // Normaliser le format
            $normalized = [
                'type' => $punch_data['punch_type'] ?? $punch_data['type'] ?? '',
                'datetime' => isset($punch_data['punch_datetime']) ? str_replace(' ', 'T', $punch_data['punch_datetime']) : (isset($punch_data['datetime']) ? $punch_data['datetime'] : date('Y-m-d\TH:i:s')),
                'shift_id' => $punch_data['shift_id'] ?? null,
                'created_at' => date('Y-m-d\TH:i:s')
            ];
            
            // Ajouter boxes_count si présent
            if (isset($punch_data['boxes_count']) && $punch_data['boxes_count'] !== null) {
                $normalized['boxes_count'] = intval($punch_data['boxes_count']);
            }
            
            $ref = $this->database->getReference('grafik/punches/' . $employee_id);
            $newPunchRef = $ref->push($normalized);
            return $newPunchRef->getKey();
        } catch (Exception $e) {
            error_log("Firebase savePunch Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les pointages d'un employé
     */
    public function getPunches($employee_id, $start_date = null, $end_date = null) {
        try {
            $ref = $this->database->getReference('grafik/punches/' . $employee_id);
            $punches = $ref->getValue() ?? [];
            
            $result = [];
            foreach ($punches as $key => $punch) {
                // Convertir le format Firebase vers le format attendu
                $punch_datetime = $punch['datetime'] ?? $punch['punch_datetime'] ?? '';
                $punch_date = substr($punch_datetime, 0, 10);
                
                // Filtrer par date si spécifié
                if ($start_date || $end_date) {
                    if ($start_date && $punch_date < $start_date) {
                        continue;
                    }
                    if ($end_date && $punch_date > $end_date) {
                        continue;
                    }
                }
                
                // Normaliser le format
                $result[] = [
                    'id' => $key,
                    'employee_id' => $employee_id,
                    'punch_type' => $punch['type'] ?? $punch['punch_type'] ?? '',
                    'punch_datetime' => str_replace('T', ' ', $punch_datetime),
                    'shift_id' => $punch['shift_id'] ?? null,
                    'boxes_count' => $punch['boxes_count'] ?? null,
                    'created_at' => $punch['created_at'] ?? null
                ];
            }
            
            // Trier par date décroissante
            usort($result, function($a, $b) {
                return strcmp($b['punch_datetime'], $a['punch_datetime']);
            });
            
            return $result;
        } catch (Exception $e) {
            error_log("Firebase getPunches Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer tous les pointages pour une date (tous employés)
     */
    public function getAllPunchesByDate($date) {
        try {
            $ref = $this->database->getReference('grafik/punches');
            $allPunches = $ref->getValue() ?? [];
            
            $result = [];
            foreach ($allPunches as $employee_id => $employeePunches) {
                foreach ($employeePunches as $key => $punch) {
                    $punch_datetime = $punch['datetime'] ?? $punch['punch_datetime'] ?? '';
                    $punch_date = substr($punch_datetime, 0, 10);
                    
                    if ($punch_date === $date) {
                        // Récupérer les infos de l'employé
                        $employee = $this->getEmployee($employee_id);
                        
                        // S'assurer que l'employee_id est bien une string pour la compatibilité
                        $emp_id_str = (string)$employee_id;
                        
                        // S'assurer que les noms sont présents
                        $first_name = $employee['first_name'] ?? '';
                        $last_name = $employee['last_name'] ?? '';
                        
                        // Si pas de nom, essayer de récupérer depuis tous les employés
                        if (empty($first_name)) {
                            $allEmployees = $this->getAllEmployees();
                            foreach ($allEmployees as $id => $emp) {
                                if ((string)$id === $emp_id_str || $id == $employee_id) {
                                    $first_name = $emp['first_name'] ?? '';
                                    $last_name = $emp['last_name'] ?? '';
                                    break;
                                }
                            }
                        }
                        
                        $result[] = [
                            'id' => $key,
                            'employee_id' => $emp_id_str,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'punch_type' => $punch['type'] ?? $punch['punch_type'] ?? '',
                            'punch_datetime' => str_replace('T', ' ', $punch_datetime),
                            'shift_id' => $punch['shift_id'] ?? null,
                            'boxes_count' => $punch['boxes_count'] ?? null,
                            'firebase_key' => $key // Ajouter la clé Firebase pour référence
                        ];
                    }
                }
            }
            
            usort($result, function($a, $b) {
                return strcmp($b['punch_datetime'], $a['punch_datetime']);
            });
            
            return $result;
        } catch (Exception $e) {
            error_log("Firebase getAllPunchesByDate Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifier le PIN d'un employé
     */
    public function verifyPin($employee_id, $pin) {
        try {
            $employee = $this->getEmployee($employee_id);
            if ($employee && isset($employee['pin'])) {
                return $employee['pin'] === $pin;
            }
            return false;
        } catch (Exception $e) {
            error_log("Firebase verifyPin Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier le PIN par QR code
     */
    public function verifyPinByQr($qr_code, $pin) {
        try {
            $employees = $this->getAllEmployees();
            foreach ($employees as $id => $employee) {
                if (isset($employee['qr_code']) && $employee['qr_code'] === $qr_code) {
                    if (isset($employee['pin']) && $employee['pin'] === $pin) {
                        return ['success' => true, 'employee_id' => $id, 'employee' => $employee];
                    }
                    return ['success' => false, 'message' => 'PIN incorrect'];
                }
            }
            return ['success' => false, 'message' => 'QR code invalide'];
        } catch (Exception $e) {
            error_log("Firebase verifyPinByQr Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur de connexion'];
        }
    }

    /**
     * Enregistrer un appareil
     */
    public function registerDevice($employee_id, $device_id, $device_info) {
        try {
            $ref = $this->database->getReference('grafik/devices/' . $employee_id . '/' . $device_id);
            $existing = $ref->getValue();
            
            if ($existing) {
                // Mettre à jour last_used
                $ref->update([
                    'last_used' => date('Y-m-d\TH:i:s'),
                    'is_allowed' => $device_info['is_allowed'] ?? true
                ]);
            } else {
                // Créer nouveau device
                $ref->set([
                    'name' => $device_info['name'] ?? 'Unknown Device',
                    'first_registered' => date('Y-m-d\TH:i:s'),
                    'last_used' => date('Y-m-d\TH:i:s'),
                    'is_allowed' => $device_info['is_allowed'] ?? true,
                    'user_agent' => $device_info['user_agent'] ?? ''
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Firebase registerDevice Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si un appareil est autorisé
     */
    public function isDeviceAllowed($employee_id, $device_id) {
        try {
            $ref = $this->database->getReference('grafik/devices/' . $employee_id . '/' . $device_id);
            $device = $ref->getValue();
            
            if (!$device) {
                // Appareil non enregistré - par défaut autorisé lors de la première utilisation
                return true;
            }
            
            return $device['is_allowed'] ?? false;
        } catch (Exception $e) {
            error_log("Firebase isDeviceAllowed Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir tous les appareils d'un employé
     */
    public function getDevices($employee_id) {
        try {
            $ref = $this->database->getReference('grafik/devices/' . $employee_id);
            return $ref->getValue() ?? [];
        } catch (Exception $e) {
            error_log("Firebase getDevices Error: " . $e->getMessage());
            return [];
        }
    }

    // ==================== SCHEDULES (PLANNINGS) ====================

    /**
     * Sauvegarder un planning dans Firebase
     */
    public function saveSchedule($schedule_id, $data) {
        try {
            $ref = $this->database->getReference('grafik/schedules/' . $schedule_id);
            $ref->set($data);
            return true;
        } catch (Exception $e) {
            error_log("Firebase saveSchedule Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer un planning par ID
     */
    public function getSchedule($schedule_id) {
        try {
            $ref = $this->database->getReference('grafik/schedules/' . $schedule_id);
            return $ref->getValue();
        } catch (Exception $e) {
            error_log("Firebase getSchedule Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer tous les plannings d'un employé pour un mois
     */
    public function getSchedulesByEmployeeMonth($employee_id, $year, $month) {
        try {
            $ref = $this->database->getReference('grafik/schedules');
            $allSchedules = $ref->getValue() ?? [];
            
            $start_date = sprintf('%04d-%02d-01', $year, $month);
            $end_date = date('Y-m-t', strtotime($start_date));
            
            $filtered = [];
            foreach ($allSchedules as $id => $schedule) {
                if (isset($schedule['employee_id']) && $schedule['employee_id'] == $employee_id) {
                    $schedule_date = $schedule['schedule_date'] ?? '';
                    if ($schedule_date >= $start_date && $schedule_date <= $end_date) {
                        $schedule['id'] = $id;
                        $filtered[] = $schedule;
                    }
                }
            }
            
            usort($filtered, function($a, $b) {
                $dateCmp = strcmp($a['schedule_date'] ?? '', $b['schedule_date'] ?? '');
                if ($dateCmp !== 0) return $dateCmp;
                return strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
            });
            
            return $filtered;
        } catch (Exception $e) {
            error_log("Firebase getSchedulesByEmployeeMonth Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer tous les plannings pour une date
     */
    public function getSchedulesByDate($date) {
        try {
            $ref = $this->database->getReference('grafik/schedules');
            $allSchedules = $ref->getValue() ?? [];
            
            $filtered = [];
            foreach ($allSchedules as $id => $schedule) {
                if (isset($schedule['schedule_date']) && $schedule['schedule_date'] === $date) {
                    $schedule['id'] = $id;
                    $filtered[] = $schedule;
                }
            }
            
            usort($filtered, function($a, $b) {
                $timeCmp = strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
                if ($timeCmp !== 0) return $timeCmp;
                return strcmp($a['last_name'] ?? '', $b['last_name'] ?? '');
            });
            
            return $filtered;
        } catch (Exception $e) {
            error_log("Firebase getSchedulesByDate Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer tous les plannings pour un mois
     */
    public function getAllSchedulesByMonth($year, $month) {
        try {
            $ref = $this->database->getReference('grafik/schedules');
            $allSchedules = $ref->getValue() ?? [];
            
            $start_date = sprintf('%04d-%02d-01', $year, $month);
            $end_date = date('Y-m-t', strtotime($start_date));
            
            $filtered = [];
            foreach ($allSchedules as $id => $schedule) {
                $schedule_date = $schedule['schedule_date'] ?? '';
                if ($schedule_date >= $start_date && $schedule_date <= $end_date) {
                    $schedule['id'] = $id;
                    $filtered[] = $schedule;
                }
            }
            
            usort($filtered, function($a, $b) {
                $dateCmp = strcmp($a['schedule_date'] ?? '', $b['schedule_date'] ?? '');
                if ($dateCmp !== 0) return $dateCmp;
                $timeCmp = strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
                if ($timeCmp !== 0) return $timeCmp;
                return strcmp($a['last_name'] ?? '', $b['last_name'] ?? '');
            });
            
            return $filtered;
        } catch (Exception $e) {
            error_log("Firebase getAllSchedulesByMonth Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifier si un planning existe pour un employé à une date
     */
    public function scheduleExistsForEmployeeDate($employee_id, $date) {
        try {
            $ref = $this->database->getReference('grafik/schedules');
            $allSchedules = $ref->getValue() ?? [];
            
            foreach ($allSchedules as $schedule) {
                if (isset($schedule['employee_id']) && $schedule['employee_id'] == $employee_id &&
                    isset($schedule['schedule_date']) && $schedule['schedule_date'] === $date) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("Firebase scheduleExistsForEmployeeDate Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer un planning
     */
    public function deleteSchedule($schedule_id) {
        try {
            $ref = $this->database->getReference('grafik/schedules/' . $schedule_id);
            $ref->remove();
            return true;
        } catch (Exception $e) {
            error_log("Firebase deleteSchedule Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Générer un ID unique pour un nouveau planning
     */
    public function generateScheduleId() {
        return 'schedule_' . time() . '_' . uniqid();
    }

    // ==================== MESSAGES ====================

    /**
     * Sauvegarder un message dans Firebase
     */
    public function saveMessage($message_id, $data) {
        try {
            $ref = $this->database->getReference('grafik/messages/' . $message_id);
            $ref->set($data);
            return true;
        } catch (Exception $e) {
            error_log("Firebase saveMessage Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer un message par ID
     */
    public function getMessage($message_id) {
        try {
            $ref = $this->database->getReference('grafik/messages/' . $message_id);
            return $ref->getValue();
        } catch (Exception $e) {
            error_log("Firebase getMessage Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer tous les messages
     */
    public function getAllMessages($limit = null, $offset = 0) {
        try {
            $ref = $this->database->getReference('grafik/messages');
            $allMessages = $ref->getValue() ?? [];
            
            // Convertir en tableau indexé et trier par date
            $messages = [];
            foreach ($allMessages as $id => $message) {
                $message['id'] = $id;
                $messages[] = $message;
            }
            
            usort($messages, function($a, $b) {
                $dateA = $a['created_at'] ?? '';
                $dateB = $b['created_at'] ?? '';
                return strcmp($dateB, $dateA); // Décroissant
            });
            
            if ($limit !== null) {
                $messages = array_slice($messages, $offset, $limit);
            }
            
            return $messages;
        } catch (Exception $e) {
            error_log("Firebase getAllMessages Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les messages non lus
     */
    public function getUnreadMessages() {
        try {
            $ref = $this->database->getReference('grafik/messages');
            $allMessages = $ref->getValue() ?? [];
            
            $unread = [];
            foreach ($allMessages as $id => $message) {
                if (!isset($message['is_read']) || !$message['is_read']) {
                    $message['id'] = $id;
                    $unread[] = $message;
                }
            }
            
            usort($unread, function($a, $b) {
                $dateA = $a['created_at'] ?? '';
                $dateB = $b['created_at'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            return $unread;
        } catch (Exception $e) {
            error_log("Firebase getUnreadMessages Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Compter les messages non lus
     */
    public function countUnreadMessages() {
        try {
            $unread = $this->getUnreadMessages();
            return count($unread);
        } catch (Exception $e) {
            error_log("Firebase countUnreadMessages Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Marquer un message comme lu
     */
    public function markMessageAsRead($message_id) {
        try {
            $ref = $this->database->getReference('grafik/messages/' . $message_id . '/is_read');
            $ref->set(true);
            return true;
        } catch (Exception $e) {
            error_log("Firebase markMessageAsRead Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Générer un ID unique pour un nouveau message
     */
    public function generateMessageId() {
        return 'msg_' . time() . '_' . uniqid();
    }

    // ==================== CONSUMPTIONS ====================

    /**
     * Sauvegarder une consommation dans Firebase
     */
    public function saveConsumption($consumption_id, $data) {
        try {
            $ref = $this->database->getReference('grafik/consumptions/' . $consumption_id);
            $ref->set($data);
            return true;
        } catch (Exception $e) {
            error_log("Firebase saveConsumption Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les consommations d'un employé
     */
    public function getConsumptionsForEmployee($employee_id, $limit = 50) {
        try {
            $ref = $this->database->getReference('grafik/consumptions');
            $allConsumptions = $ref->getValue() ?? [];
            
            $filtered = [];
            foreach ($allConsumptions as $id => $consumption) {
                if (isset($consumption['employee_id']) && $consumption['employee_id'] == $employee_id) {
                    $consumption['id'] = $id;
                    $filtered[] = $consumption;
                }
            }
            
            usort($filtered, function($a, $b) {
                $dateA = ($a['consumption_date'] ?? '') . ' ' . ($a['consumption_time'] ?? '');
                $dateB = ($b['consumption_date'] ?? '') . ' ' . ($b['consumption_time'] ?? '');
                return strcmp($dateB, $dateA); // Décroissant
            });
            
            if ($limit > 0) {
                $filtered = array_slice($filtered, 0, $limit);
            }
            
            return $filtered;
        } catch (Exception $e) {
            error_log("Firebase getConsumptionsForEmployee Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les consommations d'un employé pour une période
     */
    public function getConsumptionsForEmployeePeriod($employee_id, $start_date, $end_date) {
        try {
            $ref = $this->database->getReference('grafik/consumptions');
            $allConsumptions = $ref->getValue() ?? [];
            
            $filtered = [];
            foreach ($allConsumptions as $id => $consumption) {
                if (isset($consumption['employee_id']) && $consumption['employee_id'] == $employee_id) {
                    $consumption_date = $consumption['consumption_date'] ?? '';
                    if ($consumption_date >= $start_date && $consumption_date <= $end_date) {
                        $consumption['id'] = $id;
                        $filtered[] = $consumption;
                    }
                }
            }
            
            usort($filtered, function($a, $b) {
                $dateA = ($a['consumption_date'] ?? '') . ' ' . ($a['consumption_time'] ?? '');
                $dateB = ($b['consumption_date'] ?? '') . ' ' . ($b['consumption_time'] ?? '');
                return strcmp($dateB, $dateA);
            });
            
            return $filtered;
        } catch (Exception $e) {
            error_log("Firebase getConsumptionsForEmployeePeriod Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculer le total des consommations pour un employé sur une période
     */
    public function getConsumptionTotalForPeriod($employee_id, $start_date, $end_date) {
        try {
            $consumptions = $this->getConsumptionsForEmployeePeriod($employee_id, $start_date, $end_date);
            
            $count = count($consumptions);
            $total_original = 0;
            $total_discounted = 0;
            
            foreach ($consumptions as $consumption) {
                $total_original += floatval($consumption['original_price'] ?? 0);
                $total_discounted += floatval($consumption['discounted_price'] ?? 0);
            }
            
            return [
                'count' => $count,
                'total_original' => $total_original,
                'total_discounted' => $total_discounted
            ];
        } catch (Exception $e) {
            error_log("Firebase getConsumptionTotalForPeriod Error: " . $e->getMessage());
            return ['count' => 0, 'total_original' => 0, 'total_discounted' => 0];
        }
    }

    /**
     * Récupérer les consommations du jour pour un employé
     */
    public function getTodayConsumptionsForEmployee($employee_id) {
        $today = date('Y-m-d');
        return $this->getConsumptionsForEmployeePeriod($employee_id, $today, $today);
    }

    /**
     * Récupérer les dernières consommations (pour admin dashboard)
     */
    public function getRecentConsumptions($limit = 10) {
        try {
            $ref = $this->database->getReference('grafik/consumptions');
            $allConsumptions = $ref->getValue() ?? [];
            
            $consumptions = [];
            foreach ($allConsumptions as $id => $consumption) {
                $consumption['id'] = $id;
                $consumptions[] = $consumption;
            }
            
            usort($consumptions, function($a, $b) {
                $dateA = ($a['consumption_date'] ?? '') . ' ' . ($a['consumption_time'] ?? '');
                $dateB = ($b['consumption_date'] ?? '') . ' ' . ($b['consumption_time'] ?? '');
                return strcmp($dateB, $dateA);
            });
            
            return array_slice($consumptions, 0, $limit);
        } catch (Exception $e) {
            error_log("Firebase getRecentConsumptions Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprimer une consommation
     */
    public function deleteConsumption($consumption_id) {
        try {
            $ref = $this->database->getReference('grafik/consumptions/' . $consumption_id);
            $ref->remove();
            return true;
        } catch (Exception $e) {
            error_log("Firebase deleteConsumption Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Générer un ID unique pour une nouvelle consommation
     */
    public function generateConsumptionId() {
        return 'cons_' . time() . '_' . uniqid();
    }

    // ==================== SECURITY SETTINGS ====================

    /**
     * Obtenir un paramètre de sécurité
     */
    public function getSecuritySetting($key, $default = null) {
        try {
            $ref = $this->database->getReference('grafik/security_settings/' . $key);
            $setting = $ref->getValue();
            
            if ($setting === null) {
                return $default;
            }
            
            // Convertir selon le type si présent
            if (isset($setting['type'])) {
                return $this->convertSettingValue($setting['value'], $setting['type']);
            }
            
            return $setting['value'] ?? $default;
        } catch (Exception $e) {
            error_log("Firebase getSecuritySetting Error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Définir un paramètre de sécurité
     */
    public function setSecuritySetting($key, $value, $type = 'string', $description = '') {
        try {
            $ref = $this->database->getReference('grafik/security_settings/' . $key);
            $ref->set([
                'value' => $this->valueToString($value, $type),
                'type' => $type,
                'description' => $description,
                'updated_at' => date('Y-m-d\TH:i:s')
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Firebase setSecuritySetting Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir tous les paramètres de sécurité
     */
    public function getAllSecuritySettings() {
        try {
            $ref = $this->database->getReference('grafik/security_settings');
            $allSettings = $ref->getValue() ?? [];
            
            $result = [];
            foreach ($allSettings as $key => $setting) {
                $result[$key] = [
                    'value' => $this->convertSettingValue($setting['value'] ?? null, $setting['type'] ?? 'string'),
                    'type' => $setting['type'] ?? 'string',
                    'description' => $setting['description'] ?? ''
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Firebase getAllSecuritySettings Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Convertir une valeur selon son type
     */
    private function convertSettingValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return intval($value);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Convertir une valeur en string pour stockage
     */
    private function valueToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'integer':
                return strval($value);
            case 'json':
                return json_encode($value);
            default:
                return strval($value);
        }
    }

    // ==================== AUDIT LOGS ====================

    /**
     * Logger une tentative de connexion employé
     */
    public function logEmployeeLogin($employee_id, $data) {
        try {
            $log_id = 'login_' . time() . '_' . uniqid();
            $ref = $this->database->getReference('grafik/logs/employee_logins/' . $log_id);
            $ref->set([
                'employee_id' => $employee_id,
                'qr_code' => $data['qr_code'] ?? null,
                'pin_entered' => $data['pin_entered'] ?? null,
                'success' => $data['success'] ?? false,
                'failure_reason' => $data['failure_reason'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'device_info' => $data['device_info'] ?? null,
                'ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
                'gps_latitude' => $data['gps_latitude'] ?? null,
                'gps_longitude' => $data['gps_longitude'] ?? null,
                'created_at' => date('Y-m-d\TH:i:s')
            ]);
            return $log_id;
        } catch (Exception $e) {
            error_log("Firebase logEmployeeLogin Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logger une action admin
     */
    public function logAdminAction($admin_id, $action_type, $description, $target_type = null, $target_id = null, $old_values = null, $new_values = null) {
        try {
            $log_id = 'admin_' . time() . '_' . uniqid();
            $ref = $this->database->getReference('grafik/logs/admin_actions/' . $log_id);
            $ref->set([
                'admin_id' => $admin_id,
                'action_type' => $action_type,
                'action_description' => $description,
                'target_type' => $target_type,
                'target_id' => $target_id,
                'old_values' => $old_values ? json_encode($old_values) : null,
                'new_values' => $new_values ? json_encode($new_values) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d\TH:i:s')
            ]);
            return $log_id;
        } catch (Exception $e) {
            error_log("Firebase logAdminAction Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enregistrer une tentative PIN échouée
     */
    public function logFailedPinAttempt($employee_id, $qr_code, $pin_entered, $device_id) {
        try {
            $log_id = 'failed_' . time() . '_' . uniqid();
            $ref = $this->database->getReference('grafik/logs/failed_pin_attempts/' . $log_id);
            $ref->set([
                'employee_id' => $employee_id,
                'qr_code' => $qr_code,
                'pin_entered' => $pin_entered,
                'device_id' => $device_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d\TH:i:s')
            ]);
            return $log_id;
        } catch (Exception $e) {
            error_log("Firebase logFailedPinAttempt Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les logs de connexion d'un employé
     */
    public function getEmployeeLoginLogs($employee_id, $limit = 50) {
        try {
            $ref = $this->database->getReference('grafik/logs/employee_logins');
            $allLogs = $ref->getValue() ?? [];
            
            $filtered = [];
            foreach ($allLogs as $id => $log) {
                if (isset($log['employee_id']) && $log['employee_id'] == $employee_id) {
                    $log['id'] = $id;
                    $filtered[] = $log;
                }
            }
            
            usort($filtered, function($a, $b) {
                $dateA = $a['created_at'] ?? '';
                $dateB = $b['created_at'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            return array_slice($filtered, 0, $limit);
        } catch (Exception $e) {
            error_log("Firebase getEmployeeLoginLogs Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer tous les logs de connexion récents
     */
    public function getRecentLoginLogs($hours = 24, $limit = 100) {
        try {
            $ref = $this->database->getReference('grafik/logs/employee_logins');
            $allLogs = $ref->getValue() ?? [];
            
            $cutoff = date('Y-m-d\TH:i:s', strtotime("-{$hours} hours"));
            
            $filtered = [];
            foreach ($allLogs as $id => $log) {
                $created_at = $log['created_at'] ?? '';
                if ($created_at >= $cutoff) {
                    $log['id'] = $id;
                    $filtered[] = $log;
                }
            }
            
            usort($filtered, function($a, $b) {
                $dateA = $a['created_at'] ?? '';
                $dateB = $b['created_at'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            return array_slice($filtered, 0, $limit);
        } catch (Exception $e) {
            error_log("Firebase getRecentLoginLogs Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les logs d'actions admin
     */
    public function getAdminActionLogs($admin_id = null, $limit = 100) {
        try {
            $ref = $this->database->getReference('grafik/logs/admin_actions');
            $allLogs = $ref->getValue() ?? [];
            
            $filtered = [];
            foreach ($allLogs as $id => $log) {
                if ($admin_id === null || (isset($log['admin_id']) && $log['admin_id'] == $admin_id)) {
                    $log['id'] = $id;
                    $filtered[] = $log;
                }
            }
            
            usort($filtered, function($a, $b) {
                $dateA = $a['created_at'] ?? '';
                $dateB = $b['created_at'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            return array_slice($filtered, 0, $limit);
        } catch (Exception $e) {
            error_log("Firebase getAdminActionLogs Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtenir les statistiques de sécurité
     */
    public function getSecurityStats() {
        try {
            $stats = [];
            
            // Total tentatives connexion 24h
            $loginLogs = $this->getRecentLoginLogs(24);
            $total = count($loginLogs);
            $successful = 0;
            $failed = 0;
            
            foreach ($loginLogs as $log) {
                if (isset($log['success']) && $log['success']) {
                    $successful++;
                } else {
                    $failed++;
                }
            }
            
            $stats['login_attempts_24h'] = [
                'total' => $total,
                'successful' => $successful,
                'failed' => $failed
            ];
            
            // Actions admin 24h
            $adminLogs = $this->getAdminActionLogs(null, 1000);
            $adminCount = 0;
            $cutoff = date('Y-m-d\TH:i:s', strtotime('-24 hours'));
            foreach ($adminLogs as $log) {
                if (isset($log['created_at']) && $log['created_at'] >= $cutoff) {
                    $adminCount++;
                }
            }
            $stats['admin_actions_24h'] = $adminCount;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Firebase getSecurityStats Error: " . $e->getMessage());
            return [
                'login_attempts_24h' => ['total' => 0, 'successful' => 0, 'failed' => 0],
                'admin_actions_24h' => 0
            ];
        }
    }

    // ==================== ADMINS ====================

    /**
     * Sauvegarder un admin dans Firebase
     */
    public function saveAdmin($admin_id, $data) {
        try {
            $ref = $this->database->getReference('grafik/admins/' . $admin_id);
            $ref->set($data);
            return true;
        } catch (Exception $e) {
            error_log("Firebase saveAdmin Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer un admin par username
     */
    public function getAdminByUsername($username) {
        try {
            $ref = $this->database->getReference('grafik/admins');
            $allAdmins = $ref->getValue() ?? [];
            
            foreach ($allAdmins as $id => $admin) {
                if (isset($admin['username']) && $admin['username'] === $username) {
                    $admin['id'] = $id;
                    return $admin;
                }
            }
            return null;
        } catch (Exception $e) {
            error_log("Firebase getAdminByUsername Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mettre à jour last_login d'un admin
     */
    public function updateAdminLastLogin($admin_id) {
        try {
            $ref = $this->database->getReference('grafik/admins/' . $admin_id . '/last_login');
            $ref->set(date('Y-m-d\TH:i:s'));
            return true;
        } catch (Exception $e) {
            error_log("Firebase updateAdminLastLogin Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Générer un ID unique pour un nouvel employé
     */
    public function generateEmployeeId() {
        return 'emp_' . time() . '_' . uniqid();
    }

    /**
     * Générer un ID unique pour un nouveau pointage
     */
    public function generatePunchId() {
        return 'punch_' . time() . '_' . uniqid();
    }
}

