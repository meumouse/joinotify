/**
 * jsonEditor.ts
 *
 * CodeMirror 6 setup for editing a JSON template in the builder: line numbers,
 * JSON highlighting, folding, bracket matching and closing, undo history, a
 * lint gutter fed by the caller's checker, {{ placeholder }} highlighting and
 * autocomplete (typing "{{" lists the variables of the workflow), plus the JSON
 * literals. Loaded with a dynamic import, so CodeMirror stays out of the
 * builder's main bundle until a JSON editor is shown.
 *
 * @since 2.7.1
 */
import { EditorState, Compartment } from '@codemirror/state';
import {
  Decoration,
  EditorView,
  MatchDecorator,
  ViewPlugin,
  drawSelection,
  highlightActiveLine,
  highlightActiveLineGutter,
  keymap,
  lineNumbers,
  placeholder as placeholderText,
} from '@codemirror/view';
import type { DecorationSet, ViewUpdate } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { bracketMatching, foldGutter, foldKeymap, indentOnInput } from '@codemirror/language';
import { autocompletion, closeBrackets, closeBracketsKeymap, completionKeymap } from '@codemirror/autocomplete';
import type { Completion, CompletionContext, CompletionResult } from '@codemirror/autocomplete';
import { linter, lintGutter } from '@codemirror/lint';
import type { Diagnostic } from '@codemirror/lint';
import { json } from '@codemirror/lang-json';
import { oneDark } from '@codemirror/theme-one-dark';

export interface JsonEditorPlaceholder {
  placeholder: string;
  description?: string;
  replacement?: Record<string, unknown>;
}

export interface JsonEditorProblem {
  // 1-based line and column of the problem; the whole line when column is missing.
  line: number;
  column?: number;
  severity: 'error' | 'warning' | 'info';
  message: string;
}

export interface JsonEditorOptions {
  value: string;
  label?: string;
  placeholder?: string;
  disabled?: boolean;
  placeholders?: Array<JsonEditorPlaceholder | string>;
  lint?: (text: string) => JsonEditorProblem[];
  onChange: (value: string) => void;
}

export interface JsonEditor {
  getValue: () => string;
  setValue: (value: string) => void;
  setPlaceholders: (placeholders: Array<JsonEditorPlaceholder | string>) => void;
  setDisabled: (disabled: boolean) => void;
  insert: (text: string) => void;
  focus: () => void;
  destroy: () => void;
}

const PLACEHOLDER_MARK = Decoration.mark({ class: 'cm-joinotify-placeholder' });

const placeholderHighlighter = ViewPlugin.fromClass(
  class {
    decorations: DecorationSet;

    decorator = new MatchDecorator({ regexp: /\{\{[\s\S]*?\}\}/g, decoration: () => PLACEHOLDER_MARK });

    constructor(view: EditorView) {
      this.decorations = this.decorator.createDeco(view);
    }

    update(update: ViewUpdate) {
      this.decorations = this.decorator.updateDeco(update, this.decorations);
    }
  },
  { decorations: (plugin) => plugin.decorations },
);

const LITERALS: Completion[] = ['true', 'false', 'null'].map((label) => ({ label, type: 'keyword' }));

