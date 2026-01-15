<?php
/**
 * User Model
 */

require_once __DIR__ . '/BaseModel.php';

class UserModel extends BaseModel {
    protected $table = 'users';
    
    /**
     * جلب مستخدم حسب اسم المستخدم
     */
    public function findByUsername($username) {
        return $this->findWhere(['username' => $username]);
    }
    
    /**
     * جلب مستخدم حسب البريد
     */
    public function findByEmail($email) {
        return $this->findWhere(['email' => $email]);
    }
    
    /**
     * إنشاء مستخدم جديد
     */
    public function createUser($data) {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        
        return $this->create($data);
    }
    
    /**
     * تحديث كلمة المرور
     */
    public function updatePassword($id, $newPassword) {
        return $this->update($id, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * التحقق من كلمة المرور
     */
    public function verifyPassword($username, $password) {
        $user = $this->findByUsername($username);
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password_hash']) ? $user : false;
    }
    
    /**
     * تحديث معلومات تسجيل الدخول
     */
    public function updateLoginInfo($id, $ipAddress) {
        return $this->update($id, [
            'last_login' => date('Y-m-d H:i:s'),
            'last_ip' => $ipAddress
        ]);
    }
    
    /**
     * جلب مستخدمي حسب الدور
     */
    public function getByRole($role) {
        return $this->all(['role' => $role]);
    }
}
?>
