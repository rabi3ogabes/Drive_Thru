/*
 * The home page hero: one slide per service, each a headline beside a short
 * scene told in motion. A slide plays once through (its tab's progress line fills
 * as it goes), then the next one starts. Nothing moves while the section is
 * off screen, the tab is hidden, the pointer rests on it, it holds keyboard
 * focus, or the visitor pressed pause. With reduced motion nothing plays by
 * itself and every scene shows its finished state.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('serviceShowcase', (count) => ({
        count,
        active: 0,
        stopped: false, // the visitor pressed pause
        hovered: false,
        focused: false,
        visible: false,
        hidden: document.hidden,
        still: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        started: false,
        moved: false, // a slide other than the first has been shown (entrance animations play from then on)
        touch: null,

        init() {
            const io = new IntersectionObserver(([entry]) => {
                this.visible = entry.isIntersecting;
                if (this.visible && !this.started && !this.still) {
                    this.started = true;
                    this.restart();
                }
            }, { threshold: 0.25 });
            // The scenes, not the whole (tall) section: on a phone the section never fits on screen.
            io.observe(this.$refs.stage);

            document.addEventListener('visibilitychange', () => { this.hidden = document.hidden; });
            window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', (e) => { this.still = e.matches; });
        },

        get paused() {
            return this.still || this.stopped || this.hovered || this.focused || !this.visible || this.hidden || !this.started;
        },

        get rtl() {
            return document.documentElement.dir === 'rtl';
        },

        show(index, { user = false } = {}) {
            this.active = (index + this.count) % this.count;
            this.moved = true;
            if (user && !this.still) this.started = true;
            this.$nextTick(() => this.restart());
        },

        /** A slide's "start free" button: pick that service on the request form, then go there. */
        pick(service) {
            window.dispatchEvent(new CustomEvent('pick-service', { detail: service }));
            document.getElementById('request')?.scrollIntoView({ behavior: this.still ? 'auto' : 'smooth', block: 'start' });
            history.replaceState(null, '', '#request');
        },

        next() { this.show(this.active + 1); },
        previous() { this.show(this.active - 1); },

        toggle() {
            this.stopped = !this.stopped;
            if (!this.stopped && !this.started) {
                this.started = true;
                this.restart();
            }
        },

        /** Plays the active scene (and its progress line) from the beginning. */
        restart() {
            if (this.still) return;
            const scene = this.$refs[`scene${this.active}`];
            const bar = this.$refs[`bar${this.active}`];
            [scene, bar].forEach((el) => {
                if (!el) return;
                el.classList.remove('is-playing');
                void el.offsetWidth; // reflow, so the animations start over
                el.classList.add('is-playing');
            });
        },

        /** The progress line of the active tab finished: move on. */
        ended(event, index) {
            if (event.animationName === 'sc-progress' && index === this.active) this.next();
        },

        /** Arrow keys follow reading direction; Home/End jump to the ends. */
        key(event) {
            const forward = this.rtl ? 'ArrowLeft' : 'ArrowRight';
            const back = this.rtl ? 'ArrowRight' : 'ArrowLeft';
            const moves = { [forward]: this.active + 1, [back]: this.active - 1, Home: 0, End: this.count - 1 };
            if (!(event.key in moves)) return;
            event.preventDefault();
            this.show(moves[event.key], { user: true });
            this.$nextTick(() => this.$refs[`tab${this.active}`]?.focus());
        },

        swipeStart(event) {
            this.touch = { x: event.clientX, y: event.clientY };
        },

        swipeEnd(event) {
            if (!this.touch) return;
            const dx = event.clientX - this.touch.x;
            const dy = event.clientY - this.touch.y;
            this.touch = null;
            if (Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.4) return;
            const towardsNext = this.rtl ? dx > 0 : dx < 0;
            this.show(this.active + (towardsNext ? 1 : -1), { user: true });
        },
    }));
});
