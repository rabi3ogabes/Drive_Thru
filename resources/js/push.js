/*
 * Web push on this browser, for staff alerts and guest order updates.
 * window.dtpPush.state()   → 'unsupported' | 'unconfigured' | 'denied' | 'on' | 'off'
 * window.dtpPush.enable(o) → asks permission, subscribes, saves it on the server
 * window.dtpPush.disable(o)→ unsubscribes and forgets it on the server
 * o.restaurant is the restaurant slug for guests; staff omit it.
 * The public VAPID key comes from <meta name="webpush-key">.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const vapidKey = () => document.querySelector('meta[name="webpush-key"]')?.content ?? '';

function supported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
}

function keyBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(padded);
    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

async function registration() {
    const existing = await navigator.serviceWorker.getRegistration('/');
    return existing ?? navigator.serviceWorker.register('/sw.js');
}

async function send(method, subscription, options) {
    const body = { ...subscription.toJSON(), restaurant: options?.restaurant ?? null };
    const response = await fetch('/push/subscriptions', {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    if (!response.ok) throw new Error(String(response.status));
}

async function state() {
    if (!supported()) return 'unsupported';
    if (!vapidKey()) return 'unconfigured';
    if (Notification.permission === 'denied') return 'denied';
    const reg = await navigator.serviceWorker.getRegistration('/');
    const subscription = reg ? await reg.pushManager.getSubscription() : null;
    return subscription && Notification.permission === 'granted' ? 'on' : 'off';
}

async function enable(options = {}) {
    if (!supported() || !vapidKey()) return state();
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return state();

    const reg = await registration();
    await navigator.serviceWorker.ready;
    const subscription = (await reg.pushManager.getSubscription())
        ?? (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(vapidKey()) }));
    await send('POST', subscription, options);

    return 'on';
}

async function disable(options = {}) {
    if (!supported()) return 'unsupported';
    const reg = await navigator.serviceWorker.getRegistration('/');
    const subscription = reg ? await reg.pushManager.getSubscription() : null;
    if (subscription) {
        await send('DELETE', subscription, options).catch(() => {});
        await subscription.unsubscribe();
    }

    return state();
}

window.dtpPush = { supported, state, enable, disable };
