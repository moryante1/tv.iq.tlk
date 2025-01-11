// DOM Elements
const themeToggle = document.getElementById('theme-toggle');
const body = document.body;

// تحميل تفضيلات المستخدم المحفوظة
const savedTheme = localStorage.getItem('theme');
if (savedTheme) {
    body.classList.add(savedTheme);
    themeToggle.innerHTML = `<img src="service/img/${savedTheme === 'light-mode' ? 'moon' : 'sun'}.png" alt="Toggle Theme">`;
}

// تبديل الوضع الليلي/النهاري
themeToggle.addEventListener('click', () => {
    body.classList.toggle('light-mode');
    const isLightMode = body.classList.contains('light-mode');
    themeToggle.innerHTML = `<img src="service/img/${isLightMode ? 'moon' : 'sun'}.png" alt="Toggle Theme">`;
    localStorage.setItem('theme', isLightMode ? 'light-mode' : '');
});

// إظهار زر تبديل الوضع بعد تحميل الصفحة
window.addEventListener('load', () => {
    themeToggle.style.opacity = '1'; // جعل الزر مرئيًا بعد تحميل الصفحة
});

// تسجيل Service Worker لتطبيق PWA
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service/service-worker.js')
        .then(() => console.log('Service Worker Registered'))
        .catch(err => console.log('Service Worker Registration Failed: ', err));
}