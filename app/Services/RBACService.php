<?php
/**
 * RBAC Service - Role-Based Access Control
 */

class RBACService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * التحقق من صلاحية المستخدم
     */
    public function userHasPermission($userId, $permissionName) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as has_permission
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ? AND p.name = ?
        ");
        
        $stmt->execute([$userId, $permissionName]);
        $result = $stmt->fetch();
        
        return $result['has_permission'] > 0;
    }
    
    /**
     * التحقق من دور المستخدم
     */
    public function userHasRole($userId, $roleName) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as has_role
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.name = ?
        ");
        
        $stmt->execute([$userId, $roleName]);
        $result = $stmt->fetch();
        
        return $result['has_role'] > 0;
    }
    
    /**
     * جلب أدوار المستخدم
     */
    public function getUserRoles($userId) {
        $stmt = $this->pdo->prepare("
            SELECT r.*
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * جلب صلاحيات المستخدم
     */
    public function getUserPermissions($userId) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT p.*
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ?
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * تعيين دور لمستخدم
     */
    public function assignRole($userId, $roleId, $assignedBy = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_roles (user_id, role_id, assigned_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE assigned_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([$userId, $roleId, $assignedBy]);
    }
    
    /**
     * إزالة دور من مستخدم
     */
    public function removeRole($userId, $roleId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_roles
            WHERE user_id = ? AND role_id = ?
        ");
        
        return $stmt->execute([$userId, $roleId]);
    }
    
    /**
     * جلب جميع الأدوار
     */
    public function getAllRoles() {
        return $this->pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
    }
    
    /**
     * جلب جميع الصلاحيات
     */
    public function getAllPermissions() {
        return $this->pdo->query("
            SELECT * FROM permissions 
            ORDER BY module, name
        ")->fetchAll();
    }
    
    /**
     * جلب صلاحيات دور معين
     */
    public function getRolePermissions($roleId) {
        $stmt = $this->pdo->prepare("
            SELECT p.*
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ?
        ");
        
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    }
    
    /**
     * تعيين صلاحيات لدور
     */
    public function assignPermissionsToRole($roleId, $permissionIds) {
        // حذف الصلاحيات الحالية
        $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        
        // إضافة الصلاحيات الجديدة
        if (!empty($permissionIds)) {
            $stmt = $this->pdo->prepare("
                INSERT INTO role_permissions (role_id, permission_id)
                VALUES (?, ?)
            ");
            
            foreach ($permissionIds as $permissionId) {
                $stmt->execute([$roleId, $permissionId]);
            }
        }
        
        return true;
    }
    
    /**
     * إنشاء دور جديد
     */
    public function createRole($name, $displayName, $description = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO roles (name, display_name, description)
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([$name, $displayName, $description]);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * حذف دور
     */
    public function deleteRole($roleId) {
        // التحقق من عدم حذف الأدوار الأساسية
        $stmt = $this->pdo->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();
        
        if (in_array($role['name'], ['super_admin', 'admin'])) {
            throw new Exception('لا يمكن حذف الأدوار الأساسية');
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM roles WHERE id = ?");
        return $stmt->execute([$roleId]);
    }
}

/**
 * دوال مساعدة
 */
function can($permission) {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $rbac = new RBACService($pdo);
    return $rbac->userHasPermission($_SESSION['user_id'], $permission);
}

function hasRole($role) {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $rbac = new RBACService($pdo);
    return $rbac->userHasRole($_SESSION['user_id'], $role);
}
?>
