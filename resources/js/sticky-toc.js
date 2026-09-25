const desktop = window.matchMedia('(min-width: 761px)');

const initializeStickyTocs = () => {
    document.querySelectorAll('[data-sticky-toc]').forEach(toc => {
        const list = toc.querySelector('[data-toc-list]');
        const toggle = toc.querySelector('[data-toc-toggle]');
        const current = toc.querySelector('[data-toc-current]');
        const currentLink = toc.querySelector('[data-toc-current-link]');
        if (!list || !toggle || !current || !currentLink || !desktop.matches) return;
        const links = [...list.querySelectorAll('a')];
        const headings = links.map(link => ({ link, heading: document.getElementById(link.hash.slice(1)) })).filter(({ heading }) => heading);
        if (headings.length < 2) return;

        const isLong = list.scrollHeight > Math.min(420, window.innerHeight * .52);
        if (!isLong) return;

        const compactAfter = toc.getBoundingClientRect().top + window.scrollY + 220;
        let frame;
        const update = () => {
            frame = undefined;
            const active = headings.reduce((last, item) => item.heading.getBoundingClientRect().top <= window.innerHeight * .32 ? item : last, headings[0]);
            currentLink.textContent = active.link.textContent;
            currentLink.href = active.link.getAttribute('href');
            toc.classList.toggle('is-compact', window.scrollY >= compactAfter);
        };
        const queueUpdate = () => { if (!frame) frame = window.requestAnimationFrame(update); };

        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            const expanded = toc.classList.toggle('is-expanded');
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.textContent = expanded ? 'Réduire le sommaire' : 'Afficher le sommaire';
        });
        window.addEventListener('scroll', queueUpdate, { passive: true });
        window.addEventListener('resize', queueUpdate, { passive: true });
        update();
    });
};

initializeStickyTocs();
