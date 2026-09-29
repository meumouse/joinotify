/**
 * Rewrite translation calls into a shape translate.wordpress.org can extract.
 *
 * WordPress.org builds the plugin's originals by running `wp i18n make-pot` over
 * the shipped package. Its JavaScript scanner only picks up calls whose callee is
 * named `__`/`_n`/`_x`/`_nx` (or a member expression ending in one) and whose text
 * domain is a string literal. The source calls the `utils/i18n` wrapper as
 * `__('Text', textDomain)`, which the minifier turns into `l("Text",a)` — so none
 * of the Vue strings ever reached wp.org, and their translations could not be
 * imported there.
 *
 * During the build, every call to a function imported from `utils/i18n` becomes
 * `wp.i18n.__("Text","joinotify")`. `wp` is a global, so neither Rollup nor the
 * minifier can rename it, and the literal domain survives as well. The wrapper's
 * fallback is not needed at runtime: every Joinotify bundle is enqueued with the
 * `wp-i18n` dependency.
 *
 * Template expressions compiled by Vue reach this hook as
 * `_unref(__)('Text', _unref(textDomain))`; both forms are handled.
 *
 * @since 2.4.3
 */

const TEXT_DOMAIN = 'joinotify';

/**
 * Position of the text-domain argument for each supported function.
 */
const DOMAIN_ARGUMENT_INDEX = {
  __: 1,
  _x: 2,
  _n: 3,
  _nx: 4,
};

const I18N_MODULE_PATTERN = /(^|\/)utils\/i18n(\.[jt]s)?$/;

/**
 * Visit every node of an ESTree AST.
 *
 * @param {Object} node Root node.
 * @param {Function} visit Callback receiving each node.
 */
function walk(node, visit) {
  if (!node || typeof node.type !== 'string') {
    return;
  }

  visit(node);

  for (const key of Object.keys(node)) {
    const value = node[key];

    if (Array.isArray(value)) {
      value.forEach((child) => walk(child, visit));
    } else if (value && typeof value === 'object' && key !== 'loc') {
      walk(value, visit);
    }
  }
}

/**
 * Resolve the identifier behind `name` or `_unref(name)`.
 *
 * @param {Object} node Expression node.
 * @returns {string|null} Identifier name, or null for any other expression.
 */
function unwrapIdentifier(node) {
  if (node?.type === 'Identifier') {
    return node.name;
  }

  if (
    node?.type === 'CallExpression'
    && node.callee.type === 'Identifier'
    && /^_?unref$/.test(node.callee.name)
    && node.arguments.length === 1
    && node.arguments[0].type === 'Identifier'
  ) {
    return node.arguments[0].name;
  }

  return null;
}

/**
 * Read the text of an argument make-pot can extract.
 *
 * @param {Object} node Argument node.
 * @returns {string|null} The literal text, or null for a dynamic expression.
 */
function staticString(node) {
  if (node?.type === 'Literal' && typeof node.value === 'string') {
    return node.value;
  }

  if (node?.type === 'TemplateLiteral' && node.expressions.length === 0) {
    return node.quasis[0].value.cooked;
  }

  return null;
}

/**
 * Build the Vite plugin.
 *
 * @returns {import('vite').Plugin}
 */
export default function wporgI18nCalls() {
  return {
    name: 'joinotify-wporg-i18n-calls',
    apply: 'build',
    enforce: 'post',
    transform(code, id) {
      if (!code.includes('utils/i18n') || id.includes('node_modules')) {
        return null;
      }

      const ast = this.parse(code);
      const functions = new Map();
      const domainLocals = new Set();

      for (const statement of ast.body) {
        if (statement.type !== 'ImportDeclaration' || !I18N_MODULE_PATTERN.test(statement.source.value)) {
          continue;
        }

        for (const specifier of statement.specifiers) {
          if (specifier.type !== 'ImportSpecifier') {
            continue;
          }

          const imported = specifier.imported.name;

          if (imported in DOMAIN_ARGUMENT_INDEX) {
            functions.set(specifier.local.name, imported);
          } else if (imported === 'textDomain') {
            domainLocals.add(specifier.local.name);
          }
        }
      }

      if (functions.size === 0) {
        return null;
      }

      const edits = [];

      walk(ast, (node) => {
        if (node.type !== 'CallExpression') {
          return;
        }

        const imported = functions.get(unwrapIdentifier(node.callee));

        if (!imported) {
          return;
        }

        const domainIndex = DOMAIN_ARGUMENT_INDEX[imported];
        const args = node.arguments;
        const textArgs = imported === '_n' || imported === '_nx' ? args.slice(0, 2) : args.slice(0, 1);

        if (textArgs.some((arg) => staticString(arg) === null)) {
          this.warn(`Dynamic text passed to ${imported}() is invisible to translate.wordpress.org (${id}).`, node.start);
        }

        edits.push({ start: node.callee.start, end: node.callee.end, text: `wp.i18n.${imported}` });

        const domainArg = args[domainIndex];

        if (!domainArg) {
          if (args.length !== domainIndex) {
            this.error(`${imported}() is missing arguments before the text domain (${id}).`, node.start);
          }

          edits.push({ start: args[args.length - 1].end, end: args[args.length - 1].end, text: `, "${TEXT_DOMAIN}"` });
        } else if (domainLocals.has(unwrapIdentifier(domainArg))) {
          edits.push({ start: domainArg.start, end: domainArg.end, text: `"${TEXT_DOMAIN}"` });
        } else if (staticString(domainArg) !== TEXT_DOMAIN) {
          this.error(`${imported}() must use the "${TEXT_DOMAIN}" text domain (${id}).`, domainArg.start);
        }
      });

      if (edits.length === 0) {
        return null;
      }

      let output = code;

      for (const edit of edits.sort((a, b) => b.start - a.start)) {
        output = output.slice(0, edit.start) + edit.text + output.slice(edit.end);
      }

      return { code: output, map: null };
    },
  };
}
