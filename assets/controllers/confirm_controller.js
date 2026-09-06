import { Controller } from '@hotwired/stimulus';

/*
 * Confirmation d'une action irréversible.
 *
 * Purement client : le dialogue ne fait que retenir un envoi de formulaire le
 * temps d'une question. Le serveur, lui, reçoit exactement la même requête
 * qu'avant — un client sans JavaScript supprime toujours, en un clic.
 */
export default class extends Controller {
    static targets = ['dialog', 'form'];

    connect() {
        this.onKeydown = (event) => {
            if (event.key === 'Escape' && !this.dialogTarget.hidden) {
                this.close();
            }
        };
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKeydown);
    }

    ask(event) {
        event.preventDefault();
        this.dialogTarget.hidden = false;
        // Le premier élément atteignable est le retrait, pas la suppression :
        // une confirmation ne doit pas s'obtenir en appuyant deux fois sur
        // Entrée.
        this.dialogTarget.querySelector('[data-confirm-cancel]')?.focus();
    }

    close() {
        this.dialogTarget.hidden = true;
    }

    dismiss(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    proceed() {
        this.formTarget.requestSubmit();
    }
}
