<?php
/**
 * Logger - نظام تسجيل شامل
 */

class Logger {
    private $logPath;
    private $level;
    private $maxFiles;
    
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';
    
    private $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];
    
    public function __construct($logPath, $level = 'info', $maxFiles = 30) {
        $this->logPath = $logPath;
        $this->level = $level;
        $this->maxFiles = $maxFiles;
        
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }
    }
    
    /**
     * تسجيل رسالة
     */
    public function log($level, $message, $context = []) {
        if ($this->levels[$level] < $this->levels[$this->level]) {
            return; // المستوى أقل من المطلوب
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logFile = $this->logPath . '/' . $level . '_' . date('Y-m-d') . '.log';
        
        $logMessage = sprintf(
            "[%s] %s: %s\n",
            $timestamp,
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $logMessage .= "Context: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
        
        $logMessage .= str_repeat('-', 80) . "\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // تنظيف الملفات القديمة
        $this->cleanOldLogs();
    }
    
    /**
     * Debug
     */
    public function debug($message, $context = []) {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Info
     */
    public function info($message, $context = []) {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Warning
     */
    public function warning($message, $context = []) {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Error
     */
    public function error($message, $context = []) {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Critical
     */
    public function critical($message, $context = []) {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * تنظيف السجلات القديمة
     */
    private function cleanOldLogs() {
        $files = glob($this->logPath . '/*.log');
        
        if (count($files) > $this->maxFiles) {
            // ترتيب حسب التاريخ
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // حذف الملفات الأقدم
            $toDelete = count($files) - $this->maxFiles;
            for ($i = 0; $i < $toDelete; $i++) {
                unlink($files[$i]);
            }
        }
    }
    
    /**
     * قراءة السجلات
     */
    public function read($level = null, $date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        if ($level === null) {
            // قراءة جميع المستويات
            $logs = [];
            foreach (array_keys($this->levels) as $lvl) {
                $logFile = $this->logPath . '/' . $lvl . '_' . $date . '.log';
                if (file_exists($logFile)) {
                    $logs[$lvl] = file_get_contents($logFile);
                }
            }
            return $logs;
        }
        
        $logFile = $this->logPath . '/' . $level . '_' . $date . '.log';
        
        if (file_exists($logFile)) {
            return file_get_contents($logFile);
        }
        
        return null;
    }
}

// Global Logger Instance
$logPath = __DIR__ . '/../../storage/logs';
$GLOBALS['logger'] = new Logger($logPath, 'info', 30);

/**
 * دوال مساعدة
 */
function log_debug($message, $context = []) {
    $GLOBALS['logger']->debug($message, $context);
}

function log_info($message, $context = []) {
    $GLOBALS['logger']->info($message, $context);
}

function log_warning($message, $context = []) {
    $GLOBALS['logger']->warning($message, $context);
}

function log_error($message, $context = []) {
    $GLOBALS['logger']->error($message, $context);
}

function log_critical($message, $context = []) {
    $GLOBALS['logger']->critical($message, $context);
}
?>
