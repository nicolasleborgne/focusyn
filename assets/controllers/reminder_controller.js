import { Controller } from '@hotwired/stimulus';

/*
 * Dialogue d'échéance.
 *
 * L'écoute est déléguée au document plutôt que posée sur chaque pastille : les
 * pastilles vivent ailleurs dans la page — et, sur l'écran des tâches, dans un
 * Live Component qui remplace ses lignes à chaque action. Une écoute attachée
 * aux pastilles disparaîtrait avec elles ; celle-ci leur survit.
 *
 * Tout ce qui se passe ici est local à l'onglet : quel sujet est en cours
 * d'édition, quelle date est affichée avant l'envoi. L'enregistrement, lui, est
 * un envoi de formulaire ordinaire.
 */
export default class extends Controller {
    static targets = [
        'label', 'labelField', 'subject', 'date', 'time',
        'ics', 'dropForm', 'dropSubject',
    ];

    static values = { defaultDate: String };

    connect() {
        this.onChipClick = (event) => {
            const chip = event.target.closest('[data-fx-remind-subject]');

            if (chip) {
                this.open(chip.dataset);
            }
        };
        this.onKeydown = (event) => {
            if (event.key === 'Escape' && !this.element.hidden) {
                this.close();
            }
        };

        document.addEventListener('click', this.onChipClick);
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this.onChipClick);
        document.removeEventListener('keydown', this.onKeydown);
    }

    open(data) {
        const subject = data.fxRemindSubject;
        const label = data.fxRemindLabel || '';
        const date = data.fxRemindDate || '';

        this.subjectTarget.value = subject;
        this.dropSubjectTarget.value = subject;
        this.labelFieldTarget.value = label;
        this.labelTarget.textContent = label;

        this.dateTarget.value = date || this.defaultDateValue;
        this.timeTarget.value = data.fxRemindTime || '09:00';

        // Le .ics et la suppression ne valent que pour un rappel déjà posé.
        const posed = Boolean(date);
        this.dropFormTarget.hidden = !posed;
        this.icsTarget.hidden = !posed;

        if (posed) {
            this.icsTarget.href = data.fxRemindIcs;
        }

        this.element.hidden = false;
        this.dateTarget.focus();
    }

    close() {
        this.element.hidden = true;
    }

    /* Le clic sur le fond ferme ; celui sur le panneau ne doit pas remonter. */
    dismiss(event) {
        if (event.target === this.element) {
            this.close();
        }
    }

    shortcut(event) {
        this.dateTarget.value = event.params.date;
        this.timeTarget.value = event.params.time;
    }
}
