import { Controller } from '@hotwired/stimulus';

/*
 * Champ de titre qui grandit avec son contenu.
 *
 * Un `input` tronque : un titre de note un peu long disparaissait au-delà du
 * bord droit, sans que rien ne le signale. La maquette emploie une zone de
 * texte qui s'ajuste — c'est la seule façon de voir ce qu'on écrit.
 */
export default class extends Controller {
    connect() {
        this.grow();
        this.element.addEventListener('input', this.grow);
    }

    disconnect() {
        this.element.removeEventListener('input', this.grow);
    }

    grow = () => {
        this.element.style.height = 'auto';
        this.element.style.height = `${this.element.scrollHeight}px`;
    };

    // Le titre se valide aussi à la sortie du champ. C'était un
    // `onchange="this.form.requestSubmit()"` dans le gabarit : un attribut
    // que le CSP bloque, et qui ne se voyait pas parce qu'un
    // `data-action="change->autogrow#save"` pointait déjà, à côté, vers cette
    // méthode qui n'existait pas.
    save() {
        this.element.form?.requestSubmit();
    }

    // Entrée valide au lieu d'insérer un saut de ligne : un titre tient sur
    // une ligne logique, même s'il s'affiche sur plusieurs.
    submit(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.element.form?.requestSubmit();
            this.element.blur();
        }
    }
}
