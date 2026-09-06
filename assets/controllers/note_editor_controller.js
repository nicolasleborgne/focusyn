import { Controller } from '@hotwired/stimulus';
import { Decoration, EditorView, ViewPlugin, keymap, highlightActiveLine, drawSelection } from '@codemirror/view';
import { EditorState, RangeSetBuilder } from '@codemirror/state';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { markdown } from '@codemirror/lang-markdown';
import { HighlightStyle, syntaxHighlighting, syntaxTree } from '@codemirror/language';
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
 * Mieux : chaque ligne reçoit la classe `fx-prose__line--*` que porte déjà
 * l'aperçu, de sorte que les deux moitiés de l'écran sont mises en forme par la
 * même feuille de style. C'est ce que fait la maquette, où l'éditeur et
 * l'aperçu sont le même rendu à une différence près : les marques.
 */

/*
 * La nature d'une ligne est lue dans l'arbre syntaxique de CodeMirror, pas
 * redevinée par une expression régulière : il n'y a toujours qu'un seul
 * analyseur markdown côté client, celui de l'éditeur.
 */
const LINE_KINDS = {
    ATXHeading1: 'h1',
    SetextHeading1: 'h1',
    ATXHeading2: 'h2',
    SetextHeading2: 'h2',
    ATXHeading3: 'h3',
    Blockquote: 'quote',
    ListItem: 'list',
    BulletList: 'list',
    OrderedList: 'list',
    FencedCode: 'code',
    CodeBlock: 'code',
    HorizontalRule: 'rule',
};

const kindAt = (tree, position) => {
    let node = tree.resolveInner(position, 1);

    while (node) {
        if (LINE_KINDS[node.name]) {
            return LINE_KINDS[node.name];
        }

        node = node.parent;
    }

    return 'paragraph';
};

const proseLines = ViewPlugin.fromClass(
    class {
        constructor(view) {
            this.decorations = this.build(view);
        }

        update(update) {
            if (update.docChanged || update.viewportChanged) {
                this.decorations = this.build(update.view);
            }
        }

        build(view) {
            const builder = new RangeSetBuilder();
            const tree = syntaxTree(view.state);
            let last = -1;

            for (const { from, to } of view.visibleRanges) {
                for (let position = from; position <= to; ) {
                    const line = view.state.doc.lineAt(position);

                    if (line.from > last) {
                        const kind = line.length === 0 ? 'paragraph' : kindAt(tree, line.from);
                        builder.add(
                            line.from,
                            line.from,
                            Decoration.line({ class: `fx-prose__line fx-prose__line--${kind}` }),
                        );
                        last = line.from;
                    }

                    position = line.to + 1;
                }
            }

            return builder.finish();
        }
    },
    { decorations: (plugin) => plugin.decorations },
);

const prose = (token) => getComputedStyle(document.documentElement).getPropertyValue(token).trim();

const focusynHighlight = () =>
    HighlightStyle.define([
        // Les marques markdown (#, -, >, **) restent visibles mais s'effacent.
        { tag: tags.processingInstruction, color: prose('--fx-ink-100'), opacity: prose('--fx-markdown-mark-opacity') },
        // Les tailles de titre viennent de la classe de ligne, comme dans
        // l'aperçu : ici, seulement ce qui est propre au fragment.
        { tag: tags.heading1, color: prose('--fx-text-title') },
        { tag: tags.heading2, color: prose('--fx-text-title') },
        { tag: tags.heading3, color: prose('--fx-text-title') },
        { tag: tags.strong, fontWeight: prose('--fx-weight-strong'), color: prose('--fx-text-title') },
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
            fontFamily: 'inherit',
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
    static targets = ['host', 'status', 'preview', 'meta'];
    static values = {
        body: String,
        saveUrl: String,
        token: String,
        savedLabel: String,
        savingLabel: String,
        dirtyLabel: String,
        failedLabel: String,
    };

    /*
     * L'aperçu est rendu par le serveur : c'est ce délai qui décide s'il paraît
     * vivant. Neuf cents millisecondes se voyaient ; quatre cents, non.
     */
    static debounceMs = 400;

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
                    proseLines,
                    EditorView.contentAttributes.of({ class: 'fx-prose' }),
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

        // L'assistant vit dans un autre composant, plus bas dans la page : il
        // annonce son résultat, l'éditeur décide où le mettre. Personne d'autre
        // que lui ne doit toucher au document.
        this.onAssistantApply = (event) => this.apply(event.detail);
        document.addEventListener('focusyn:apply', this.onAssistantApply);
    }

    disconnect() {
        window.removeEventListener('beforeunload', this.onBeforeUnload);
        document.removeEventListener('focusyn:apply', this.onAssistantApply);
        window.clearTimeout(this.timer);
        this.view?.destroy();
    }

    /* Insère à la fin, ou remplace tout. Un seul changement, donc une seule
       entrée dans l'historique : Ctrl-Z annule l'insertion d'un bloc. */
    apply({ text, mode }) {
        const doc = this.view.state.doc;
        const trimmed = String(text ?? '').trim();

        if (trimmed === '') {
            return;
        }

        const change = mode === 'replace'
            ? { from: 0, to: doc.length, insert: trimmed + '\n' }
            : { from: doc.length, to: doc.length, insert: '\n\n' + trimmed + '\n' };

        this.view.dispatch({ changes: change });
        this.view.focus();
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

            const payload = await response.json();

            this.saved = body;
            this.announce(this.savedLabelValue);

            // Le serveur renvoie l'aperçu déjà rendu : c'est le même analyseur
            // que partout ailleurs, il ne peut pas diverger de l'éditeur.
            if (this.hasPreviewTarget && typeof payload.preview === 'string') {
                this.previewTarget.innerHTML = payload.preview;
            }

            // Le décompte vient du serveur lui aussi : le refaire ici donnerait
            // deux façons de compter un mot, qui finiraient par diverger.
            if (this.hasMetaTarget && typeof payload.meta === 'string') {
                this.metaTarget.innerHTML = payload.meta;
            }
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
