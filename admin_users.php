<?php
/**
 * صفحة إدارة المستخدمين الأدمنز
 * للمدير الرئيسي فقط
 */

require_once 'includes/init.php';
require_once 'includes/rbac.php';
require_once 'includes/audit.php';

// حماية الصفحة: التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من أن المستخدم هو السوبر أدمن
if (!isSuperAdmin($pdo, $_SESSION['user_id'])) {
    set_flash('error', 'ليس لديك الصلاحيات الكافية للوصول إلى هذه الصفحة');
    header("Location: admin.php");
    exit();
}

// معالجة العمليات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        set_flash('error', 'خطأ في التحقق من الأمان. يرجى المحاولة مرة أخرى.');
        header("Location: admin_users.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    
    // إنشاء مستخدم جديد
    if ($action == 'create_user') {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 2); // افتراضي: أدمن عادي
        
        // التحقق من البيانات
        if (empty($username) || empty($password) || empty($full_name)) {
            set_flash('error', 'جميع الحقول مطلوبة');
        } elseif (strlen($password) < 6) {
            set_flash('error', 'كلمة المرور يجب أن تكون 6 أحرف على الأقل');
        } else {
            // التحقق من عدم وجود اسم مستخدم مكرر
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                set_flash('error', 'اسم المستخدم موجود بالفعل');
            } else {
                // إنشاء المستخدم
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, full_name, email, is_super_admin, can_be_deleted, last_password_change)
                    VALUES (?, ?, ?, ?, FALSE, TRUE, NOW())
                ");
                
                if ($stmt->execute([$username, $password_hash, $full_name, $email])) {
                    $new_user_id = $pdo->lastInsertId();
                    
                    // تعيين الدور
                    assignRole($pdo, $new_user_id, $role_id, $_SESSION['user_id']);
                    
                    // تسجيل في الـ Audit Log
                    $logger = new AuditLogger($pdo);
                    $logger->logUserAction('create_user', $new_user_id, [
                        'username' => $username,
                        'role_id' => $role_id,
                        'created_by' => $_SESSION['user_id']
                    ]);
                    
                    set_flash('success', "تم إنشاء المستخدم '$full_name' بنجاح");
                } else {
                    set_flash('error', 'حدث خطأ أثناء إنشاء المستخدم');
                }
            }
        }
        
        header("Location: admin_users.php");
        exit();
    }
    
    // تعديل مستخدم
    if ($action == 'edit_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 2);
        $new_password = $_POST['new_password'] ?? '';
        
        if ($user_id > 0 && !empty($full_name)) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $user_id]);
            
            // تحديث الدور
            // أولاً، إزالة جميع الأدوار
            $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$user_id]);
            // ثم تعيين الدور الجديد
            assignRole($pdo, $user_id, $role_id, $_SESSION['user_id']);
            
            // تغيير كلمة المرور إذا تم إدخالها
            if (!empty($new_password) && strlen($new_password) >= 6) {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, last_password_change = NOW() WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);
            }
            
            set_flash('success', 'تم تحديث بيانات المستخدم بنجاح');
        } else {
            set_flash('error', 'بيانات غير صحيحة');
        }
        
        header("Location: admin_users.php");
        exit();
    }
    
    // حذف مستخدم
    if ($action == 'delete_user') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        $canDelete = canDeleteUser($pdo, $_SESSION['user_id'], $user_id);
        if ($canDelete['can_delete']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                // تسجيل في الـ Audit Log
                $logger = new AuditLogger($pdo);
                $logger->logUserAction('delete_user', $user_id, [
                    'deleted_by' => $_SESSION['user_id']
                ]);
                
                set_flash('success', 'تم حذف المستخدم بنجاح');
            } else {
                set_flash('error', 'حدث خطأ أثناء حذف المستخدم');
            }
        } else {
            set_flash('error', $canDelete['reason']);
        }
        
        header("Location: admin_users.php");
        exit();
    }
}

// جلب جميع المستخدمين
$stmt = $pdo->query("SELECT * FROM users ORDER BY is_super_admin DESC, id ASC");
$users = $stmt->fetchAll();

