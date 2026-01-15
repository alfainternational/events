<?php
/**
 * Event Model
 * يدير جميع عمليات الفعاليات في قاعدة البيانات
 */

require_once __DIR__ . '/BaseModel.php';

class EventModel extends BaseModel {
    protected $table = 'events';
    
    /**
     * جلب الفعاليات غير المحذوفة فقط
     */
    public function allActive($orderBy = 'created_at DESC') {
        return $this->all(['deleted_at' => null], $orderBy);
    }
    
    /**
     * جلب الفعاليات حسب الحالة
     */
    public function getByStatus($status) {
        return $this->query("
            SELECT e.*, h.name as hall_name
            FROM {$this->table} e
            LEFT JOIN halls h ON e.hall_id = h.id
            WHERE e.status = ? AND e.deleted_at IS NULL
            ORDER BY e.start_date DESC
        ", [$status]);
    }
    
    /**
     * جلب الفعاليات المعتمدة
     */
    public function getApproved() {
        return $this->getByStatus('approved');
    }
    
    /**
     * جلب الفعاليات المعلقة
     */
    public function getPending() {
        return $this->getByStatus('pending');
    }
    
    /**
     * البحث المتقدم
     */
    public function search($criteria) {
        $where = ["deleted_at IS NULL"];
        $params = [];
        
        if (!empty($criteria['q'])) {
            $where[] = "(title LIKE ? OR organizing_dept LIKE ? OR notes LIKE ?)";
            $searchTerm = '%' . $criteria['q'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($criteria['status'])) {
            $where[] = "status = ?";
            $params[] = $criteria['status'];
        }
        
        if (!empty($criteria['location_type'])) {
            $where[] = "location_type = ?";
            $params[] = $criteria['location_type'];
        }
        
        if (!empty($criteria['hall_id'])) {
            $where[] = "hall_id = ?";
            $params[] = $criteria['hall_id'];
        }
        
        if (!empty($criteria['date_from'])) {
            $where[] = "start_date >= ?";
            $params[] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $where[] = "start_date <= ?";
            $params[] = $criteria['date_to'];
        }
        
        $whereClause = implode(' AND ', $where);
        $orderBy = $criteria['sort'] ?? 'start_date DESC';
        
        $sql = "
            SELECT e.*, h.name as hall_name
            FROM {$this->table} e
            LEFT JOIN halls h ON e.hall_id = h.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
        ";
        
        return $this->query($sql, $params);
    }
    
    /**
     * التحقق من تعارض المواعيد
     */
    public function checkConflict($startDate, $endDate, $startTime, $endTime, $hallId, $excludeId = null) {
        $sql = "
            SELECT COUNT(*) as count
            FROM {$this->table}
            WHERE hall_id = ?
            AND status = 'approved'
            AND deleted_at IS NULL
            AND (
                (start_date = ? AND start_time < ? AND end_time > ?)
                OR (start_date BETWEEN ? AND ?)
                OR (end_date BETWEEN ? AND ?)
            )
        ";
        
        $params = [$hallId, $startDate, $endTime, $startTime, $startDate, $endDate, $startDate, $endDate];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * جلب إحصائيات الفعاليات
     */
    public function getStatistics() {
        $stats = [];
        
        // حسب الحالة
        $stmt = $this->pdo->query("
            SELECT status, COUNT(*) as count
            FROM {$this->table}
            WHERE deleted_at IS NULL
            GROUP BY status
        ");
        
        while ($row = $stmt->fetch()) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // حسب النوع
        $stmt = $this->pdo->query("
            SELECT location_type, COUNT(*) as count
            FROM {$this->table}
            WHERE deleted_at IS NULL
            GROUP BY location_type
        ");
        
        while ($row = $stmt->fetch()) {
            $stats['by_type'][$row['location_type']] = $row['count'];
        }
        
        // حسب الشهر
        $stmt = $this->pdo->query("
            SELECT DATE_FORMAT(start_date, '%Y-%m') as month, COUNT(*) as count
            FROM {$this->table}
            WHERE deleted_at IS NULL
            AND start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ");
        
        $stats['monthly'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * تحديث الحالة
     */
    public function updateStatus($id, $status) {
        return $this->update($id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * جلب الفعالية مع التفاصيل الكاملة
     */
    public function getWithDetails($id) {
        return $this->query("
            SELECT e.*, h.name as hall_name
            FROM {$this->table} e
            LEFT JOIN halls h ON e.hall_id = h.id
            WHERE e.id = ?
        ", [$id])[0] ?? null;
    }
}
?>
