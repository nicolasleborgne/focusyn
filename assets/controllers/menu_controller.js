import { Controller } from '@hotwired/stimulus';

/*
 * Menu déroulant : ouverture, fermeture au clic extérieur et à Échap.
 *
 * Purement client, comme le mode focus : l'état « ouvert » n'a aucune valeur
 * hors de l'onglet courant.
 */
export default class extends Controller {
    static targets = ['panel', 'trigger'];

    connect() {
        this.onDocumentClick = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        this.onKeydown = (event) => {
            if (event.key === 'Escape') {
                this.close();
            }
        };

        document.addEventListener('click', this.onDocumentClick);
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocumentClick);
        document.removeEventListener('keydown', this.onKeydown);
    }

    toggle() {
        this.setOpen(this.panelTarget.hidden);
    }

    close() {
        this.setOpen(false);
    }

    setOpen(open) {
        this.panelTarget.hidden = !open;

        if (this.hasTriggerTarget) {
            this.triggerTarget.setAttribute('aria-expanded', String(open));
            this.triggerTarget.classList.toggle('is-open', open);
        }
    }
}
