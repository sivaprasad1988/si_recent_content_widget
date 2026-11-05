/**
 * Scrolls the page module to the content element referenced by the URL
 * fragment (#element-tt_content-{uid}, set by the Recent Content widget),
 * focuses it and highlights it briefly. Elements hidden by the page module
 * ("show hidden content" off renders them display:none) are skipped.
 */
const PREFIX = '#element-tt_content-';
const HIGHLIGHT_CLASS = 'recent-content-widget-focus';

function focusElement() {
    const hash = window.location.hash;
    if (!hash.startsWith(PREFIX) || !/^\d+$/.test(hash.slice(PREFIX.length))) {
        return;
    }
    const element = document.getElementById(hash.slice(1));
    if (!element || element.offsetParent === null) {
        return;
    }

    element.scrollIntoView({ block: 'center', behavior: 'smooth' });
    if (!element.hasAttribute('tabindex')) {
        element.setAttribute('tabindex', '-1');
    }
    element.focus({ preventScroll: true });
    element.classList.add(HIGHLIGHT_CLASS);
    element.addEventListener('animationend', () => element.classList.remove(HIGHLIGHT_CLASS), { once: true });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', focusElement);
} else {
    focusElement();
}
