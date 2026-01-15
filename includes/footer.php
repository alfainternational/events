    </main>
    <footer class="bg-white border-t border-teal-50 py-8 mt-20 text-center">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-teal-700 text-sm">إدارة العلاقات العامة - شركة الشمال التعليمية للتعليم</p>
        </div>
    </footer>

    <!-- Auto-save & Dark Mode Scripts -->
    <link rel="stylesheet" href="assets/css/darkmode.css">
    <script src="assets/js/autosave.js"></script>
    <script src="assets/js/darkmode.js"></script>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/activity/service-worker.js')
                    .then(reg => console.log('[PWA] Service Worker registered:', reg))
                    .catch(err => console.log('[PWA] Service Worker registration failed:', err));
            });
        }
    </script>
</body>
</html>
