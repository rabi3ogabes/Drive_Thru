<script>
    document.addEventListener('alpine:init', () => {
        // One clock for every timer on the page (no interval per card).
        window.Alpine.store('clock', { now: Date.now() });
        setInterval(() => (window.Alpine.store('clock').now = Date.now()), 1000);

        window.Alpine.data('opsShell', (config) => ({
            time: '',
            live: 'connecting',
            sound: false,
            awake: false,
            toasts: [],
            failures: 0,

            init() {
                try { this.sound = localStorage.getItem('dtp.ops.sound') === 'on' } catch (e) {}

                const clock = new Intl.DateTimeFormat(config.locale, { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: config.timezone });
                const tick = () => (this.time = clock.format(new Date()));
                tick();
                setInterval(tick, 1000);

                this.keepAwake();
                document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.keepAwake());

                if (config.heartbeatUrl) {
                    this.beat();
                    setInterval(() => this.beat(), 30000);
                    // Pick up new app versions overnight without anyone touching the screen.
                    setTimeout(() => location.reload(), 6 * 60 * 60 * 1000);
                }

                window.addEventListener('ops-toast', (e) => this.toast(e.detail.message, e.detail.tone));
                window.addEventListener('ops-new-orders', () => this.alert());
                window.addEventListener('ops-print', (e) => window.open(e.detail.url, '_blank', 'width=420,height=720,noopener'));
            },

            toast(message, tone = 'success') {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, message, tone });
                setTimeout(() => (this.toasts = this.toasts.filter((t) => t.id !== id)), tone === 'error' ? 6000 : 3200);
            },

            enableSound() {
                this.sound = ! this.sound;
                try { localStorage.setItem('dtp.ops.sound', this.sound ? 'on' : 'off') } catch (e) {}
                if (this.sound) this.chime();
            },

            /** A soft bell arpeggio, generated in the browser (no files, no network). */
            chime() {
                try {
                    const audio = this.audio ??= new (window.AudioContext || window.webkitAudioContext)();
                    audio.resume?.();
                    [783.99, 987.77, 1174.66, 1567.98].forEach((frequency, i) => {
                        const start = audio.currentTime + i * 0.12;
                        const osc = audio.createOscillator();
                        const gain = audio.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = frequency;
                        gain.gain.setValueAtTime(0.0001, start);
                        gain.gain.exponentialRampToValueAtTime(0.28, start + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, start + 1.1);
                        osc.connect(gain).connect(audio.destination);
                        osc.start(start);
                        osc.stop(start + 1.2);
                    });
                } catch (e) {}
            },

            alert() {
                if (this.sound) this.chime();
                navigator.vibrate?.([120, 80, 120]);
            },

            async keepAwake() {
                try {
                    if ('wakeLock' in navigator) {
                        const lock = await navigator.wakeLock.request('screen');
                        this.awake = true;
                        lock.addEventListener('release', () => (this.awake = false));
                    }
                } catch (e) {
                    this.awake = false;
                }
            },

            async beat() {
                try {
                    const response = await fetch(config.heartbeatUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                    if (response.status === 401) {
                        location.href = config.pairUrl;
                        return;
                    }
                    this.failures = 0;
                } catch (e) {
                    this.failures++;
                    setTimeout(() => this.beat(), Math.min(30000, 5000 * 2 ** (this.failures - 1)));
                }
            },

            fullscreen() {
                document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen?.();
            },
        }));
    });
</script>
