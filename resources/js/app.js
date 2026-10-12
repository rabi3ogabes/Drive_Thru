import './push';
import Alpine from 'alpinejs';

// Public pages (website, invitations) use Alpine directly. Livewire pages bring
// their own Alpine, so only start one when none is present.
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}
