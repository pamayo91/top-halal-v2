<script>
    (() => {
        const updateOffset = () => {
            const topbar = document.querySelector('.fi-topbar-ctn');
            document.documentElement.style.setProperty('--fi-editor-toolbar-offset', `${topbar?.getBoundingClientRect().height || 64}px`);
        };

        const start = () => {
            updateOffset();
            const topbar = document.querySelector('.fi-topbar-ctn');
            if (topbar && window.ResizeObserver) new ResizeObserver(updateOffset).observe(topbar);
        };

        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start, { once: true }) : start();
        window.addEventListener('resize', updateOffset, { passive: true });
    })();
</script>
