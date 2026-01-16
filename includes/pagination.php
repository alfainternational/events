<?php
/**
 * Pagination Helper Functions
 * دوال مساعدة للتصفح والتقسيم
 */

/**
 * حساب معلومات Pagination
 *
 * @param int $total_items إجمالي عدد السجلات
 * @param int $current_page الصفحة الحالية
 * @param int $per_page عدد السجلات في كل صفحة
 * @return array معلومات Pagination
 */
function calculate_pagination($total_items, $current_page = 1, $per_page = null) {
    // استخدام القيمة الافتراضية من .env
    if ($per_page === null) {
        $per_page = (int) env('PAGINATION_DEFAULT', 20);
    }

    // التحقق من الحد الأقصى
    $max_per_page = (int) env('PAGINATION_MAX', 100);
    if ($per_page > $max_per_page) {
        $per_page = $max_per_page;
    }

    // التأكد من أن الصفحة الحالية صالحة
    $current_page = max(1, (int) $current_page);

    // حساب إجمالي الصفحات
    $total_pages = max(1, ceil($total_items / $per_page));

    // التأكد من أن الصفحة الحالية ضمن النطاق
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }

    // حساب offset
    $offset = ($current_page - 1) * $per_page;

    // حساب from و to
    $from = $total_items > 0 ? $offset + 1 : 0;
    $to = min($offset + $per_page, $total_items);

    // حساب الصفحة السابقة والتالية
    $prev_page = $current_page > 1 ? $current_page - 1 : null;
    $next_page = $current_page < $total_pages ? $current_page + 1 : null;

    return [
        'total_items' => $total_items,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'from' => $from,
        'to' => $to,
        'prev_page' => $prev_page,
        'next_page' => $next_page,
        'has_prev' => $prev_page !== null,
        'has_next' => $next_page !== null
    ];
}

/**
 * الحصول على أرقام الصفحات للعرض
 *
 * @param int $current_page الصفحة الحالية
 * @param int $total_pages إجمالي الصفحات
 * @param int $range عدد الصفحات المجاورة لعرضها
 * @return array أرقام الصفحات للعرض
 */
function get_page_numbers($current_page, $total_pages, $range = 2) {
    $pages = [];

    // دائماً أظهر الصفحة الأولى
    $pages[] = 1;

    // حساب النطاق حول الصفحة الحالية
    $start = max(2, $current_page - $range);
    $end = min($total_pages - 1, $current_page + $range);

    // إضافة "..." بعد الصفحة الأولى إذا لزم الأمر
    if ($start > 2) {
        $pages[] = '...';
    }

    // إضافة الصفحات في النطاق
    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }

    // إضافة "..." قبل الصفحة الأخيرة إذا لزم الأمر
    if ($end < $total_pages - 1) {
        $pages[] = '...';
    }

    // دائماً أظهر الصفحة الأخيرة
    if ($total_pages > 1) {
        $pages[] = $total_pages;
    }

    return $pages;
}

/**
 * عرض عناصر التحكم في Pagination
 *
 * @param array $pagination معلومات Pagination من calculate_pagination()
 * @param string $base_url الرابط الأساسي (سيتم إضافة ?page=N)
 * @param array $query_params معاملات إضافية للرابط
 */
function render_pagination($pagination, $base_url, $query_params = []) {
    if ($pagination['total_pages'] <= 1) {
        return; // لا داعي لعرض pagination إذا كانت صفحة واحدة فقط
    }

    // بناء query string للمعاملات الإضافية
    $query_string = '';
    if (!empty($query_params)) {
        $query_string = '&' . http_build_query($query_params);
    }

    $pages = get_page_numbers($pagination['current_page'], $pagination['total_pages']);
    ?>

    <div class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
        <!-- معلومات العرض -->
        <div class="text-sm text-teal-600 font-bold">
            عرض <?= $pagination['from'] ?> - <?= $pagination['to'] ?> من <?= $pagination['total_items'] ?> نتيجة
        </div>

        <!-- أزرار التنقل -->
        <div class="flex items-center gap-2">
            <!-- السابق -->
            <?php if ($pagination['has_prev']): ?>
                <a href="<?= $base_url ?>?page_num=<?= $pagination['prev_page'] ?><?= $query_string ?>"
                   class="px-4 py-2 bg-white border-2 border-teal-100 rounded-xl text-teal-600 font-bold hover:bg-teal-50 transition">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="px-4 py-2 bg-gray-100 border-2 border-gray-200 rounded-xl text-gray-400 font-bold cursor-not-allowed">
                    <i class="fas fa-chevron-right"></i>
                </span>
            <?php endif; ?>

            <!-- أرقام الصفحات -->
            <?php foreach ($pages as $page_num): ?>
                <?php if ($page_num === '...'): ?>
                    <span class="px-3 py-2 text-teal-400">...</span>
                <?php elseif ($page_num == $pagination['current_page']): ?>
                    <span class="px-4 py-2 bg-teal-500 text-white rounded-xl font-black shadow-md">
                        <?= $page_num ?>
                    </span>
                <?php else: ?>
                    <a href="<?= $base_url ?>?page_num=<?= $page_num ?><?= $query_string ?>"
                       class="px-4 py-2 bg-white border-2 border-teal-100 rounded-xl text-teal-600 font-bold hover:bg-teal-50 transition">
                        <?= $page_num ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- التالي -->
            <?php if ($pagination['has_next']): ?>
                <a href="<?= $base_url ?>?page_num=<?= $pagination['next_page'] ?><?= $query_string ?>"
                   class="px-4 py-2 bg-white border-2 border-teal-100 rounded-xl text-teal-600 font-bold hover:bg-teal-50 transition">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="px-4 py-2 bg-gray-100 border-2 border-gray-200 rounded-xl text-gray-400 font-bold cursor-not-allowed">
                    <i class="fas fa-chevron-left"></i>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php
}

/**
 * عرض Pagination مبسط (للجوال)
 */
function render_simple_pagination($pagination, $base_url, $query_params = []) {
    if ($pagination['total_pages'] <= 1) {
        return;
    }

    $query_string = '';
    if (!empty($query_params)) {
        $query_string = '&' . http_build_query($query_params);
    }
    ?>

    <div class="mt-6 flex flex-col gap-3">
        <!-- معلومات -->
        <div class="text-center text-sm text-teal-600 font-bold">
            صفحة <?= $pagination['current_page'] ?> من <?= $pagination['total_pages'] ?>
        </div>

        <!-- أزرار -->
        <div class="grid grid-cols-2 gap-3">
            <?php if ($pagination['has_prev']): ?>
                <a href="<?= $base_url ?>?page_num=<?= $pagination['prev_page'] ?><?= $query_string ?>"
                   class="btn-primary text-center">
                    <i class="fas fa-chevron-right ml-2"></i> السابق
                </a>
            <?php else: ?>
                <button disabled class="bg-gray-200 text-gray-400 px-6 py-3 rounded-xl font-bold cursor-not-allowed">
                    <i class="fas fa-chevron-right ml-2"></i> السابق
                </button>
            <?php endif; ?>

            <?php if ($pagination['has_next']): ?>
                <a href="<?= $base_url ?>?page_num=<?= $pagination['next_page'] ?><?= $query_string ?>"
                   class="btn-primary text-center">
                    التالي <i class="fas fa-chevron-left mr-2"></i>
                </a>
            <?php else: ?>
                <button disabled class="bg-gray-200 text-gray-400 px-6 py-3 rounded-xl font-bold cursor-not-allowed">
                    التالي <i class="fas fa-chevron-left mr-2"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php
}
?>
