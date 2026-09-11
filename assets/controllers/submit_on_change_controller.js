import { Controller } from '@hotwired/stimulus';

/*
 * Un champ qui valide son formulaire en le quittant.
 *
 * Les étiquettes d'une note et l'obsession d'une routine s'enregistrent ainsi :
 * il n'y a pas de bouton, et en attendre un obligerait à en dessiner un pour
 * une saisie qui tient en trois mots.
 *
 * C'était un `onchange="this.form.requestSubmit()"` posé dans le gabarit. Un
 * attribut de gestionnaire est du JavaScript en ligne comme un autre : le CSP
 * le bloque, et aucun nonce ne s'applique à un attribut.
 */
export default class extends Controller {
    submit() {
        this.element.form?.requestSubmit();
    }
}
