import { Controller } from '@hotwired/stimulus';

/*
 * Un dialogue qui s'ouvre et se ferme, rien de plus.
 *
 * Purement présentationnel : ce qu'il contient vient du serveur, mais qu'il
 * soit ouvert ou non ne regarde que cet onglet. Un aller-retour réseau pour
 * afficher ce qui est déjà dans la page serait un gaspillage visible.
 *
 * L'ouverture est déléguée au document : le déclencheur vit ailleurs dans la
 * page, et le dialogue à la fin — un `data-action` posé sur le déclencheur ne
 * l'atteindrait jamais.
 */
export default class extends Controller {
    connect() {
        this.onTrigger = (event) => {
            if (event.target.closest(`[data-fx-dialog="${this.element.dataset.fxDialogName}"]`)) {
                event.preventDefault();
                this.open();
            }
        };

        this.onEscape = (event) => {
            if ('Escape' === event.key) {
                this.close();
            }
        };

        document.addEventListener('click', this.onTrigger);
        document.addEventListener('keydown', this.onEscape);
    }

    disconnect() {
        document.removeEventListener('click', this.onTrigger);
        document.removeEventListener('keydown', this.onEscape);
    }

    open() {
        this.element.hidden = false;
    }

    close() {
        this.element.hidden = true;
    }

    /* Le voile ferme, le panneau non : un clic dans le dialogue n'est pas un
       clic à côté. */
    dismiss(event) {
        if (event.target === this.element) {
            this.close();
        }
    }
}
