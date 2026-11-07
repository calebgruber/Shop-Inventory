(() => {
    'use strict';
    let saved;
    try { saved = localStorage.getItem('theatre-theme'); } catch { saved = null; }
    const configured = document.documentElement.dataset.bsTheme;
    const theme = ['light', 'dark'].includes(saved) ? saved :
        ['light', 'dark'].includes(configured) ? configured :
            matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.dataset.bsTheme = theme;
})();
