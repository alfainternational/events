<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#14b8a6">
    <title>بوابة الفعاليات | كلية الشمال للتمريض الأهلية</title>
    
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">

    <style>
        :root { --primary: #14b8a6; --secondary: #0d9488; --accent: #facc15; --bg: #f0fdfa; }
        body { font-family: 'Cairo', sans-serif; background-color: var(--bg); color: #0f172a; font-size: 16px; line-height: 1.6; }
        
        .shimal-card { background: white; border-radius: 1.5rem; border: 1px solid rgba(20, 184, 166, 0.1); transition: all 0.3s ease; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; min-height: 48px; display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.5rem; border-radius: 1rem; font-weight: 900; box-shadow: 0 4px 12px rgba(20, 184, 166, 0.2); }
        
        /* حل مشكلة القائمة والطبقة العازلة في الجوال */
        #nav-links { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); z-index: 2100; }
        @media (max-width: 768px) {
            #nav-links { position: fixed; top: 0; right: -100%; width: 80%; height: 100vh; background: white; flex-direction: column; padding: 5rem 1.5rem; box-shadow: -10px 0 30px rgba(0,0,0,0.1); display: flex !important; }
            #nav-links.active { right: 0; }
            .mobile-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; backdrop-filter: blur(4px); }
            .mobile-overlay.active { display: block; }
        }
        
        input, select, textarea { font-size: 16px !important; min-height: 48px; border-radius: 0.75rem; border: 1px solid #e2e8f0; width: 100%; padding: 0.75rem; }
        label { font-size: 14px; font-weight: 900; color: #0f766e; margin-bottom: 0.5rem; display: block; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobileOverlay"></div>
    <nav class="bg-white border-b border-teal-50 sticky top-0 z-[2050] shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-500 rounded-xl flex items-center justify-center shadow-sm">
                    <i class="fas fa-heart-pulse text-white text-lg"></i>
                </div>
                <h1 class="text-base md:text-lg font-black text-teal-900 leading-tight">كلية الشمال للتمريض</h1>
            </div>

            <button class="md:hidden text-teal-600 text-2xl p-2 min-w-[48px]" id="menuBtn">
                <i class="fas fa-bars"></i>
            </button>

            <div id="nav-links" class="hidden md:flex items-center gap-6">
                <a href="index.php?page=home" class="font-bold text-teal-700 hover:text-teal-500 py-3 md:py-0 border-b md:border-0">الجدول الزمني</a>
                <a href="index.php?page=booking" class="font-bold text-teal-700 hover:text-teal-500 py-3 md:py-0 border-b md:border-0">حجز فعالية</a>
                <a href="edit_booking.php" class="font-bold text-teal-700 hover:text-teal-500 py-3 md:py-0 border-b md:border-0">تعديل طلب</a>
                <a href="calendar.php" class="font-bold text-teal-700 hover:text-teal-500 py-3 md:py-0 border-b md:border-0">التقويم</a>
                <a href="search.php" class="font-bold text-teal-700 hover:text-teal-500 py-3 md:py-0 border-b md:border-0">البحث</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="admin.php" class="btn-primary w-full md:w-auto">لوحة الإدارة</a>
                <?php else: ?>
                    <a href="login.php" class="btn-primary w-full md:w-auto">تسجيل الدخول</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <script>
        const menuBtn = document.getElementById('menuBtn');
        const navLinks = document.getElementById('nav-links');
        const overlay = document.getElementById('mobileOverlay');
        menuBtn.onclick = () => { navLinks.classList.toggle('active'); overlay.classList.toggle('active'); };
        overlay.onclick = () => { navLinks.classList.remove('active'); overlay.classList.remove('active'); };
    </script>
    <main class="max-w-7xl mx-auto px-4 py-8">