import './push';
import Alpine from 'alpinejs';

// Public pages (website, invitations) use Alpine directly. Livewire pages bring
// their own Alpine, so only start one when none is present.
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}

// Website: reveal [data-reveal] sections once as they scroll into view.
const revealAll = () => {
    const items = document.querySelectorAll('[data-reveal]:not(.is-visible)');
    if (items.length === 0) return;
    if (!('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }
    const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
        }
    }), { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    items.forEach((el) => io.observe(el));
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', revealAll);
else revealAll();
