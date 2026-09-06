import { Controller } from '@hotwired/stimulus';

/*
 * L'interrupteur du thème, quand c'est l'appareil qui décide.
 *
 * Le serveur sait ce que le compte a choisi, jamais ce que l'appareil préfère :
 * l'en-tête ne le dit pas, et cela changerait entre deux requêtes sans qu'aucune
 * ne l'annonce. Réglé sur « suit l'appareil », l'interrupteur rendu serait donc
 * éteint alors que l'écran est sombre — un état faux, sur la ligne même qui
 * prétend le régler.
 *
 * D'où cette correction, purement présentationnelle et locale à l'onglet : la
 * position du bouton, et la valeur que le clic postera. Le réglage lui-même
 * reste au serveur.
 *
 * Sans JavaScript, rien ne casse : l'interrupteur part de « éteint » et le
 * premier clic passe au sombre. Il faut alors deux clics pour revenir au clair
 * si l'appareil était déjà sombre — un pas de trop, pas une impasse.
 */
export default class extends Controller {
    static targets = ['switch', 'value'];
    static values = { choice: String };

    connect() {
        if (this.choiceValue !== 'system') {
            return;
        }

        this.query = window.matchMedia('(prefers-color-scheme: dark)');
        this.follow = () => this.reflect(this.query.matches);
        this.query.addEventListener('change', this.follow);
        this.follow();
    }

    disconnect() {
        this.query?.removeEventListener('change', this.follow);
    }

    /* L'appareil est passé au sombre pendant qu'on regardait la page : la
       ligne suit, sans recharger. */
    reflect(dark) {
        this.switchTarget.classList.toggle('fx-switch--on', dark);
        this.switchTarget.setAttribute('aria-pressed', dark ? 'true' : 'false');
        this.valueTarget.value = dark ? 'light' : 'dark';
    }
}
