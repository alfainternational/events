<?php
/**
 * نظام البريد الإلكتروني - Mailer Class
 */

class Mailer {
    private $pdo;
    private $from_email;
    private $from_name;
    private $enabled;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }
    
    /**
     * تحميل إعدادات البريد
     */
    private function loadSettings() {
        $this->from_email = get_system_setting('email_from_address', 'noreply@shmial.edu.sa', $this->pdo);
        $this->from_name = get_system_setting('email_from_name', 'نظام الفعاليات', $this->pdo);
        $this->enabled = get_system_setting('email_enabled', '0', $this->pdo) == '1';
    }
    
    /**
     * إرسال بريد باستخدام قالب
     */
    public function sendFromTemplate($templateKey, $toEmail, $toName, $variables = []) {
        if (!$this->enabled) {
            error_log("Email system is disabled");
            return false;
        }
        
        // جلب القالب
        $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE template_key = ?");
        $stmt->execute([$templateKey]);
        $template = $stmt->fetch();
        
        if (!$template) {
            error_log("Email template not found: {$templateKey}");
            return false;
        }
        
        // استبدال المتغيرات
        $subject = $this->replaceVariables($template['subject'], $variables);
        $bodyText = $this->replaceVariables($template['body_text'], $variables);
        $bodyHtml = $template['body_html'] ? $this->replaceVariables($template['body_html'], $variables) : null;
        
        return $this->queue($toEmail, $toName, $subject, $bodyText, $bodyHtml);
    }
    
    /**
     * إضافة بريد إلى الطابور
     */
    public function queue($toEmail, $toName, $subject, $bodyText, $bodyHtml = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_queue (to_email, to_name, subject, body, html_body) 
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$toEmail, $toName, $subject, $bodyText, $bodyHtml]);
        } catch (\PDOException $e) {
            error_log("Failed to queue email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * معالجة طابور البريد (يُستدعى من cron job)
     */
    public function processQueue($limit = 10) {
        if (!$this->enabled) {
            return ['sent' => 0, 'failed' => 0, 'message' => 'Email system disabled'];
        }
        
        // جلب الرسائل المعلقة
        $stmt = $this->pdo->prepare("
            SELECT * FROM email_queue 
            WHERE status = 'pending' AND attempts < max_attempts 
            ORDER BY created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll();
        
        $sent = 0;
        $failed = 0;
        
        foreach ($emails as $email) {
            if ($this->send($email)) {
                $sent++;
            } else {
                $failed++;
            }
        }
        
        return [
            'sent' => $sent,
            'failed' => $failed,
            'processed' => count($emails)
        ];
    }
    
    /**
     * إرسال بريد واحد من الطابور
     */
    private function send($emailRecord) {
        $id = $emailRecord['id'];
        
        try {
            // تحديث عدد المحاولات
            $stmt = $this->pdo->prepare("
                UPDATE email_queue 
                SET attempts = attempts + 1, last_attempt = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            // إعداد الرؤوس
            $headers = [];
            $headers[] = "From: {$this->from_name} <{$this->from_email}>";
            $headers[] = "Reply-To: {$this->from_email}";
            $headers[] = "X-Mailer: PHP/" . phpversion();
            $headers[] = "MIME-Version: 1.0";
            
            $body = $emailRecord['body'];
            
            // إذا كان هناك HTML
            if ($emailRecord['html_body']) {
                $boundary = md5(time());
                $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
                
                $body = "--{$boundary}\r\n";
                $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                $body .= $emailRecord['body'] . "\r\n\r\n";
                
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: text/html; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
                $body .= $emailRecord['html_body'] . "\r\n\r\n";
                $body .= "--{$boundary}--";
            } else {
                $headers[] = "Content-Type: text/plain; charset=UTF-8";
            }
            
            // الإرسال
            $success = mail(
                $emailRecord['to_email'],
                $emailRecord['subject'],
                $body,
                implode("\r\n", $headers)
            );
            
            if ($success) {
                // تحديث الحالة إلى sent
                $stmt = $this->pdo->prepare("
                    UPDATE email_queue 
                    SET status = 'sent', sent_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                return true;
            } else {
                throw new \Exception("mail() function returned false");
            }
            
        } catch (\Exception $e) {
            // تحديث الحالة إلى failed إذا وصلنا للحد الأقصى من المحاولات
            $newStatus = ($emailRecord['attempts'] + 1 >= $emailRecord['max_attempts']) ? 'failed' : 'pending';
            
            $stmt = $this->pdo->prepare("
                UPDATE email_queue 
                SET status = ?, error_message = ? 
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $e->getMessage(), $id]);
            
            error_log("Failed to send email #{$id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * استبدال المتغيرات في النص
     */
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace("{{" . $key . "}}", $value, $text);
        }
        return $text;
    }
    
    /**
     * حذف الرسائل القديمة (المرسلة/الفاشلة لأكثر من 30 يوم)
     */
    public function cleanup($daysToKeep = 30) {
        $stmt = $this->pdo->prepare("
            DELETE FROM email_queue 
            WHERE status IN ('sent', 'failed') 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        return $stmt->execute([$daysToKeep]);
    }
    
    /**
     * الحصول على إحصائيات البريد
     */
    public function getStats() {
        $stmt = $this->pdo->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM email_queue
            GROUP BY status
        ");
        
        $stats = [
            'pending' => 0,
            'sent' => 0,
            'failed' => 0
        ];
        
        while ($row = $stmt->fetch()) {
            $stats[$row['status']] = $row['count'];
        }
        
        return $stats;
    }
}

/**
 * دوال مساعدة سريعة
 */
function send_email_template($templateKey, $toEmail, $toName, $variables = []) {
    global $pdo;
    $mailer = new Mailer($pdo);
    return $mailer->sendFromTemplate($templateKey, $toEmail, $toName, $variables);
}

function queue_email($toEmail, $toName, $subject, $body, $htmlBody = null) {
    global $pdo;
    $mailer = new Mailer($pdo);
    return $mailer->queue($toEmail, $toName, $subject, $body, $htmlBody);
}

/**
 * إرسال بريد إعادة تعيين كلمة المرور
 */
function send_password_reset_email($userId, $userEmail, $userName) {
    global $pdo;
    
    // توليد رمز (token) آمن
    $token = bin2hex(random_bytes(32));
    
    // حفظ الرمز في قاعدة البيانات (صالح لمدة ساعة)
    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (user_id, token, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ");
    $stmt->execute([$userId, $token]);
    
    // إنشاء رابط إعادة التعيين
    $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
    
    // إرسال البريد
    $subject = "إعادة تعيين كلمة المرور - نظام الفعاليات";
    $body = "
السلام عليكم {$userName},

تم طلب إعادة تعيين كلمة المرور لحسابك.

للمتابعة، يرجى الضغط على الرابط التالي:
{$resetLink}

الرابط صالح لمدة ساعة واحدة فقط.

إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذه الرسالة.

مع تحيات،
نظام إدارة الفعاليات
    ";
    
    $htmlBody = "
<!DOCTYPE html>
<html dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'Arial', sans-serif; background: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 10px; }
        .header { background: #14b8a6; color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .content { padding: 20px; }
        .button { display: inline-block; background: #14b8a6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; color: #777; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>إعادة تعيين كلمة المرور</h2>
        </div>
        <div class='content'>
            <p>السلام عليكم <strong>{$userName}</strong>،</p>
            <p>تم طلب إعادة تعيين كلمة المرور لحسابك في نظام إدارة الفعاليات.</p>
            <p><a href='{$resetLink}' class='button'>إعادة تعيين كلمة المرور</a></p>
            <p style='color: #999; font-size: 12px;'>أو انسخ الرابط التالي في المتصفح:<br>{$resetLink}</p>
            <p><strong>ملاحظة:</strong> الرابط صالح لمدة ساعة واحدة فقط.</p>
            <p>إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذه الرسالة.</p>
        </div>
        <div class='footer'>
            <p>نظام إدارة الفعاليات - كلية الشمال</p>
        </div>
    </div>
</body>
</html>
    ";
    
    return queue_email($userEmail, $userName, $subject, $body, $htmlBody);
}
?>
