import { Controller } from '@hotwired/stimulus';

/*
 * Ce que devient la réponse du modèle.
 *
 * Purement client : l'éditeur possède le texte en cours, y compris ce qui n'est
 * pas encore sauvegardé. Repasser par le serveur pour insérer trois lignes
 * écraserait des frappes.
 */
export default class extends Controller {
    static targets = ['text'];

    insert() {
        this.dispatch('apply', {
            prefix: 'focusyn',
            detail: { text: this.textTarget.textContent, mode: 'append' },
            target: document,
        });
    }

    replace() {
        this.dispatch('apply', {
            prefix: 'focusyn',
            detail: { text: this.textTarget.textContent, mode: 'replace' },
            target: document,
        });
    }
}
