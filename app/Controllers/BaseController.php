<?php
/**
 * Base Controller
 * جميع الـ Controllers ترث من هذا الكلاس
 */

abstract class BaseController {
    protected $pdo;
    protected $request;
    protected $session;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->request = $_REQUEST;
        $this->session = $_SESSION;
    }
    
    /**
     * Render view
     */
    protected function view($view, $data = []) {
        extract($data);
        $viewFile = __DIR__ . '/../../views/' . $view . '.php';
        
        if (!file_exists($viewFile)) {
            throw new Exception("View not found: {$view}");
        }
        
        include __DIR__ . '/../../includes/header.php';
        include $viewFile;
        include __DIR__ . '/../../includes/footer.php';
    }
    
    /**
     * JSON response
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Redirect
     */
    protected function redirect($url, $message = null, $type = 'success') {
        if ($message) {
            require_once __DIR__ . '/../../includes/messages.php';
            set_flash($type, $message);
        }
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Get request data
     */
    protected function input($key, $default = null) {
        return $this->request[$key] ?? $default;
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCsrf() {
        require_once __DIR__ . '/../../includes/csrf.php';
        
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
        }
    }
    
    /**
     * Check authentication
     */
    protected function requireAuth() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/activity/login.php', 'يجب تسجيل الدخول أولاً', 'error');
        }
    }
    
    /**
     * Check permission
     */
    protected function requirePermission($permission) {
        $this->requireAuth();
        
        require_once __DIR__ . '/../Services/RBACService.php';
        $rbac = new RBACService($this->pdo);
        
        if (!$rbac->userHasPermission($_SESSION['user_id'], $permission)) {
            $this->json(['error' => 'ليس لديك صلاحية للوصول'], 403);
        }
    }
    
    /**
     * Validate input
     */
    protected function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $rule);
            
            foreach ($ruleList as $r) {
                if ($r === 'required' && empty($value)) {
                    $errors[$field] = "الحقل {$field} مطلوب";
                }
                
                if (strpos($r, 'min:') === 0) {
                    $min = (int) substr($r, 4);
                    if (strlen($value) < $min) {
                        $errors[$field] = "الحد الأدنى {$min} حرف";
                    }
                }
                
                if (strpos($r, 'max:') === 0) {
                    $max = (int) substr($r, 4);
                    if (strlen($value) > $max) {
                        $errors[$field] = "الحد الأقصى {$max} حرف";
                    }
                }
                
                if ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "البريد الإلكتروني غير صحيح";
                }
                
                if ($r === 'date' && !strtotime($value)) {
                    $errors[$field] = "التاريخ غير صحيح";
                }
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Log action
     */
    protected function logAction($action, $resourceId = null, $oldData = null, $newData = null) {
        require_once __DIR__ . '/../../includes/audit.php';
        $logger = new AuditLogger($this->pdo);
        $logger->logEvent($action, $resourceId, $oldData, $newData);
    }
}
?>
