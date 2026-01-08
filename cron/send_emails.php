/**
 * Cron Job لمعالجة طابور البريد الإلكتروني
 * يجب تشغيله كل 5 دقائق عبر cron/task scheduler
 * 
 * في Windows Task Scheduler:
 * Program: C:\xampp\php\php.exe
 * Arguments: -f "D:\xampp\htdocs\activity\cron\send_emails.php"
 * Schedule: Every 5 minutes
 * 
 * في Linux Cron:
 * 0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/php /path/to/activity/cron/send_emails.php
 */

// منع الوصول من المتصفح
if (php_sapi_name() !== 'cli' && !isset($_GET['secret'])) {
    die('Access denied. This script should only be run from command line or with secret key.');
}

// تحميل التبعيات
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting email processing...\n";

try {
    $mailer = new Mailer($pdo);
    
    // معالجة 20 بريد في كل مرة
    $result = $mailer->processQueue(20);
    
    echo "Processed: {$result['processed']}\n";
    echo "Sent: {$result['sent']}\n";
    echo "Failed: {$result['failed']}\n";
    
    // تنظيف الرسائل القديمة
    if (date('H') == '03') { // فقط في الساعة 3 صباحاً
        $deleted = $mailer->cleanup(30);
        echo "Cleaned up old emails: {$deleted}\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Email processing completed.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Email cron error: " . $e->getMessage());
    exit(1);
}

exit(0);
?>
