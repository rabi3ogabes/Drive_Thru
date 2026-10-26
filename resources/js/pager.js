/*
 * The guest's pager page. Keeps the screen awake while they wait, and when
 * the counter calls the number it rings (a soft two-tone bell made with Web
 * Audio, no sound file), vibrates and flashes the tab title, until the guest
 * taps "I'm on my way". Browsers only allow sound after a tap, so the first
 * tap anywhere turns sound on.
 */
import './live';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('pagerRinger', ({ readyTitle }) => ({
        sound: false,
        ringing: false,
        audio: null,
        timer: null,
        lock: null,
        baseTitle: document.title,
        lastCalls: 0,

        init() {
            this.lastCalls = this.$wire.calls;
            this.keepAwake();
            document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.keepAwake());

            // Any first tap turns sound on (browsers forbid sound before one).
            const unlock = () => this.enableSound();
            document.addEventListener('pointerdown', unlock, { once: true });

            this.$watch('$wire.calls', (calls) => {
                if (calls > this.lastCalls && this.$wire.status === 'ready') this.ring();
                this.lastCalls = calls;
            });
            this.$watch('$wire.status', (status) => {
                if (status !== 'ready') this.stop();
                if (status === 'collected' || status === 'expired') this.release();
            });
        },

        enableSound() {
            if (this.audio) return;
            const Context = window.AudioContext || window.webkitAudioContext;
            if (!Context) return;
            this.audio = new Context();
            this.audio.resume?.();
            this.sound = true;
            // A silent blip so iOS keeps the audio session unlocked.
            this.tone(440, 0, 0.01, 0.0001);
        },

        tone(freq, start, length, peak = 0.22) {
            const ctx = this.audio;
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, ctx.currentTime + start);
            gain.gain.exponentialRampToValueAtTime(peak, ctx.currentTime + start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + start + length);
            osc.connect(gain).connect(ctx.destination);
            osc.start(ctx.currentTime + start);
            osc.stop(ctx.currentTime + start + length + 0.05);
        },

        chime() {
            // A two-note bell, then its echo: noticeable, never harsh.
            [[880, 0], [1318.5, 0.18], [880, 0.72], [1318.5, 0.9]].forEach(([f, t]) => {
                this.tone(f, t, 0.9);
                this.tone(f * 2, t, 0.35, 0.05);
            });
        },

        ring() {
            this.stop();
            this.ringing = true;
            let rounds = 0;
            const once = () => {
                if (!this.ringing || rounds++ >= 20) return this.stop();
                this.chime();
                navigator.vibrate?.([450, 180, 450, 180, 900]);
                document.title = rounds % 2 ? readyTitle : this.baseTitle;
            };
            once();
            this.timer = setInterval(once, 3000);
        },

        stop() {
            this.ringing = false;
            clearInterval(this.timer);
            this.timer = null;
            navigator.vibrate?.(0);
            document.title = this.baseTitle;
        },

        async keepAwake() {
            try {
                if ('wakeLock' in navigator && !this.lock && document.visibilityState === 'visible') {
                    this.lock = await navigator.wakeLock.request('screen');
                    this.lock.addEventListener('release', () => (this.lock = null));
                }
            } catch (e) {
                this.lock = null;
            }
        },

        release() {
            this.lock?.release?.();
            this.lock = null;
        },
    }));
});