const theme = EditorView.theme({
  '&': { fontSize: '13px', borderRadius: '0.5rem', border: '1px solid rgb(226 232 240)' },
  '&.cm-focused': { outline: '1px solid var(--joinotify-primary, #3b82f6)' },
  '.cm-scroller': { minHeight: '220px', maxHeight: '480px', fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace', lineHeight: '1.6' },
  '.cm-content': { padding: '10px 0' },
  '.cm-joinotify-placeholder': { backgroundColor: 'rgba(59, 130, 246, 0.22)', borderRadius: '3px', color: '#bfdbfe' },
  '.cm-tooltip-autocomplete ul li[aria-selected]': { backgroundColor: '#2563eb' },
  '.cm-completionInfo': { maxWidth: '320px', whiteSpace: 'normal' },
});

/**
 * Normalizes the placeholder list given to the builder fields.
 *
 * @since 2.7.1
 * @param {Array<JsonEditorPlaceholder | string>} items Placeholders.
 * @returns {JsonEditorPlaceholder[]} The placeholders with a token.
 */
function normalizePlaceholders(items: Array<JsonEditorPlaceholder | string>): JsonEditorPlaceholder[] {
  return (Array.isArray(items) ? items : [])
    .map((item) => (typeof item === 'string' ? { placeholder: item } : item))
    .filter((item): item is JsonEditorPlaceholder => Boolean(item && item.placeholder));
}

/**
 * Creates the JSON editor inside a DOM element.
 *
 * @since 2.7.1
 * @param {HTMLElement} parent Element the editor is mounted in.
 * @param {JsonEditorOptions} options Initial value, label, placeholders, checker and change callback.
 * @returns {JsonEditor} Handle to read, write and destroy the editor.
 */
export function createJsonEditor(parent: HTMLElement, options: JsonEditorOptions): JsonEditor {
  let placeholders = normalizePlaceholders(options.placeholders || []);
  const editable = new Compartment();
  // Set while the value is written from outside, so it is not echoed back as a change.
  let syncing = false;

  /**
   * Completes "{{ ..." with the workflow's variables, replacing a closing "}}"
   * already there (closeBrackets adds it), and bare words with the JSON literals.
   */
  const completions = (context: CompletionContext): CompletionResult | null => {
    const token = context.matchBefore(/\{\{[^{}]*/);

    if (token) {
      const after = context.state.sliceDoc(context.pos, context.pos + 6);
      const closing = (/^\s*\}\}/.exec(after) || /^\}/.exec(after) || [''])[0];

      return {
        from: token.from,
        to: context.pos + closing.length,
        options: placeholders.map((item) => {
          const sandbox = typeof item.replacement?.sandbox === 'string' ? item.replacement.sandbox : '';

          return {
            label: item.placeholder,
            type: 'variable',
            detail: sandbox ? sandbox.slice(0, 40) : undefined,
            info: item.description || undefined,
            apply: item.placeholder,
          };
        }),
        validFor: /^\{\{[^{}]*$/,
      };
    }

    const word = context.matchBefore(/[a-z]+/);

    if (!word || (word.from === word.to && !context.explicit)) {
      return null;
    }

    return { from: word.from, options: LITERALS, validFor: /^[a-z]*$/ };
  };

  const lintSource = (view: EditorView): Diagnostic[] => {
    if (!options.lint) {
      return [];
    }

    const doc = view.state.doc;

    return options.lint(doc.toString())
      .filter((problem) => problem.line >= 1 && problem.line <= doc.lines)
      .map((problem) => {
        const line = doc.line(problem.line);
        const from = problem.column ? Math.min(line.from + problem.column - 1, line.to) : line.from;

        return { from, to: problem.column ? Math.min(from + 1, line.to) : line.to, severity: problem.severity, message: problem.message };
      });
  };

  const view = new EditorView({
    parent,
    state: EditorState.create({
      doc: options.value,
      extensions: [
        lineNumbers(),
        highlightActiveLineGutter(),
        foldGutter(),
        lintGutter(),
        history(),
        drawSelection(),
        indentOnInput(),
        bracketMatching(),
        closeBrackets(),
        highlightActiveLine(),
        json(),
        oneDark,
        theme,
        placeholderHighlighter,
        autocompletion({ override: [completions], activateOnTyping: true }),
        linter(lintSource, { delay: 300 }),
        keymap.of([...closeBracketsKeymap, ...defaultKeymap, ...historyKeymap, ...foldKeymap, ...completionKeymap, indentWithTab]),
        EditorState.tabSize.of(2),
        EditorView.lineWrapping,
        placeholderText(options.placeholder || ''),
        EditorView.contentAttributes.of({ 'aria-label': options.label || 'JSON', spellcheck: 'false' }),
        editable.of(EditorView.editable.of(!options.disabled)),
        EditorView.updateListener.of((update) => {
          if (update.docChanged && !syncing) {
            options.onChange(update.state.doc.toString());
          }
        }),
      ],
    }),
  });

  return {
    getValue: () => view.state.doc.toString(),
    setValue(value: string) {
      if (value === view.state.doc.toString()) {
        return;
      }

      syncing = true;
      view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: value } });
      syncing = false;
    },
    setPlaceholders(items) {
      placeholders = normalizePlaceholders(items);
    },
    setDisabled(disabled: boolean) {
      view.dispatch({ effects: editable.reconfigure(EditorView.editable.of(!disabled)) });
    },
    insert(text: string) {
      const { from, to } = view.state.selection.main;

      view.dispatch({ changes: { from, to, insert: text }, selection: { anchor: from + text.length }, scrollIntoView: true });
      view.focus();
    },
    focus: () => view.focus(),
    destroy: () => view.destroy(),
  };
}
