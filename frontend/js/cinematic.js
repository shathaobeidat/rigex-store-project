'use strict';

(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const root = document.documentElement;
    const intro = document.getElementById('regix-intro');
    let introFinished = false;
    let counterFrame = null;
    let lenis = null;

    document.querySelectorAll('.hero-word').forEach((line) => {
        Array.from(line.children).forEach((letter, index) => letter.style.setProperty('--letter-index', index));
    });

    const finishIntro = () => {
        if (introFinished) return;
        introFinished = true;
        cancelAnimationFrame(counterFrame);
        document.getElementById('regix-load-count').textContent = '100';
        intro.classList.add('is-leaving');
        document.body.classList.remove('regix-intro-loading');
        root.classList.add('regix-motion-ready');
        lenis?.start();
        try { sessionStorage.setItem('regix-campaign-intro', 'seen'); } catch (_) { /* Storage is optional. */ }
        setTimeout(() => { intro.hidden = true; intro.setAttribute('aria-hidden', 'true'); }, 650);
    };

    let seenIntro = false;
    try { seenIntro = sessionStorage.getItem('regix-campaign-intro') === 'seen'; } catch (_) { /* Continue without storage. */ }
    if (!reducedMotion.matches && !seenIntro && !window.location.hash) {
        intro.hidden = false;
        intro.removeAttribute('aria-hidden');
        document.body.classList.add('regix-intro-loading');
        const started = performance.now();
        const count = (now) => {
            const progress = Math.min(1, (now - started) / 2000);
            document.getElementById('regix-load-count').textContent = String(Math.round(progress * 100));
            if (progress < 1) counterFrame = requestAnimationFrame(count);
            else setTimeout(finishIntro, 200);
        };
        counterFrame = requestAnimationFrame(count);
    } else {
        introFinished = true;
        intro.hidden = true;
        root.classList.add('regix-motion-ready');
    }
    document.getElementById('skip-regix-intro')?.addEventListener('click', finishIntro);

    if (!reducedMotion.matches && 'IntersectionObserver' in window) {
        root.classList.add('regix-reveals-enabled');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('in-view');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0 });
        document.querySelectorAll('.campaign-reveal').forEach((element) => observer.observe(element));
    }

    const motionButton = document.getElementById('campaign-motion-toggle');
    motionButton?.addEventListener('click', () => {
        const paused = root.classList.toggle('campaign-motion-paused');
        motionButton.setAttribute('aria-pressed', String(paused));
        motionButton.textContent = paused ? 'Resume motion ▷' : 'Pause motion Ⅱ';
    });

    const focusCopy = {
        play: 'Stay in the moment. Start with graphics performance, a balanced processor, and the responsiveness that makes every session feel right.',
        create: 'Give your ideas room to grow. Think processing power, generous memory, and fast storage for the projects you can’t stop thinking about.',
        everyday: 'Make the everyday feel effortless. A responsive system, thoughtful essentials, and the right upgrades can change the way you work and play.',
    };
    const tabs = Array.from(document.querySelectorAll('[data-focus]'));
    const selectFocus = (tab) => {
        tabs.forEach((button) => {
            const active = button === tab;
            button.setAttribute('aria-selected', String(active));
            button.tabIndex = active ? 0 : -1;
        });
        const panel = document.getElementById('focus-panel');
        const copy = document.getElementById('focus-copy');
        panel.setAttribute('aria-labelledby', tab.id);
        const text = focusCopy[tab.dataset.focus];
        copy.replaceChildren();
        text.split(' ').forEach((word, index) => {
            const span = document.createElement('span');
            span.className = 'focus-word';
            span.textContent = word;
            span.style.animationDelay = `${index * 22}ms`;
            copy.append(span, document.createTextNode(' '));
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectFocus(tab));
        tab.addEventListener('keydown', (event) => {
            let next = index;
            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else return;
            event.preventDefault();
            selectFocus(tabs[next]);
            tabs[next].focus();
        });
    });

    // Local SVG filters provide the reference's liquid hover without a framework.
    if (!reducedMotion.matches && window.matchMedia('(hover: hover) and (min-width: 769px)').matches) {
        const ns = 'http://www.w3.org/2000/svg';
        document.querySelectorAll('.campaign-liquid').forEach((figure, index) => {
            const image = figure.querySelector('img');
            const bare = figure.hasAttribute('data-bare');
            const svg = document.createElementNS(ns, 'svg');
            svg.setAttribute('width', '0');
            svg.setAttribute('height', '0');
            svg.setAttribute('aria-hidden', 'true');
            svg.style.position = 'absolute';
            const filter = document.createElementNS(ns, 'filter');
            filter.id = `regix-liquid-${index}`;
            const noise = document.createElementNS(ns, 'feTurbulence');
            noise.setAttribute('type', 'fractalNoise');
            noise.setAttribute('baseFrequency', '0.009');
            noise.setAttribute('numOctaves', '2');
            noise.setAttribute('result', 'noise');
            const displacement = document.createElementNS(ns, 'feDisplacementMap');
            displacement.setAttribute('in', 'SourceGraphic');
            displacement.setAttribute('in2', 'noise');
            displacement.setAttribute('scale', '1');
            displacement.setAttribute('xChannelSelector', 'R');
            displacement.setAttribute('yChannelSelector', 'G');
            filter.append(noise, displacement);
            svg.append(filter);
            figure.append(svg);
            let frame = null;
            let current = 1;
            const animate = (target) => {
                cancelAnimationFrame(frame);
                const initial = current;
                const started = performance.now();
                const tick = (now) => {
                    const progress = Math.min(1, (now - started) / 520);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    current = initial + (target - initial) * eased;
                    displacement.setAttribute('scale', String(current));
                    noise.setAttribute('baseFrequency', String(.009 + (current / 28) * .013));
                    if (progress < 1) frame = requestAnimationFrame(tick);
                };
                frame = requestAnimationFrame(tick);
            };
            const resetImage = () => {
                image.style.filter = bare ? '' : 'grayscale(.9)';
                animate(1);
            };
            figure.addEventListener('pointerenter', () => {
                if (root.classList.contains('campaign-motion-paused')) return;
                image.style.filter = `url(#${filter.id})`;
                animate(bare ? 18 : 24);
            });
            figure.addEventListener('pointerleave', resetImage);
        });
    }

    // Smooth scrolling is progressive enhancement; native scrolling remains usable.
    if (!reducedMotion.matches) {
        import('https://cdn.jsdelivr.net/npm/lenis@1.3.19/dist/lenis.mjs').then(({ default: Lenis }) => {
            lenis = new Lenis({
                smoothWheel: true,
                anchors: true,
                prevent: (node) => Boolean(node.closest('dialog, .store-account-panel')),
            });
            if (!introFinished) lenis.stop();
            const raf = (time) => {
                if (!lenis) return;
                lenis.raf(time);
                requestAnimationFrame(raf);
            };
            requestAnimationFrame(raf);
        }).catch(() => { /* Native scrolling is the fallback. */ });
    }

    reducedMotion.addEventListener('change', () => {
        if (!reducedMotion.matches) return;
        finishIntro();
        root.classList.remove('regix-reveals-enabled');
        lenis?.destroy();
        lenis = null;
    });
})();
