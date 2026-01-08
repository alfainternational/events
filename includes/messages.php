<?php
/**
 * نظام Flash Messages لعرض الرسائل بعد إعادة التوجيه
 */

/**
 * تعيين رسالة flash
 * @param string $type نوع الرسالة (success, error, warning, info)
 * @param string $message محتوى الرسالة
 */
function set_flash($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][$type] = $message;
}

/**
 * الحصول على رسالة flash وحذفها
 * @param string $type نوع الرسالة
 * @return string|null
 */
function get_flash($type) {
    if (isset($_SESSION['flash_messages'][$type])) {
        $message = $_SESSION['flash_messages'][$type];
        unset($_SESSION['flash_messages'][$type]);
        return $message;
    }
    return null;
}

/**
 * عرض جميع رسائل Flash بتنسيق HTML
 */
function display_flash_messages() {
    $types = ['success', 'error', 'warning', 'info'];
    $colors = [
        'success' => 'bg-green-50 text-green-700 border-green-200',
        'error' => 'bg-red-50 text-red-700 border-red-200',
        'warning' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'info' => 'bg-blue-50 text-blue-700 border-blue-200'
    ];
    
    $icons = [
        'success' => 'fa-check-circle',
        'error' => 'fa-exclamation-circle',
        'warning' => 'fa-exclamation-triangle',
        'info' => 'fa-info-circle'
    ];
    
    foreach ($types as $type) {
        $message = get_flash($type);
        if ($message) {
            $unique_id = 'flash_' . uniqid();
            echo '<div id="' . $unique_id . '" class="' . $colors[$type] . ' p-4 rounded-2xl mb-6 border-2 font-bold flex items-center gap-3 animate-in fade-in duration-300" style="position: relative;">';
            echo '<i class="fas ' . $icons[$type] . ' text-xl"></i>';
            echo '<span>' . htmlspecialchars($message) . '</span>';
            echo '<button onclick="document.getElementById(\'' . $unique_id . '\').remove()" class="mr-auto text-2xl hover:opacity-70 px-3" style="cursor: pointer;">&times;</button>';
            echo '</div>';
            // Auto-dismiss after 8 seconds
            echo '<script>setTimeout(function(){ var el = document.getElementById("' . $unique_id . '"); if(el) { el.style.opacity = "0"; el.style.transition = "opacity 0.5s"; setTimeout(function(){ if(el.parentNode) el.remove(); }, 500); } }, 8000);</script>';
        }
    }
}
?>
