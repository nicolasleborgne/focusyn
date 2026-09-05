import { Controller } from '@hotwired/stimulus';

/*
 * Mode « focus profond ».
 *
 * C'est un état de présentation pur : rien à enregistrer côté serveur, donc
 * pas de Live Component — un aller-retour réseau pour masquer une barre
 * latérale serait un gaspillage visible à l'œil.
 *
 * La préférence est conservée par navigateur : rouvrir une note ne doit pas
 * faire réapparaître toute la chrome qu'on venait d'écarter.
 */
export default class extends Controller {
    static targets = ['focusButton', 'focusLabel', 'focusTrack'];
    static classes = ['focus'];
    static values = { focusable: Boolean };

    static storageKey = 'focusyn.focus';

    connect() {
        if (!this.focusableValue) {
            return;
        }

        this.apply(window.localStorage.getItem(this.constructor.storageKey) === '1');
    }

    toggleFocus() {
        this.apply(!this.element.classList.contains(this.focusClass));
    }

    apply(enabled) {
        this.element.classList.toggle(this.focusClass, enabled);

        if (this.hasFocusButtonTarget) {
            this.focusButtonTarget.setAttribute('aria-pressed', String(enabled));
            this.focusButtonTarget.classList.toggle('is-active', enabled);
        }

        if (this.hasFocusTrackTarget) {
            this.focusTrackTarget.parentElement.classList.toggle('fx-switch--on', enabled);
        }

        if (this.hasFocusLabelTarget) {
            this.focusLabelTarget.textContent = this.focusLabelTarget.dataset[enabled ? 'on' : 'off']
                ?? this.focusLabelTarget.textContent;
        }

        window.localStorage.setItem(this.constructor.storageKey, enabled ? '1' : '0');
    }
}
