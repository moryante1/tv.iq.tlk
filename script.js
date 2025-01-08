// عناصر DOM
const video = document.getElementById('m3u-video');
const playPauseBtn = document.querySelector('.play-pause-btn');
const muteBtn = document.querySelector('.mute-btn');
const volumeSlider = document.querySelector('.volume-slider');
const progressBar = document.querySelector('.progress');
const timeDisplay = document.querySelector('.time');
const fullscreenBtn = document.querySelector('.fullscreen-btn');
const restartBtn = document.querySelector('.restart-btn');
const channelList = document.getElementById('channel-list');

// قائمة القنوات (يمكن استبدالها بمصدر بيانات حقيقي)
const channels = [

];

// تحميل القنوات في القائمة الجانبية
function loadChannels() {
    channels.forEach(channel => {
        const li = document.createElement('li');
        li.textContent = channel.name;
        li.addEventListener('click', () => playChannel(channel.url));
        channelList.appendChild(li);
    });
}

// تشغيل قناة محددة
function playChannel(url) {
    if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(url);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => video.play());
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = url;
        video.play();
    }
}

// التحكم في التشغيل والإيقاف
playPauseBtn.addEventListener('click', () => {
    if (video.paused) {
        video.play();
        playPauseBtn.textContent = "⏸️";
    } else {
        video.pause();
        playPauseBtn.textContent = "▶️";
    }
});

// التحكم في الصوت
muteBtn.addEventListener('click', () => {
    video.muted = !video.muted;
    muteBtn.textContent = video.muted ? "🔇" : "🔊";
});

volumeSlider.addEventListener('input', () => {
    video.volume = volumeSlider.value;
});

// تحديث شريط التقدم
video.addEventListener('timeupdate', () => {
    const progress = (video.currentTime / video.duration) * 100;
    progressBar.style.width = `${progress}%`;
    timeDisplay.textContent = `${formatTime(video.currentTime)} / ${formatTime(video.duration)}`;
});

// إعادة التشغيل
restartBtn.addEventListener('click', () => {
    video.currentTime = 0;
});

// ملء الشاشة
fullscreenBtn.addEventListener('click', () => {
    if (!document.fullscreenElement) {
        video.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
});

// تنسيق الوقت
function formatTime(time) {
    const minutes = Math.floor(time / 60);
    const seconds = Math.floor(time % 60);
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

// تحميل القنوات عند بدء التشغيل
loadChannels();

// قائمة قنوات M3U
const m3uContent = `#EXTM3U
#EXTINF:-1,العربية الاخبارية 
https://shls-live-enc.edgenextcdn.net/out/v1/f5f319206ed740f9a831f2097c2ead23/index_37.m3u8 
#EXTINF:-1,ام بي سي4
https://mbc4-prod-dub-enc.edgenextcdn.net/out/v1/c08681f81775496ab4afa2bac7ae7638/index_2.m3u8

#EXTINF:-1,ام بي سي العراق
https://iraq-prod-dub-enc.edgenextcdn.net/out/v1/c9bf1e87ea66478bb20bc5c93c9d41ea/index_3_22747127.ts?m=1717320314http://162.19.247.39:1935/kass/kass4/playlist.m3u8
#EXTINF:-1,عرب كوت تالنت
https://shls-live-enc.edgenextcdn.net/out/v1/07a6ab2d57b2453a91bbdd2d46b5865a/index_2.m3u8
`;

// تحميل القنوات من m3uContent
function loadM3UChannels() {
    const lines = m3uContent.split('\n');
    for (let i = 0; i < lines.length; i++) {
        if (lines[i].startsWith('#EXTINF')) {
            const channelName = lines[i].split(',')[1];
            const channelUrl = lines[i + 1];
            const li = document.createElement('li');
            li.textContent = channelName;
            li.addEventListener('click', () => playChannel(channelUrl));
            channelList.appendChild(li);
        }
    }
}

// تحميل القنوات عند بدء التشغيل
loadM3UChannels();



