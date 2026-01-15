<?php
/**
 * Base Model Class
 * جميع النماذج ترث من هذا الكلاس الأساسي
 */

abstract class BaseModel {
    protected $pdo;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * جلب جميع السجلات
     */
    public function all($where = [], $orderBy = null) {
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "{$key} = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($where));
        return $stmt->fetchAll();
    }
    
    /**
     * جلب سجل واحد حسب ID
     */
    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * جلب سجل واحد حسب شروط
     */
    public function findWhere($where) {
        $conditions = [];
        $params = [];
        
        foreach ($where as $key => $value) {
            $conditions[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $conditions);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * إنشاء سجل جديد
     */
    public function create($data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * تحديث سجل
     */
    public function update($id, $data) {
        $sets = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " 
                WHERE {$this->primaryKey} = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * حذف سجل (حذف صلب)
     */
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * حذف منطقي (soft delete)
     */
    public function softDelete($id) {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * استعادة من الحذف المنطقي
     */
    public function restore($id) {
        return $this->update($id, ['deleted_at' => null]);
    }
    
    /**
     * عد السجلات
     */
    public function count($where = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "{$key} = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($where));
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
    }
    
    /**
     * Pagination
     */
    public function paginate($page = 1, $perPage = 20, $where = [], $orderBy = null) {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "{$key} = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($where));
        
        return [
            'data' => $stmt->fetchAll(),
            'total' => $this->count($where),
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($this->count($where) / $perPage)
        ];
    }

    /**
     * استعلام مخصص
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * بداية معاملة (transaction)
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * إنهاء معاملة
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * إلغاء معاملة
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
}
?>
