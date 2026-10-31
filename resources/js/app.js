import Alpine from 'alpinejs';

// Alpine powers the interactive forms (purchase payment choice, supplier
// payments, expenses). Views register their components on the page with a
// `alpine:init` listener, which only fires from start() — so start it after
// the document has been parsed (module scripts are deferred, so the inline
// listeners are already registered by then).
window.Alpine = Alpine;
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => Alpine.start(), { once: true });
} else {
	Alpine.start();
}

document.addEventListener('DOMContentLoaded', () => {
	document.addEventListener('wheel', (event) => {
		if (event.target.closest?.('input[type="number"]')) {
			event.preventDefault();
		}
	}, { passive: false });

	const sidebar = document.querySelector('[data-mobile-sidebar]');
	const overlay = document.querySelector('[data-sidebar-overlay]');
	const toggle = document.querySelector('[data-sidebar-toggle]');
	const close = document.querySelector('[data-sidebar-close]');

	if (!sidebar || !overlay || !toggle) return;

	const setOpen = (open) => {
		sidebar.classList.toggle('translate-x-0', open);
		sidebar.classList.toggle('-translate-x-full', !open);
		overlay.classList.toggle('hidden', !open);
		toggle.setAttribute('aria-expanded', String(open));
	};

	toggle.addEventListener('click', () => setOpen(sidebar.classList.contains('-translate-x-full')));
	overlay.addEventListener('click', () => setOpen(false));
	close?.addEventListener('click', () => setOpen(false));
	sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
});
