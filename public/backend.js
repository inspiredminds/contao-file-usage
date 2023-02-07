(function() {
    'use strict';

    const selector = '.selector_container > ul > li > .replace-image > img';
    const initialized = new WeakMap();

    const init = (img) => {
        if (initialized.has(img)) {
            return;
        }

        initialized.set(img, true);

        const link = img.closest('a');

        link.addEventListener('click', () => {
            img.classList.add('rotate');
        });
    };

    document.querySelectorAll(selector).forEach(init);

    new MutationObserver(function (mutationsList) {
        for (const mutation of mutationsList) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(function (element) {
                    if (element.matches && element.matches(selector)) {
                        init(element);
                    }
                })
            }
        }
    }).observe(document, {
        attributes: false,
        childList: true,
        subtree: true
    });
})();
