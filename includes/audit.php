<?php
/**
 * نظام Audit Logging - تسجيل جميع العمليات الحساسة
 */

class AuditLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * تسجيل عملية
     * @param string $action العملية (approve, reject, delete, update, create, login, etc)
     * @param string $resourceType نوع المورد (event, user, setting, etc)
     * @param int|null $resourceId معرف المورد
     * @param mixed $oldValue القيمة القديمة (سيتم تحويلها لـ JSON)
     * @param mixed $newValue القيمة الجديدة (سيتم تحويلها لـ JSON)
     * @param int|null $userId معرف المستخدم (null للعمليات غير المصادق عليها)
     */
    public function log($action, $resourceType, $resourceId = null, $oldValue = null, $newValue = null, $userId = null) {
        try {
            // الحصول على معلومات الجلسة الحالية
            $userId = $userId ?? ($_SESSION['user_id'] ?? null);
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // تحويل القيم إلى JSON
            $oldValueJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
            $newValueJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log 
                (user_id, action, resource_type, resource_id, old_value, new_value, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $userId,
                $action,
                $resourceType,
                $resourceId,
                $oldValueJson,
                $newValueJson,
                $ipAddress,
                $userAgent
            ]);
        } catch (\PDOException $e) {
            // تسجيل الخطأ في ملف log بدلاً من إيقاف التطبيق
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تسجيل عملية على event
     */
    public function logEvent($action, $eventId, $oldData = null, $newData = null) {
        return $this->log($action, 'event', $eventId, $oldData, $newData);
    }
    
    /**
     * تسجيل عملية على user
     */
    public function logUser($action, $userId, $oldData = null, $newData = null) {
        return $this->log($action, 'user', $userId, $oldData, $newData);
    }
    
    /**
     * تسجيل عملية على setting
     */
    public function logSetting($action, $settingKey, $oldValue = null, $newValue = null) {
        return $this->log($action, 'setting', null, ['key' => $settingKey, 'value' => $oldValue], ['key' => $settingKey, 'value' => $newValue]);
    }
    
    /**
     * تسجيل محاولة تسجيل دخول
     */
    public function logLogin($username, $success = true, $userId = null) {
        $action = $success ? 'login_success' : 'login_failed';
        return $this->log($action, 'user', $userId, null, ['username' => $username]);
    }
    
    /**
     * جلب سجلات معينة
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        $where = [];
        $params = [];
        
        if (isset($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        
        if (isset($filters['resource_type'])) {
            $where[] = 'resource_type = ?';
            $params[] = $filters['resource_type'];
        }
        
        if (isset($filters['resource_id'])) {
            $where[] = 'resource_id = ?';
            $params[] = $filters['resource_id'];
        }
        
        if (isset($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.username, u.full_name
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            {$whereClause}
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * حساب عدد السجلات
     */
    public function countLogs($filters = []) {
        $where = [];
        $params = [];
        
        if (isset($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        
        if (isset($filters['resource_type'])) {
            $where[] = 'resource_type = ?';
            $params[] = $filters['resource_type'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM audit_log
            {$whereClause}
        ");
        
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
    
    /**
     * حذف سجلات قديمة (أكثر من X أيام)
     */
    public function cleanup($daysToKeep = 90) {
        $stmt = $this->pdo->prepare("
            DELETE FROM audit_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        return $stmt->execute([$daysToKeep]);
    }
    
    /**
     * الحصول على IP الحقيقي للعميل
     */
    private function getClientIp() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }
}

/**
 * دوال مساعدة سريعة
 */

function audit_log($action, $resourceType, $resourceId = null, $oldValue = null, $newValue = null) {
    global $pdo;
    $logger = new AuditLogger($pdo);
    return $logger->log($action, $resourceType, $resourceId, $oldValue, $newValue);
}

function audit_log_event($action, $eventId, $oldData = null, $newData = null) {
    global $pdo;
    $logger = new AuditLogger($pdo);
    return $logger->logEvent($action, $eventId, $oldData, $newData);
}
?>
