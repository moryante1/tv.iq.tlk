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
#EXTINF:-1,Kass_1 
http://185.151.107.242:1935/kass/kass1/playlist.m3u8
#EXTINF:-1,Kass_2 
http://185.151.107.242:1935/kass/kass2/playlist.m3u8
#EXTINF:-1,Kass_3 
http://185.151.107.242:1935/kass/kass3/playlist.m3u8
#EXTINF:-1,Kass_4 
http://185.151.107.242:1935/kass/kass4/playlist.m3u8
#EXTINF:-1,Kuwait_Sports 
http://185.151.107.242:1935/bein2/kuwaitsport/playlist.m3u8
#EXTINF:-1,IRAQIA_SPORT 
http://185.151.107.242:1935/bein2/iqs/playlist.m3u8
#EXTINF:-1,SSC_1 
http://185.151.107.242:1935/bein1/ssc1/playlist.m3u8
#EXTINF:-1,SSC_2 
http://185.151.107.242:1935/bein1/ssc2/playlist.m3u8
#EXTINF:-1,Dijlah 
http://185.151.107.242:1935/live/dijla/playlist.m3u8
#EXTINF:-1,FOX_FAMILY_MOVIES 
http://185.151.107.242:1935/live2/26/playlist.m3u8
#EXTINF:-1,Fox_Action_Movies 
http://185.151.107.242:1935/live2/23/playlist.m3u8
#EXTINF:-1,MBC_1 
http://185.151.107.242:1935/nile/mbc1.stream_360p/playlist.m3u8
#EXTINF:-1,MBC2 
http://185.151.107.242:1935/nile2/mbc2.stream_360p/playlist.m3u8
#EXTINF:-1,MBC_3 
http://185.151.107.242:1935/nile3/mbc3.stream_360p/playlist.m3u8
#EXTINF:-1,MBC_4 
http://185.151.107.242:1935/nile/mbc4-1.stream_360p/playlist.m3u8
#EXTINF:-1,MBC_ACTION 
http://185.151.107.242:1935/nile2/mbcaction2.stream_360p/playlist.m3u8
#EXTINF:-1,MBC_MAX 
http://185.151.107.242:1935/live/10070/playlist.m3u8
#EXTINF:-1,MBC_IRAQ 
http://185.151.107.242:1935/live/mbci/playlist.m3u8
#EXTINF:-1,MBC_Bollywood 
http://185.151.107.242:1935/live/10151/playlist.m3u8
#EXTINF:-1,Mbc_Variety 
http://185.151.107.242:1935/live/AR-Al-Jazeera/playlist.m3u8
#EXTINF:-1,MBC_DRAMA 
http://185.151.107.242:1935/nile3/mbcmax3.stream_360p/playlist.m3u8
#EXTINF:-1,MBC_Drama 
http://185.151.107.242:1935/live/18/playlist.m3u8
#EXTINF:-1,INVESTIGATION_DISCOVERY 
http://185.151.107.242:1935/live2/DiscoveryID/playlist.m3u8
#EXTINF:-1,AL-RASHEED 
http://185.151.107.242:1935/nile/alrasheed1.stream_360p/playlist.m3u8
#EXTINF:-1,AL-SHARQIYA 
http://185.151.107.242:1935/nile2/alsharqiya/playlist.m3u8
#EXTINF:-1,AL-SHARQIYA_NEWS 
http://185.151.107.242:1935/nile2/al-sharqiya2.stream_360p/playlist.m3u8
#EXTINF:-1,Al-SUMARIA 
http://185.151.107.242:1935/nile3/al-sumaria3.stream_360p/playlist.m3u8
#EXTINF:-1,Rotana_Drama 
http://185.151.107.242:1935/nile/rotana1.stream_360p/playlist.m3u8
#EXTINF:-1,LBC 
http://185.151.107.242:1935/live/lbc/playlist.m3u8
#EXTINF:-1,History_2 
http://185.151.107.242:1935/live/10178/playlist.m3u8
#EXTINF:-1,NICKJR 
http://185.151.107.242:1935/live/10175/playlist.m3u8
#EXTINF:-1,Iraqia_News_HD 
http://185.151.107.242:1935/live/10124/playlist.m3u8
#EXTINF:-1,Cartoon_Network 
http://185.151.107.242:1935/OTV1/3096/playlist.m3u8
#EXTINF:-1,NAT_GEO_PEOPLE 
http://185.151.107.242:1935/live2/71/playlist.m3u8
#EXTINF:-1,History_1 
http://185.151.107.242:1935/live2/34/playlist.m3u8
#EXTINF:-1,Rotana_Khalijia 
http://185.151.107.242:1935/live/16/playlist.m3u8
#EXTINF:-1,TLC 
http://185.151.107.242:1935/live2/22/playlist.m3u8
#EXTINF:-1,Alforat_News 
http://185.151.107.242:1935/live/rotanaaflam/playlist.m3u8
#EXTINF:-1,KARBALA_TV_HD 
http://185.151.107.242:1935/live/Rotana_music/playlist.m3u8
#EXTINF:-1,RT_Arab 
http://185.151.107.242:1935/live/RT-Arab/playlist.m3u8
#EXTINF:-1,UTV 
http://185.151.107.242:1935/live/utv/playlist.m3u8
#EXTINF:-1,OSN_Movies_Hollywood 
http://185.151.107.242:1935/live/osn-movies-hollywood/playlist.m3u8
#EXTINF:-1,Show_Case 
http://185.151.107.242:1935/live/showcase/playlist.m3u8
#EXTINF:-1,OSN_Yahla_Cinem 
http://185.151.107.242:1935/live2/31/playlist.m3u8
#EXTINF:-1,OSN_KIDS 
http://185.151.107.242:1935/live/10097/playlist.m3u8
#EXTINF:-1,KARAMESH 
http://185.151.107.242:1935/live/3000/playlist.m3u8
#EXTINF:-1,OSN_KIDS 
http://185.151.107.242:1935/live/10097/playlist.m3u8
#EXTINF:-1,OSN_Yahla 
http://185.151.107.242:1935/live/10105/playlist.m3u8
#EXTINF:-1,OSN_MOVIES_HD 
http://185.151.107.242:1935/live3/51/playlist.m3u8
#EXTINF:-1,OSN_LIVING 
http://185.151.107.242:1935/live3/46/playlist.m3u8
#EXTINF:-1,OSN_Muslslat_1HD 
http://185.151.107.242:1935/live/10071/playlist.m3u8
#EXTINF:-1,OSN_Muslslat_2HD 
http://185.151.107.242:1935/live/10072/playlist.m3u8
#EXTINF:-1,Cartoon_Network 
http://185.151.107.242:1935/live/10115/playlist.m3u8
#EXTINF:-1,OSN_YAHALA_AL_OULA 
http://185.151.107.242:1935/live/9/playlist.m3u8
#EXTINF:-1,OSN_MOVIES_FIRST 
http://185.151.107.242:1935/live/6/playlist.m3u8
#EXTINF:-1,OSN_MOVIES_ACTION 
http://185.151.107.242:1935/live3/42/playlist.m3u8
#EXTINF:-1,OSN_Series 
http://185.151.107.242:1935/live/101041/playlist.m3u8
#EXTINF:-1,Rotana_Classic 
http://185.151.107.242:1935/live3/50/playlist.m3u8
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
