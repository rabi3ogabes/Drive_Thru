{{-- Live operations alerts for the signed-in person: a soft chime and a toast; the bell refreshes itself. --}}
@auth
    @vite('resources/js/live.js')
    <div
        hidden
        data-live-channel="App.Models.User.{{ auth()->id() }}"
        x-data="{
            context: null,
            chime(critical) {
                try {
                    this.context ??= new (window.AudioContext || window.webkitAudioContext)();
                    const notes = critical ? [880, 660, 880] : [660, 880];
                    notes.forEach((frequency, index) => {
                        const start = this.context.currentTime + index * 0.18;
                        const tone = this.context.createOscillator();
                        const gain = this.context.createGain();
                        tone.type = 'sine';
                        tone.frequency.value = frequency;
                        gain.gain.setValueAtTime(0.0001, start);
                        gain.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.5);
                        tone.connect(gain).connect(this.context.destination);
                        tone.start(start);
                        tone.stop(start + 0.55);
                    });
                } catch (error) {}
            },
            show(alert) {
                this.chime(alert.critical === true);
                if (! window.FilamentNotification || ! alert.title) return;
                const toast = new window.FilamentNotification()
                    .title(alert.title)
                    .icon(alert.critical ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-bell-alert')
                    .iconColor(alert.critical ? 'danger' : 'warning');
                if (alert.url) {
                    toast.actions([new window.FilamentNotificationAction('open').label({{ \Illuminate\Support\Js::from(__('alerts.open')) }}).url(alert.url).button()]);
                }
                (alert.critical ? toast.persistent() : toast.duration(8000)).send();
            },
        }"
        x-on:live-alert="show($event.detail)"
    ></div>
@endauth
