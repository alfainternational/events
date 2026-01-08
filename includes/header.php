<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=yes">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#14b8a6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="الفعاليات">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    
    <title>بوابة الفعاليات | كلية الشمال للتمريض الأهلية</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // إخفاء تحذير Tailwind production
        tailwind.config = {
            corePlugins: {
                preflight: false,
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/mobile-responsive.css" rel="stylesheet">
    <link href="assets/css/mobile-advanced.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap');
        
        :root {
            --primary: #14b8a6;
            --secondary: #0d9488;
            --accent: #facc15;
            --bg: #f0fdfa;
        }

        body { 
            font-family: 'Cairo', sans-serif; 
            background-color: var(--bg);
            color: #0f172a;
        }

        .shimal-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 2rem;
            border: 1px solid rgba(20, 184, 166, 0.1);
            transition: all 0.3s ease;
        }

        .shimal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -15px rgba(20, 184, 166, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            filter: brightness(1.1);
            transform: scale(1.02);
        }

        .quantity-input {
            width: 80px;
            padding: 4px;
            border-radius: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="bg-white border-b border-teal-100">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-500 rounded-lg flex items-center justify-center">
                    <i class="fas fa-heart-pulse text-white"></i>
                </div>
                <h1 class="text-lg font-bold text-teal-900">كلية الشمال للتمريض</h1>
            </div>

            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="القائمة" type="button">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navigation Links -->
            <div id="nav-links" class="hidden md:flex items-center gap-6">
                <a href="index.php?page=home" class="text-sm text-teal-700 hover:text-teal-500">الجدول الزمني</a>
                <a href="index.php?page=booking" class="text-sm text-teal-700 hover:text-teal-500">حجز فعالية</a>
                <a href="edit_booking.php" class="text-sm text-teal-700 hover:text-teal-500">تعديل طلب</a>
                <a href="calendar.php" class="text-sm text-teal-700 hover:text-teal-500">التقويم</a>
                <a href="search.php" class="text-sm text-teal-700 hover:text-teal-500">البحث</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="admin.php" class="btn-primary px-4 py-2 rounded-lg text-sm">لوحة الإدارة</a>
                <?php else: ?>
                    <a href="login.php" class="btn-primary px-4 py-2 rounded-lg text-sm">تسجيل الدخول</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <script>
        // Mobile Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navLinks = document.getElementById('nav-links');
            
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    navLinks.classList.toggle('show');
                    this.classList.toggle('active');
                    
                    // تغيير الأيقونة
                    const icon = this.querySelector('i');
                    if (navLinks.classList.contains('show')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
                
                // إغلاق القائمة عند النقر على رابط
                const links = navLinks.querySelectorAll('a');
                links.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 768) {
                            navLinks.classList.remove('show');
                            mobileMenuToggle.classList.remove('active');
                            const icon = mobileMenuToggle.querySelector('i');
                            icon.classList.remove('fa-times');
                            icon.classList.add('fa-bars');
                        }
                    });
                });
                
                // إغلاق القائمة عند تغيير حجم الشاشة
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 768) {
                        navLinks.classList.remove('show');
                        mobileMenuToggle.classList.remove('active');
                        const icon = mobileMenuToggle.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
            }
        });
    </script>
    <main class="max-w-7xl mx-auto px-6 py-12">
