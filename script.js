// التحقق من اتصال الخادم
function checkServerConnection() {
    const connectionStatus = document.getElementById('connectionStatus');
    const buttons = document.querySelectorAll('.button-grid .button');

    // محاكاة اتصال الخادم (يمكن استبدالها بطلب حقيقي إلى الخادم)
    const isConnected = Math.random() > 0.5; // تغيير هذا بناءً على اتصال الخادم

    if (isConnected) {
        connectionStatus.textContent = "انت متصل من قبل شركة IP TV 4U";
        connectionStatus.style.display = 'block';
        connectionStatus.classList.remove('offline');
        buttons.forEach(button => button.classList.remove('hidden'));
        setTimeout(() => {
            connectionStatus.style.display = 'none';
        }, 3000); // إخفاء الرسالة بعد 3 ثوانٍ
    } else {
        connectionStatus.textContent = "شركة لا تدعم IP TV 4U";
        connectionStatus.style.display = 'block';
        connectionStatus.classList.add('offline');
        buttons.forEach(button => button.classList.add('hidden'));
    }
}

// عرض/إخفاء معلومات الرخصة
document.getElementById('settingsBtn').addEventListener('click', () => {
    const licenseInfo = document.getElementById('licenseInfo');
    licenseInfo.style.display = licenseInfo.style.display === 'block' ? 'none' : 'block';
});

// التحقق من الاتصال كل 5 ثوانٍ
setInterval(checkServerConnection, 5000);
checkServerConnection(); // التحقق عند التحميل الأولي

// Dark/Light Mode Toggle
const themeToggle = document.getElementById('theme-toggle');
const body = document.body;

themeToggle.addEventListener('click', () => {
    body.classList.toggle('light-mode');
    const isLightMode = body.classList.contains('light-mode');
    themeToggle.innerHTML = `<img src="service/img/${isLightMode ? 'moon' : 'sun'}.png" alt="Toggle Theme">`;
    localStorage.setItem('theme', isLightMode ? 'light-mode' : '');
});

// Load saved theme preference
const savedTheme = localStorage.getItem('theme');
if (savedTheme) {
    body.classList.add(savedTheme);
    themeToggle.innerHTML = `<img src="service/img/${savedTheme === 'light-mode' ? 'moon' : 'sun'}.png" alt="Toggle Theme">`;
}

// إخفاء شاشة التحميل بعد 4 ثوانٍ
setTimeout(() => {
    document.getElementById('loadingScreen').classList.add('hide');
}, 4000); // 4 ثواني كمثال

// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js')
        .then(() => console.log('Service Worker Registered'))
        .catch(err => console.log('Service Worker Registration Failed: ', err));
}