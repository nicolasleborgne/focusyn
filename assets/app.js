import './stimulus_bootstrap.js';

/*
 * Le design system n'est pas importé ici : il est chargé par une balise
 * `<link>` dans `base.html.twig`. Importé depuis le JavaScript, il entrait
 * dans l'importmap sous la forme d'une adresse `data:application/javascript`,
 * qu'il aurait fallu autoriser dans `script-src` — et `data:` y ouvre
 * exactement la porte que le nonce ferme.
 */

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
