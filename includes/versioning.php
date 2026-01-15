<?php
/**
 * نظام إدارة الإصدارات - Event Versioning
 */

class EventVersioning {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * حفظ نسخل جديدة من event
     */
    public function saveVersion($eventId, $changeType = 'update', $changeNote = null) {
        try {
            // جلب بيانات الفعالية الحالية
            $stmt = $this->pdo->prepare("SELECT * FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $eventData = $stmt->fetch();
            
            if (!$eventData) {
                return false;
            }
            
            // الحصول على رقم الإصدار التالي
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(MAX(version_number), 0) + 1 as next_version 
                FROM event_versions 
                WHERE event_id = ?
            ");
            $stmt->execute([$eventId]);
            $versionNumber = $stmt->fetch()['next_version'];
            
            // حفظ النسخة
            $stmt = $this->pdo->prepare("
                INSERT INTO event_versions 
                (event_id, version_number, data_snapshot, changed_by, change_type, change_note) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $dataSnapshot = json_encode($eventData, JSON_UNESCAPED_UNICODE);
            $changedBy = $_SESSION['user_id'] ?? null;
            
            return $stmt->execute([
                $eventId,
                $versionNumber,
                $dataSnapshot,
                $changedBy,
                $changeType,
                $changeNote
            ]);
            
        } catch (\PDOException $e) {
            error_log("Version save failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب جميع الإصدارات لفعالية معينة
     */
    public function getVersions($eventId) {
        $stmt = $this->pdo->prepare("
            SELECT v.*, u.username, u.full_name
            FROM event_versions v
            LEFT JOIN users u ON v.changed_by = u.id
            WHERE v.event_id = ?
            ORDER BY v.version_number DESC
        ");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }
    
    /**
     * جلب إصدار معين
     */
    public function getVersion($eventId, $versionNumber) {
        $stmt = $this->pdo->prepare("
            SELECT v.*, u.username, u.full_name
            FROM event_versions v
            LEFT JOIN users u ON v.changed_by = u.id
            WHERE v.event_id = ? AND v.version_number = ?
        ");
        $stmt->execute([$eventId, $versionNumber]);
        $version = $stmt->fetch();
        
        if ($version) {
            $version['data'] = json_decode($version['data_snapshot'], true);
        }
        
        return $version;
    }
    
    /**
     * استعادة إصدار سابق (نسخ البيانات من version إلى event)
     */
    public function restoreVersion($eventId, $versionNumber) {
        try {
            // جلب الإصدار
            $version = $this->getVersion($eventId, $versionNumber);
            if (!$version) {
                return false;
            }
            
            $data = $version['data'];
            
            // حفظ الحالة الحالية قبل الاستعادة
            $this->saveVersion($eventId, 'update', "قبل الاستعادة إلى الإصدار {$versionNumber}");
            
            // استعادة البيانات (باستثناء id و created_at)
            $stmt = $this->pdo->prepare("
                UPDATE events SET 
                    title = ?,
                    organizing_dept = ?,
                    related_depts = ?,
                    requester_mobile = ?,
                    requester_email = ?,
                    start_date = ?,
                    end_date = ?,
                    start_time = ?,
                    end_time = ?,
                    location_type = ?,
                    hall_id = ?,
                    custom_hall_name = ?,
                    req_audio = ?,
                    req_catering = ?,
                    req_security = ?,
                    req_media = ?,
                    req_projector = ?,
                    external_address = ?,
                    req_transport = ?,
                    mkt_brochures = ?,
                    mkt_gifts = ?,
                    mkt_tools = ?,
                    estimated_budget = ?,
                    guest_list = ?,
                    attendees_expected = ?,
                    notes = ?,
                    status = 'pending'
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['title'],
                $data['organizing_dept'],
                $data['related_depts'],
                $data['requester_mobile'],
                $data['requester_email'],
                $data['start_date'],
                $data['end_date'],
                $data['start_time'],
                $data['end_time'],
                $data['location_type'],
                $data['hall_id'],
                $data['custom_hall_name'],
                $data['req_audio'],
                $data['req_catering'],
                $data['req_security'],
                $data['req_media'],
                $data['req_projector'],
                $data['external_address'],
                $data['req_transport'],
                $data['mkt_brochures'],
                $data['mkt_gifts'],
                $data['mkt_tools'],
                $data['estimated_budget'],
                $data['guest_list'],
                $data['attendees_expected'],
                $data['notes'],
                $eventId
            ]);
            
        } catch (\PDOException $e) {
            error_log("Version restore failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * مقارنة إصدارين
     */
    public function compareVersions($eventId, $version1, $version2) {
        $v1 = $this->getVersion($eventId, $version1);
        $v2 = $this->getVersion($eventId, $version2);
        
        if (!$v1 || !$v2) {
            return null;
        }
        
        $diff = [];
        $keys = array_keys($v1['data']);
        
        foreach ($keys as $key) {
            if ($v1['data'][$key] !== $v2['data'][$key]) {
                $diff[$key] = [
                    'old' => $v1['data'][$key],
                    'new' => $v2['data'][$key]
                ];
            }
        }
        
        return $diff;
    }
    
    /**
     * حذف إصدارات قديمة (الاحتفاظ بآخر X إصدار فقط)
     */
    public function cleanup($eventId, $keepLast = 10) {
        $stmt = $this->pdo->prepare("
            DELETE FROM event_versions 
            WHERE event_id = ? 
            AND version_number < (
                SELECT MAX(version_number) - ? 
                FROM (SELECT version_number FROM event_versions WHERE event_id = ?) AS t
            )
        ");
        return $stmt->execute([$eventId, $keepLast, $eventId]);
    }
}

/**
 * دوال مساعدة
 */
function save_event_version($eventId, $changeType = 'update', $changeNote = null) {
    global $pdo;
    $versioning = new EventVersioning($pdo);
    return $versioning->saveVersion($eventId, $changeType, $changeNote);
}

function get_event_versions($eventId) {
    global $pdo;
    $versioning = new EventVersioning($pdo);
    return $versioning->getVersions($eventId);
}

function restore_event_version($eventId, $versionNumber) {
    global $pdo;
    $versioning = new EventVersioning($pdo);
    return $versioning->restoreVersion($eventId, $versionNumber);
}
?>
