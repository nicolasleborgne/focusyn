import { Controller } from '@hotwired/stimulus';
import { EditorView, keymap, highlightActiveLine, drawSelection } from '@codemirror/view';
import { EditorState } from '@codemirror/state';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { markdown } from '@codemirror/lang-markdown';
import { HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import { tags } from '@lezer/highlight';

/*
 * Éditeur de note.
 *
 * Seul endroit de l'application qui échappe aux Live Components : l'édition
 * réagit à chaque frappe, et un aller-retour réseau par caractère serait
 * inutilisable. CodeMirror garde la source markdown affichée telle quelle,
 * avec ses marques — c'est exactement le parti pris de la maquette — et se
 * charge du curseur, de l'annulation, de la sélection et du mobile.
 *
 * Les couleurs et les tailles viennent du design system : aucune valeur en dur.
 */

const prose = (token) => getComputedStyle(document.documentElement).getPropertyValue(token).trim();

const focusynHighlight = () =>
    HighlightStyle.define([
        // Les marques markdown (#, -, >, **) restent visibles mais s'effacent.
        { tag: tags.processingInstruction, color: prose('--fx-ink-100'), opacity: prose('--fx-markdown-mark-opacity') },
        { tag: tags.heading1, fontSize: prose('--fx-prose-h1'), fontWeight: '600', lineHeight: '1.2', color: prose('--fx-text-title') },
        { tag: tags.heading2, fontSize: prose('--fx-prose-h2'), fontWeight: '600', lineHeight: '1.25', color: prose('--fx-text-title') },
        { tag: tags.heading3, fontSize: prose('--fx-prose-h3'), fontWeight: '650', lineHeight: '1.3', color: prose('--fx-text-title') },
        { tag: tags.strong, fontWeight: '650', color: prose('--fx-text-title') },
        { tag: tags.emphasis, fontStyle: 'italic' },
        { tag: tags.quote, fontStyle: 'italic', color: prose('--fx-text-quote') },
        { tag: tags.monospace, fontFamily: prose('--fx-family-mono'), fontSize: '0.85em' },
        { tag: tags.link, textDecoration: 'underline', textUnderlineOffset: '3px' },
        { tag: tags.url, color: prose('--fx-text-disabled') },
        { tag: tags.contentSeparator, color: prose('--fx-text-disabled') },
    ]);

const focusynTheme = () =>
    EditorView.theme({
        '&': {
            fontFamily: prose('--fx-font-prose'),
            fontSize: prose('--fx-prose-body'),
            color: prose('--fx-text-body'),
            backgroundColor: 'transparent',
        },
        '&.cm-focused': { outline: 'none' },
        '.cm-content': { padding: '0', lineHeight: prose('--fx-leading-prose'), caretColor: prose('--fx-accent') },
        '.cm-line': { padding: '0' },
        '.cm-activeLine': { backgroundColor: 'transparent' },
        '.cm-cursor': { borderLeftColor: prose('--fx-accent'), borderLeftWidth: '2px' },
        '.cm-selectionBackground, ::selection': { backgroundColor: prose('--fx-surface-selection') },
        '.cm-scroller': { fontFamily: 'inherit', lineHeight: 'inherit' },
    });

export default class extends Controller {
    static targets = ['host', 'status'];
    static values = {
        body: String,
        saveUrl: String,
        token: String,
        savedLabel: String,
        savingLabel: String,
        dirtyLabel: String,
        failedLabel: String,
    };

    static debounceMs = 900;

    connect() {
        this.view = new EditorView({
            parent: this.hostTarget,
            state: EditorState.create({
                doc: this.bodyValue,
                extensions: [
                    history(),
                    drawSelection(),
                    highlightActiveLine(),
                    EditorView.lineWrapping,
                    keymap.of([...defaultKeymap, ...historyKeymap]),
                    markdown(),
                    syntaxHighlighting(focusynHighlight()),
                    focusynTheme(),
                    EditorView.updateListener.of((update) => {
                        if (update.docChanged) {
                            this.scheduleSave();
                        }
                    }),
                ],
            }),
        });

        this.saved = this.bodyValue;
        // Une note ouverte puis fermée sans modification ne doit rien écrire :
        // sinon elle remonterait en tête des récentes pour rien.
        this.announce(this.savedLabelValue);

        this.onBeforeUnload = (event) => {
            if (this.view.state.doc.toString() !== this.saved) {
                event.preventDefault();
            }
        };
        window.addEventListener('beforeunload', this.onBeforeUnload);
    }

    disconnect() {
        window.removeEventListener('beforeunload', this.onBeforeUnload);
        window.clearTimeout(this.timer);
        this.view?.destroy();
    }

    scheduleSave() {
        this.announce(this.dirtyLabelValue);
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => this.save(), this.constructor.debounceMs);
    }

    async save() {
        const body = this.view.state.doc.toString();

        if (body === this.saved) {
            return;
        }

        this.announce(this.savingLabelValue);

        try {
            const response = await fetch(this.saveUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
                body: JSON.stringify({ body }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            this.saved = body;
            this.announce(this.savedLabelValue);
        } catch (error) {
            console.error('[focusyn] sauvegarde impossible', error);
            // On garde la version locale : l'utilisateur peut réessayer en
            // continuant d'écrire, rien n'est perdu.
            this.announce(this.failedLabelValue);
        }
    }

    announce(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }
}
