<div id="travel-loading" class="fixed inset-0 z-[60] hidden" style="backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);">
    <div class="absolute inset-0 bg-white/90"></div>
    <div class="relative flex flex-col items-center justify-center min-h-screen px-6 text-center">

        <div class="travel-plane-wrap mb-6">
            <svg class="w-20 h-20 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21 16v-2l-8-5V3.5A1.5 1.5 0 0011.5 2 1.5 1.5 0 0010 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
            </svg>
        </div>

        <div class="flex items-center gap-4 mb-6">
            <div class="travel-icon-pulse" style="animation-delay:0s">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div class="travel-icon-pulse" style="animation-delay:0.6s">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="travel-icon-pulse" style="animation-delay:1.2s">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
        </div>

        <h3 id="travel-loading-title" class="text-xl font-bold text-gray-800 mb-2">Carregando...</h3>
        <p id="travel-loading-message" class="text-sm text-gray-500 h-5 transition-opacity duration-500"></p>

        <div class="w-48 h-1.5 bg-gray-200 rounded-full overflow-hidden mt-6">
            <div class="travel-progress-bar h-full rounded-full"></div>
        </div>

        <div id="travel-loading-timeout" class="hidden mt-8">
            <p class="text-sm text-gray-500 mb-3">Está demorando mais que o esperado...</p>
            <button type="button" onclick="location.reload()" class="text-sm font-medium text-blue-600 hover:text-blue-700 underline">
                Tentar novamente
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes travelFly {
        0%, 100% { transform: translateY(0) rotate(-2deg); }
        25% { transform: translateY(-12px) rotate(2deg); }
        50% { transform: translateY(-6px) rotate(-1deg); }
        75% { transform: translateY(-14px) rotate(3deg); }
    }
    .travel-plane-wrap svg {
        animation: travelFly 3s ease-in-out infinite;
    }
    @keyframes travelPulse {
        0%, 100% { opacity: 0.3; transform: scale(0.9); }
        50% { opacity: 1; transform: scale(1.1); }
    }
    .travel-icon-pulse {
        animation: travelPulse 2s ease-in-out infinite;
    }
    @keyframes travelProgress {
        0% { transform: translateX(-100%); }
        50% { transform: translateX(0); }
        100% { transform: translateX(100%); }
    }
    .travel-progress-bar {
        width: 60%;
        background: linear-gradient(90deg, #059669, #34d399, #059669);
        animation: travelProgress 2s ease-in-out infinite;
    }
</style>

<script>
(function() {
    var overlay = document.getElementById('travel-loading');
    var titleEl = document.getElementById('travel-loading-title');
    var msgEl = document.getElementById('travel-loading-message');
    var timeoutEl = document.getElementById('travel-loading-timeout');
    var msgInterval = null;
    var timeoutTimer = null;

    window.showTravelLoading = function(config) {
        config = config || {};
        var title = config.title || 'Carregando...';
        var messages = config.messages || [];
        var timeoutMs = config.timeoutMs || 60000;

        titleEl.textContent = title;
        msgEl.textContent = messages[0] || '';
        msgEl.style.opacity = '1';
        timeoutEl.classList.add('hidden');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        if (msgInterval) clearInterval(msgInterval);
        if (messages.length > 1) {
            var idx = 0;
            msgInterval = setInterval(function() {
                msgEl.style.opacity = '0';
                setTimeout(function() {
                    idx = (idx + 1) % messages.length;
                    msgEl.textContent = messages[idx];
                    msgEl.style.opacity = '1';
                }, 400);
            }, 3500);
        }

        if (timeoutTimer) clearTimeout(timeoutTimer);
        timeoutTimer = setTimeout(function() {
            timeoutEl.classList.remove('hidden');
        }, timeoutMs);
    };

    window.hideTravelLoading = function() {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
        if (msgInterval) { clearInterval(msgInterval); msgInterval = null; }
        if (timeoutTimer) { clearTimeout(timeoutTimer); timeoutTimer = null; }
    };
})();
</script>
