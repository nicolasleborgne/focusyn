import './stimulus_bootstrap.js';
import './styles/app.css';

/*
 * Le service worker vit dans public/sw.js, hors d'AssetMapper : il doit être
 * servi depuis la racine pour contrôler toute l'origine, et sans condensat dans
 * son nom pour que le navigateur puisse en détecter les mises à jour.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('[focusyn] enregistrement du service worker impossible', error);
        });
    });
}
