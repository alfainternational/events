<?php
/**
 * Migration Runner
 * تنفيذ migrations بشكل آمن ومنظم
 *
 * الاستخدام: php run_migrations.php
 */

// تحميل الإعدادات
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../includes/db.php';

// التأكد من أن الأمر يتم تشغيله من CLI فقط
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line.\n");
}

echo "===========================================\n";
echo "   Migration Runner - نظام إدارة الفعاليات\n";
echo "===========================================\n\n";

try {
    // التحقق من وجود جدول migrations
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    echo "✓ جدول Migrations جاهز\n\n";

    // الحصول على قائمة migrations المنفذة
    $stmt = $pdo->query("SELECT migration_name FROM migrations");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Migrations المنفذة سابقاً: " . count($executedMigrations) . "\n";
    foreach ($executedMigrations as $migration) {
        echo "  - $migration\n";
    }
    echo "\n";

    // قراءة ملفات migrations من المجلد
    $migrationFiles = glob(__DIR__ . '/*.sql');
    sort($migrationFiles); // ترتيب حسب الاسم (رقمي)

    $newMigrations = 0;

    foreach ($migrationFiles as $file) {
        $filename = basename($file);
        $migrationName = pathinfo($filename, PATHINFO_FILENAME);

        // تجاهل إذا تم تنفيذه مسبقاً
        if (in_array($migrationName, $executedMigrations)) {
            continue;
        }

        echo "تنفيذ: $filename ... ";

        // قراءة محتوى الملف
        $sql = file_get_contents($file);

        // تقسيم إلى statements منفصلة
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($statement) {
                return !empty($statement) &&
                       !preg_match('/^--/', $statement) &&
                       !preg_match('/^USE\s+/i', $statement);
            }
        );

        // بدء transaction
        $pdo->beginTransaction();

        try {
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $pdo->exec($statement);
                }
            }

            // تسجيل Migration
            $stmt = $pdo->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
            $stmt->execute([$migrationName]);

            // Commit
            $pdo->commit();

            echo "✓ تم بنجاح\n";
            $newMigrations++;

        } catch (Exception $e) {
            // Rollback في حالة الخطأ
            $pdo->rollBack();
            echo "✗ فشل\n";
            echo "خطأ: " . $e->getMessage() . "\n";
            // الاستمرار مع بقية migrations
        }
    }

    echo "\n";
    echo "===========================================\n";
    echo "النتيجة: تم تنفيذ $newMigrations migration(s) جديدة\n";
    echo "===========================================\n";

} catch (PDOException $e) {
    echo "✗ خطأ في الاتصال بقاعدة البيانات:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>
