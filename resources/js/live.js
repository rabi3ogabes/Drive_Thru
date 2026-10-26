/*
 * Live updates for ops screens, the panel and the guest tracking page.
 *
 * Any element with data-live-channel="branch.3.orders" (and optionally
 * data-live-auth="/realtime/auth") is subscribed automatically, also when it
 * appears later (Livewire navigation). The element receives:
 *   - "live-update" with the broadcast payload ({ id, status, what, … });
 *     after a reconnect it gets { what: "resync" } so the page catches up;
 *   - "live-status" with { state: "connecting" | "live" | "offline" }.
 * On a person's own channel (App.Models.User.N) it also receives
 * "live-alert" with an operations alert ({ kind, critical, title, url }).
 * Pages refresh from the server on an update, so a missed message never
 * leaves a screen out of date.
 */
import './push';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const env = import.meta.env;
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const subscriptions = new Map(); // channel name → { channel, elements: Set }
let echo = null;
let state = 'connecting';
let wasConnected = false;

function authorizer(endpoint) {
    return (channel) => ({
        authorize: (socketId, callback) => {
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
            })
                .then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
                .then((data) => callback(null, data))
                .catch((error) => callback(error, null));
        },
    });
}

function emit(element, name, detail) {
    element.dispatchEvent(new CustomEvent(name, { detail, bubbles: false }));
}

function everyElement(fn) {
    subscriptions.forEach(({ elements }) => elements.forEach(fn));
}

function setState(next) {
    if (next === state) return;
    const reconnected = next === 'live' && wasConnected && state !== 'live';
    state = next;
    if (next === 'live') wasConnected = true;
    everyElement((element) => {
        emit(element, 'live-status', { state });
        if (reconnected) emit(element, 'live-update', { what: 'resync' });
    });
}

function connect(endpoint) {
    if (echo) return echo;

    echo = new Echo({
        broadcaster: 'reverb',
        key: env.VITE_REVERB_APP_KEY,
        wsHost: env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(env.VITE_REVERB_PORT || 80),
        wssPort: Number(env.VITE_REVERB_PORT || 443),
        forceTLS: (env.VITE_REVERB_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: authorizer(endpoint),
    });

    // Filament's notification bell subscribes through window.Echo once it exists.
    window.Echo = echo;
    window.dispatchEvent(new CustomEvent('EchoLoaded'));

    const connection = echo.connector.pusher.connection;
    connection.bind('state_change', ({ current }) => {
        if (current === 'connected') setState('live');
        else if (current === 'connecting' || current === 'initialized') setState(wasConnected ? 'offline' : 'connecting');
        else setState('offline');
    });

    return echo;
}

function subscribe(element) {
    const name = element.dataset.liveChannel;
    if (!name || element.__live) return;
    element.__live = true;

    connect(element.dataset.liveAuth || '/broadcasting/auth');

    let entry = subscriptions.get(name);
    if (!entry) {
        const channel = echo.private(name);
        entry = { channel, elements: new Set() };
        channel.listen('.order.updated', (payload) => entry.elements.forEach((el) => emit(el, 'live-update', payload)));
        channel.listen('.pager.updated', (payload) => entry.elements.forEach((el) => emit(el, 'live-update', payload)));
        channel.listen('.database-notifications.sent', (payload) => entry.elements.forEach((el) => emit(el, 'live-alert', payload)));
        channel.error(() => entry.elements.forEach((el) => emit(el, 'live-status', { state: 'offline' })));
        subscriptions.set(name, entry);
    }

    entry.elements.add(element);
    emit(element, 'live-status', { state });
}

function unsubscribe(element) {
    const name = element.dataset?.liveChannel;
    const entry = name && subscriptions.get(name);
    if (!entry) return;

    entry.elements.delete(element);
    element.__live = false;

    if (entry.elements.size === 0) {
        echo?.leave(name);
        subscriptions.delete(name);
    }
}

function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches('[data-live-channel]')) subscribe(root);
    root.querySelectorAll('[data-live-channel]').forEach(subscribe);
}

function sweep(root) {
    if (root.nodeType !== 1) return;
    if (root.matches('[data-live-channel]')) unsubscribe(root);
    root.querySelectorAll('[data-live-channel]').forEach(unsubscribe);
}

function start() {
    scan(document.body);
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.removedNodes.forEach(sweep);
            mutation.addedNodes.forEach(scan);
        });
    }).observe(document.body, { childList: true, subtree: true });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
else start();
