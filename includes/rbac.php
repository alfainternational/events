<?php
/**
 * RBAC - Role-Based Access Control System
 * نظام التحكم بالصلاحيات القائم على الأدوار
 */

/**
 * فحص ما إذا كان المستخدم يمتلك صلاحية معينة
 * @param PDO $pdo
 * @param int $userId
 * @param string $permissionName
 * @return bool
 */
function hasPermission($pdo, $userId, $permissionName) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ? AND p.name = ?
        ");
        $stmt->execute([$userId, $permissionName]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking permission: " . $e->getMessage());
        return false;
    }
}

/**
 * فحص ما إذا كان المستخدم يمتلك دور معين
 * @param PDO $pdo
 * @param int $userId
 * @param string $roleName
 * @return bool
 */
function hasRole($pdo, $userId, $roleName) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.name = ?
        ");
        $stmt->execute([$userId, $roleName]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking role: " . $e->getMessage());
        return false;
    }
}

/**
 * فحص ما إذا كان المستخدم هو السوبر أدمن
 * @param PDO $pdo
 * @param int $userId
 * @return bool
 */
function isSuperAdmin($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result && $result['is_super_admin'] == 1;
    } catch (PDOException $e) {
        error_log("Error checking super admin: " . $e->getMessage());
        return false;
    }
}

/**
 * التحقق من إمكانية حذف مستخدم
 * @param PDO $pdo
 * @param int $deleterId المستخدم الذي يريد الحذف
 * @param int $targetId المستخدم المراد حذفه
 * @return array ['can_delete' => bool, 'reason' => string]
 */
function canDeleteUser($pdo, $deleterId, $targetId) {
    // لا يمكن حذف نفسك
    if ($deleterId == $targetId) {
        return ['can_delete' => false, 'reason' => 'لا يمكنك حذف حسابك الخاص'];
    }
    
    // فحص إذا كان المستخدم المراد حذفه محمي
    $stmt = $pdo->prepare("SELECT is_super_admin, can_be_deleted FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    
    if (!$target) {
        return ['can_delete' => false, 'reason' => 'المستخدم غير موجود'];
    }
    
    // لا يمكن حذف السوبر أدمن
    if ($target['is_super_admin'] == 1) {
        return ['can_delete' => false, 'reason' => 'لا يمكن حذف المدير الرئيسي للنظام'];
    }
    
    // لا يمكن حذف مستخدم محمي
    if ($target['can_be_deleted'] == 0) {
        return ['can_delete' => false, 'reason' => 'هذا المستخدم محمي من الحذف'];
    }
    
    // التحقق من صلاحية الحذف
    if (!isSuperAdmin($pdo, $deleterId)) {
        return ['can_delete' => false, 'reason' => 'ليس لديك صلاحية حذف المستخدمين'];
    }
    
    return ['can_delete' => true, 'reason' => ''];
}

/**
 * الحصول على جميع صلاحيات المستخدم
 * @param PDO $pdo
 * @param int $userId
 * @return array
 */
function getUserPermissions($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.name, p.display_name, p.description, p.module
            FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ?
            ORDER BY p.module, p.display_name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting user permissions: " . $e->getMessage());
        return [];
    }
}

/**
 * الحصول على أدوار المستخدم
 * @param PDO $pdo
 * @param int $userId
 * @return array
 */
function getUserRoles($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.name, r.display_name, r.description
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting user roles: " . $e->getMessage());
        return [];
    }
}

/**
 * تعيين دور لمستخدم
 * @param PDO $pdo
 * @param int $userId
 * @param int $roleId
 * @param int $assignedBy
 * @return bool
 */
function assignRole($pdo, $userId, $roleId, $assignedBy) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_roles (user_id, role_id, assigned_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE assigned_by = ?
        ");
        return $stmt->execute([$userId, $roleId, $assignedBy, $assignedBy]);
    } catch (PDOException $e) {
        error_log("Error assigning role: " . $e->getMessage());
        return false;
    }
}

/**
 * إزالة دور من مستخدم
 * @param PDO $pdo
 * @param int $userId
 * @param int $roleId
 * @return bool
 */
function removeRole($pdo, $userId, $roleId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
        return $stmt->execute([$userId, $roleId]);
    } catch (PDOException $e) {
        error_log("Error removing role: " . $e->getMessage());
        return false;
    }
}

/**
 * الحصول على جميع الأدوار المتاحة
 * @param PDO $pdo
 * @return array
 */
function getAllRoles($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM roles ORDER BY id");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting all roles: " . $e->getMessage());
        return [];
    }
}

/**
 * فحص الوصول إلى صفحة معينة بناءً على الصلاحية
 * @param PDO $pdo
 * @param int $userId
 * @param string $requiredPermission
 * @param string $redirectUrl
 */
function requirePermission($pdo, $userId, $requiredPermission, $redirectUrl = 'admin.php') {
    if (!hasPermission($pdo, $userId, $requiredPermission) && !isSuperAdmin($pdo, $userId)) {
        set_flash('error', 'ليس لديك الصلاحيات الكافية للوصول إلى هذه الصفحة');
        header("Location: $redirectUrl");
        exit();
    }
}
?>
