import { Controller } from '@hotwired/stimulus';

/*
 * Deux onglets, deux sections déjà rendues.
 *
 * Purement présentationnel, et local à l'onglet du navigateur : il n'y a rien à
 * demander au serveur, les deux listes sont là. Un aller-retour réseau pour
 * montrer ce qui est déjà dans la page serait un gaspillage visible à l'œil.
 *
 * L'état ne survit pas au rechargement, et c'est voulu : l'écran des tâches
 * s'ouvre sur les listes, comme dans la maquette.
 */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    connect() {
        this.show(this.tabTargets.findIndex((tab) => tab.dataset.tabsDefault === 'true') || 0);
    }

    select(event) {
        this.show(this.tabTargets.indexOf(event.currentTarget));
    }

    show(index) {
        this.tabTargets.forEach((tab, i) => {
            tab.classList.toggle('fx-pill--selected', i === index);
            tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });
        this.panelTargets.forEach((panel, i) => {
            panel.hidden = i !== index;
        });
    }
}