// جلب الأدوار لكل مستخدم
foreach ($users as &$user) {
    $user['roles'] = getUserRoles($pdo, $user['id']);
}

// جلب جميع الأدوار المتاحة
$all_roles = getAllRoles($pdo);

require_once 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-teal-50 via-sky-50 to-cyan-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-4xl font-black text-teal-900 mb-2">
                    <i class="fas fa-users-cog ml-2 text-teal-500"></i>
                    إدارة المستخدمين
                </h1>
                <p class="text-gray-600">إنشاء وتعديل وحذف حسابات الأدمنز</p>
            </div>
            <div class="flex gap-3">
                <button onclick="openCreateModal()" class="btn-primary px-6 py-3 rounded-xl font-bold shadow-lg">
                    <i class="fas fa-user-plus ml-2"></i>
                    إضافة مستخدم جديد
                </button>
                <a href="admin.php" class="btn-secondary px-6 py-3 rounded-xl font-bold">
                    <i class="fas fa-arrow-right ml-2"></i>
                    العودة
                </a>
            </div>
        </div>

        <?php display_flash_messages(); ?>

        <!-- جدول المستخدمين -->
        <div class="shimal-card bg-white shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-teal-500 text-white">
                        <tr>
                            <th class="px-6 py-4 text-right font-black">اسم المستخدم</th>
                            <th class="px-6 py-4 text-right font-black">الاسم الكامل</th>
                            <th class="px-6 py-4 text-right font-black">البريد الإلكتروني</th>
                            <th class="px-6 py-4 text-right font-black">الدور</th>
                            <th class="px-6 py-4 text-right font-black">تاريخ الإنشاء</th>
                            <th class="px-6 py-4 text-center font-black">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-teal-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <i class="fas fa-user-circle text-2xl text-teal-500 ml-2"></i>
                                    <span class="font-bold"><?= htmlspecialchars($user['username']) ?></span>
                                    <?php if ($user['is_super_admin']): ?>
                                        <span class="mr-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full font-bold">
                                            <i class="fas fa-crown"></i> مدير رئيسي
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4"><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                            <td class="px-6 py-4">
                                <?php if (!empty($user['roles'])): ?>
                                    <?php foreach ($user['roles'] as $role): ?>
                                        <span class="inline-block px-3 py-1 bg-teal-100 text-teal-800 rounded-full text-sm font-bold mb-1">
                                            <?= htmlspecialchars($role['display_name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?= date('Y-m-d', strtotime($user['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button onclick='openEditModal(<?= json_encode($user) ?>)' 
                                        class="text-blue-600 hover:text-blue-800 ml-3">
                                    <i class="fas fa-edit"></i> تعديل
                                </button>
                                <?php 
                                $canDelete = canDeleteUser($pdo, $_SESSION['user_id'], $user['id']);
                                if ($canDelete['can_delete']): 
                                ?>
                                    <button onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" 
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 cursor-not-allowed" title="<?= htmlspecialchars($canDelete['reason']) ?>">
                                        <i class="fas fa-lock"></i> محمي
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: إنشاء مستخدم جديد -->
<div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-teal-500 text-white p-6 rounded-t-2xl">
            <h2 class="text-2xl font-black">
                <i class="fas fa-user-plus ml-2"></i>
                إضافة مستخدم جديد
            </h2>
        </div>
        
        <form method="POST" class="p-6">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="create_user">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block font-bold mb-2 text-gray-700">
                        <i class="fas fa-user ml-1 text-teal-500"></i>
                        اسم المستخدم
                    </label>
                    <input type="text" name="username" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                </div>
                
                <div>
                    <label class="block font-bold mb-2 text-gray-700">
                        <i class="fas fa-id-card ml-1 text-teal-500"></i>
                        الاسم الكامل
                    </label>
                    <input type="text" name="full_name" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                </div>
                
                <div>
                    <label class="block font-bold mb-2 text-gray-700">
                        <i class="fas fa-envelope ml-1 text-teal-500"></i>
                        البريد الإلكتروني
                    </label>
                    <input type="email" name="email"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                </div>
                
                <div>
                    <label class="block font-bold mb-2 text-gray-700">
                        <i class="fas fa-user-tag ml-1 text-teal-500"></i>
                        الدور
                    </label>
                    <select name="role_id" required
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                        <?php foreach ($all_roles as $role): ?>
                            <?php if ($role['name'] != 'super_admin'): // منع إنشاء super admin ?>
                                <option value="<?= $role['id'] ?>" <?= $role['id'] == 2 ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role['display_name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block font-bold mb-2 text-gray-700">
                        <i class="fas fa-lock ml-1 text-teal-500"></i>
                        كلمة المرور
                    </label>
                    <input type="password" name="password" required minlength="6"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-200">
                    <p class="text-sm text-gray-500 mt-1">يجب أن تكون 6 أحرف على الأقل</p>
                </div>
            </div>
            
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeCreateModal()" 
                        class="px-6 py-2 bg-gray-300 text-gray-700 rounded-xl font-bold hover:bg-gray-400">
                    إلغاء
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-teal-500 text-white rounded-xl font-bold hover:bg-teal-600 shadow-lg">
                    <i class="fas fa-save ml-2"></i>
                    إنشاء المستخدم
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: تعديل مستخدم -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-blue-500 text-white p-6 rounded-t-2xl">
            <h2 class="text-2xl font-black">
                <i class="fas fa-edit ml-2"></i>
                تعديل بيانات المستخدم
            </h2>
        </div>
        
        <form method="POST" class="p-6" id="editForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="md:col-span-2">
                    <label class="block font-bold mb-2 text-gray-700">اسم المستخدم (للعرض فقط)</label>
                    <input type="text" id="edit_username" disabled
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl bg-gray-100">
                </div>
                
                <div>
                    <label class="block font-bold mb-2 text-gray-700">الاسم الكامل</label>
                    <input type="text" name="full_name" id="edit_full_name" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                </div>
                
                <div>
                    <label class="block font-bold mb-2 text-gray-700">البريد الإلكتروني</label>
                    <input type="email" name="email" id="edit_email"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block font-bold mb-2 text-gray-700">الدور</label>
                    <select name="role_id" id="edit_role_id" required
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                        <?php foreach ($all_roles as $role): ?>
                            <?php if ($role['name'] != 'super_admin'): ?>
                                <option value="<?= $role['id'] ?>">
                                    <?= htmlspecialchars($role['display_name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block font-bold mb-2 text-gray-700">كلمة المرور الجديدة (اختياري)</label>
                    <input type="password" name="new_password" minlength="6"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    <p class="text-sm text-gray-500 mt-1">اتركه فارغاً إذا كنت لا تريد تغيير كلمة المرور</p>
                </div>
            </div>
            
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeEditModal()" 
                        class="px-6 py-2 bg-gray-300 text-gray-700 rounded-xl font-bold hover:bg-gray-400">
                    إلغاء
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-600 shadow-lg">
                    <i class="fas fa-save ml-2"></i>
                    حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Form: حذف مستخدم (مخفي) -->
<form method="POST" id="deleteForm" class="hidden">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}

function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_full_name').value = user.full_name || '';
    document.getElementById('edit_email').value = user.email || '';
    
    // تعيين الدور
    if (user.roles && user.roles.length > 0) {
        document.getElementById('edit_role_id').value = user.roles[0].id;
    }
    
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function confirmDelete(userId, username) {
    if (confirm(`هل أنت متأكد من حذف المستخدم "${username}"؟\n\nهذا الإجراء لا يمكن التراجع عنه.`)) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteForm').submit();
    }
}

// إغلاق الـ modals عند الضغط خارجها
window.onclick = function(event) {
    const createModal = document.getElementById('createModal');
    const editModal = document.getElementById('editModal');
    
    if (event.target == createModal) {
        closeCreateModal();
    }
    if (event.target == editModal) {
        closeEditModal();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
