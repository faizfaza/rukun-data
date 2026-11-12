<script>
    (() => {
        if (window.__rdFilterBadgeZeroInitialized) {
            return;
        }

        window.__rdFilterBadgeZeroInitialized = true;

        const selector = [
            '.fi-ta-filters-trigger-action-ctn .fi-badge',
            '.fi-ta-filters-dropdown .fi-dropdown-trigger .fi-icon-btn-badge-ctn .fi-badge',
            '.fi-ta-filters-modal .fi-icon-btn-badge-ctn .fi-badge',
            '.fi-ta-filters-before-content .fi-icon-btn-badge-ctn .fi-badge',
            '.fi-ta-filters-after-content .fi-icon-btn-badge-ctn .fi-badge',
        ].join(', ');

        const sync = () => {
            document.querySelectorAll(selector).forEach((badge) => {
                badge.style.display = badge.textContent.trim() === '0' ? 'none' : '';
            });
        };

        // Mode SPA (wire:navigate) mengganti elemen <body>, jadi observer dipasang
        // pada <html> yang tetap ada, dan sinkron ulang setiap selesai navigasi.
        document.addEventListener('livewire:navigated', sync);

        const start = () => {
            sync();
            new MutationObserver(sync).observe(document.documentElement, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start, { once: true });
        } else {
            start();
        }
    })();
</script>
