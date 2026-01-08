<?php
/**
 * Error Handler - معالج الأخطاء المركزي
 */

class ErrorHandler {
    private $logPath;
    private $displayErrors;
    
    public function __construct($logPath, $displayErrors = false) {
        $this->logPath = $logPath;
        $this->displayErrors = $displayErrors;
        
        // تسجيل معالجات الأخطاء
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    /**
     * معالج الأخطاء
     */
    public function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error = [
            'type' => 'Error',
            'severity' => $this->getSeverityString($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $this->logError($error);
        
        if ($this->displayErrors) {
            $this->displayError($error);
        } else {
            $this->displayGenericError();
        }
        
        return true;
    }
    
    /**
     * معالج الاستثناءات
     */
    public function handleException($exception) {
        $error = [
            'type' => 'Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $this->logError($error);
        
        if ($this->displayErrors) {
            $this->displayException($error);
        } else {
            $this->displayGenericError();
        }
    }
    
    /**
     * معالج الإغلاق (Fatal errors)
     */
    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $errorData = [
                'type' => 'Fatal Error',
                'severity' => $this->getSeverityString($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ];
            
            $this->logError($errorData);
            
            if (!$this->displayErrors) {
                $this->displayGenericError();
            }
        }
    }
    
    /**
     * تسجيل الخطأ
     */
    private function logError($error) {
        $logFile = $this->logPath . '/error_' . date('Y-m-d') . '.log';
        
        $logMessage = sprintf(
            "[%s] %s: %s in %s on line %d\n",
            $error['timestamp'],
            $error['type'],
            $error['message'],
            $error['file'] ?? 'unknown',
            $error['line'] ?? 0
        );
        
        if (isset($error['trace'])) {
            $logMessage .= "Stack trace:\n" . $error['trace'] . "\n";
        }
        
        $logMessage .= "IP: " . $error['ip'] . " | URL: " . $error['url'] . "\n";
        $logMessage .= str_repeat('-', 80) . "\n";
        
        // إنشاء المجلد إذا لم يكن موجوداً
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * عرض رسالة خطأ عامة
     */
    private function displayGenericError() {
        http_response_code(500);
        
        if (php_sapi_name() === 'cli') {
            echo "حدث خطأ في النظام. يرجى المحاولة لاحقاً.\n";
            return;
        }
        
        // التحقق من طلبات AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'حدث خطأ في النظام',
                'message' => 'يرجى المحاولة لاحقاً أو الاتصال بالدعم الفني'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // عرض صفحة خطأ
        ?>
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>خطأ في النظام</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 20px;
                    padding: 40px;
                    max-width: 500px;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                .error-icon {
                    font-size: 80px;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #333;
                    margin-bottom: 15px;
                    font-size: 28px;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                    margin-bottom: 25px;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 30px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 50px;
                    font-weight: bold;
                    transition: all 0.3s;
                }
                .btn:hover {
                    background: #764ba2;
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h1>عذراً، حدث خطأ</h1>
                <p>نعتذر عن الإزعاج. حدث خطأ غير متوقع في النظام. فريقنا يعمل على حل المشكلة.</p>
                <a href="/" class="btn">العودة للصفحة الرئيسية</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * عرض تفاصيل الخطأ (Development)
     */
    private function displayError($error) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; margin: 20px; border-radius: 5px; color: #721c24; font-family: monospace;'>";
        echo "<h3>{$error['type']}: {$error['severity']}</h3>";
        echo "<p><strong>Message:</strong> {$error['message']}</p>";
        echo "<p><strong>File:</strong> {$error['file']}</p>";
        echo "<p><strong>Line:</strong> {$error['line']}</p>";
        echo "</div>";
    }
    
    /**
     * عرض تفاصيل الاستثناء (Development)
     */
    private function displayException($error) {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; margin: 20px; border-radius: 5px; color: #721c24; font-family: monospace;'>";
        echo "<h3>{$error['type']}: {$error['class']}</h3>";
        echo "<p><strong>Message:</strong> {$error['message']}</p>";
        echo "<p><strong>File:</strong> {$error['file']}</p>";
        echo "<p><strong>Line:</strong> {$error['line']}</p>";
        echo "<pre style='background: #fff; padding: 10px; border-radius: 3px; overflow-x: auto;'>{$error['trace']}</pre>";
        echo "</div>";
    }
    
    /**
     * تحويل رمز الخطورة لنص
     */
    private function getSeverityString($severity) {
        $severities = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $severities[$severity] ?? 'UNKNOWN';
    }
}
?>
