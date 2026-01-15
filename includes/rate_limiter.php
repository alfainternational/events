<?php
/**
 * نظام Rate Limiting لمنع إساءة الاستخدام
 */

class RateLimiter {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * التحقق من السماح بالعملية
     * @param string $identifier معرف (IP أو user_id)
     * @param string $action نوع العملية
     * @param int $maxAttempts أقصى عدد محاولات
     * @param int $windowMinutes نافذة الوقت بالدقائق
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => timestamp]
     */
    public function check($identifier, $action, $maxAttempts = 5, $windowMinutes = 5) {
        // تنظيف السجلات القديمة
        $this->cleanup();
        
        // التحقق من الحظر الحالي
        $stmt = $this->pdo->prepare("
            SELECT blocked_until 
            FROM rate_limits 
            WHERE identifier = ? AND action = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$identifier, $action]);
        $blocked = $stmt->fetch();
        
        if ($blocked) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => strtotime($blocked['blocked_until']),
                'message' => 'تم حظرك مؤقتاً. يرجى المحاولة لاحقاً.'
            ];
        }
        
        // حساب المحاولات في النافذة الزمنية
        $stmt = $this->pdo->prepare("
            SELECT id, attempts, last_attempt 
            FROM rate_limits 
            WHERE identifier = ? AND action = ? 
            AND last_attempt > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$identifier, $action, $windowMinutes]);
        $record = $stmt->fetch();
        
        if ($record) {
            $attempts = $record['attempts'];
            
            if ($attempts >= $maxAttempts) {
                // حظر لمدة النافذة الزمنية
                $blockedUntil = date('Y-m-d H:i:s', time() + ($windowMinutes * 60));
                $stmt = $this->pdo->prepare("
                    UPDATE rate_limits 
                    SET blocked_until = ?, attempts = attempts + 1
                    WHERE id = ?
                ");
                $stmt->execute([$blockedUntil, $record['id']]);
                
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => strtotime($blockedUntil),
                    'message' => "تجاوزت الحد المسموح. حُظرت لمدة {$windowMinutes} دقائق."
                ];
            }
            
            // تحديث عدد المحاولات
            $stmt = $this->pdo->prepare("
                UPDATE rate_limits 
                SET attempts = attempts + 1, last_attempt = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$record['id']]);
            
            return [
                'allowed' => true,
                'remaining' => $maxAttempts - $attempts - 1,
                'reset_at' => strtotime($record['last_attempt']) + ($windowMinutes * 60),
                'message' => null
            ];
        } else {
            // أول محاولة
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (identifier, action, attempts) 
                VALUES (?, ?, 1)
            ");
            $stmt->execute([$identifier, $action]);
            
            return [
                'allowed' => true,
                'remaining' => $maxAttempts - 1,
                'reset_at' => time() + ($windowMinutes * 60),
                'message' => null
            ];
        }
    }
    
    /**
     * تسجيل محاولة (للاستخدام بعد التحقق)
     */
    public function hit($identifier, $action) {
        // تُستدعى ضمنياً في check()
        return true;
    }
    
    /**
     * إعادة تعيين محاولات معينة
     */
    public function reset($identifier, $action) {
        $stmt = $this->pdo->prepare("
            DELETE FROM rate_limits 
            WHERE identifier = ? AND action = ?
        ");
        return $stmt->execute([$identifier, $action]);
    }
    
    /**
     * تنظيف السجلات القديمة (أكثر من 24 ساعة)
     */
    private function cleanup() {
        $stmt = $this->pdo->prepare("
            DELETE FROM rate_limits 
            WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR) 
            AND (blocked_until IS NULL OR blocked_until < NOW())
        ");
        $stmt->execute();
    }
    
    /**
     * الحصول على معرّف من IP
     */
    public static function getIdentifier() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }
}

/**
 * دوال مساعدة سريعة
 */

function check_rate_limit($action, $maxAttempts = 5, $windowMinutes = 5) {
    global $pdo;
    $limiter = new RateLimiter($pdo);
    $identifier = RateLimiter::getIdentifier();
    return $limiter->check($identifier, $action, $maxAttempts, $windowMinutes);
}

function reset_rate_limit($action) {
    global $pdo;
    $limiter = new RateLimiter($pdo);
    $identifier = RateLimiter::getIdentifier();
    return $limiter->reset($identifier, $action);
}
?>
