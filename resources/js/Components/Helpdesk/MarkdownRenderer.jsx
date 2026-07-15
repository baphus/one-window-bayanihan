import MDEditor from '@uiw/react-md-editor';
import rehypeSanitize, { defaultSchema } from 'rehype-sanitize';
import { visit } from 'unist-util-visit';

// Extend the default sanitize schema to allow aria-hidden and tabIndex on
// anchor elements so our custom plugin's attributes survive sanitization.
const sanitizeSchema = {
  ...defaultSchema,
  attributes: {
    ...defaultSchema.attributes,
    a: [...(defaultSchema.attributes?.a || []), 'ariaHidden', 'tabIndex'],
  },
};

// Rehype plugin: mark heading anchor links as aria-hidden so axe does not
// flag them for missing accessible names (the heading text itself provides
// the accessible name for the section).
function rehypeHeadingLinks() {
  return (tree) => {
    visit(tree, 'element', (node) => {
      if (
        node.tagName === 'a' &&
        node.properties?.href?.startsWith('#') &&
        (!node.children ||
          node.children.length === 0 ||
          (node.children.length === 1 &&
            node.children[0].type === 'text' &&
            node.children[0].value.trim() === ''))
      ) {
        node.properties['ariaHidden'] = 'true';
        node.properties.tabIndex = -1;
      }
    });
  };
}

// The article page renders its own h1 (the article title), so the markdown
// body must not introduce another one: drop a leading `# Title` line (it
// duplicates the title) and demote any other `# ` heading to `## `.
// Fenced code blocks are left untouched.
export function normalizeHeadings(markdown) {
  let inFence = false;
  let leadingH1Dropped = false;
  let seenContent = false;

  return markdown
    .split('\n')
    .map((line) => {
      if (/^\s*(```|~~~)/.test(line)) {
        inFence = !inFence;
        seenContent = true;
        return line;
      }
      if (inFence) return line;

      if (/^#\s/.test(line)) {
        if (!seenContent && !leadingH1Dropped) {
          leadingH1Dropped = true;
          return null;
        }
        return '#' + line;
      }

      if (line.trim() !== '') seenContent = true;
      return line;
    })
    .filter((line) => line !== null)
    .join('\n');
}

export default function MarkdownRenderer({ content }) {
  if (!content) {
    return (
      <div className="flex items-center justify-center py-12 text-sm text-slate-400">
        No content available.
      </div>
    );
  }

  return (
    <div className="md-renderer">
      <MDEditor.Markdown
        source={normalizeHeadings(content)}
        rehypePlugins={[[rehypeSanitize, sanitizeSchema], rehypeHeadingLinks]}
        wrapperElement={{
          'data-color-mode': 'light',
        }}
      />
      <style>{`
        .md-renderer .wmde-markdown,
        .md-renderer .wmde-markdown-color {
          background-color: transparent !important;
          font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
          font-size: 1rem;
          line-height: 1.75;
          color: #181c20;
        }

        /* Headings */
        .md-renderer .wmde-markdown h1,
        .md-renderer .wmde-markdown h2,
        .md-renderer .wmde-markdown h3,
        .md-renderer .wmde-markdown h4,
        .md-renderer .wmde-markdown h5,
        .md-renderer .wmde-markdown h6 {
          font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
          font-weight: 700;
          letter-spacing: -0.02em;
          color: #181c20;
          margin-top: 2em;
          margin-bottom: 0.75em;
          line-height: 1.3;
        }
        .md-renderer .wmde-markdown h1 { font-size: 1.625rem; }
        .md-renderer .wmde-markdown h2 { font-size: 1.375rem; }
        .md-renderer .wmde-markdown h3 { font-size: 1.15rem; }
        .md-renderer .wmde-markdown h4 { font-size: 1rem; }
        .md-renderer .wmde-markdown h5 { font-size: 0.875rem; }
        .md-renderer .wmde-markdown h6 { font-size: 0.8125rem; }

        .md-renderer .wmde-markdown h1:first-child,
        .md-renderer .wmde-markdown h2:first-child,
        .md-renderer .wmde-markdown h3:first-child {
          margin-top: 0;
        }

        /* Paragraphs */
        .md-renderer .wmde-markdown p {
          margin-bottom: 1.25em;
          line-height: 1.75;
          color: #41474f;
        }

        /* Links */
        .md-renderer .wmde-markdown a {
          color: #005288;
          text-decoration: none;
          font-weight: 500;
          border-bottom: 1px solid transparent;
          transition: border-color 0.15s ease;
        }
        .md-renderer .wmde-markdown a:hover {
          border-bottom-color: #005288;
        }

        /* Inline code */
        .md-renderer .wmde-markdown code {
          font-family: 'SF Mono', 'Fira Code', 'Fira Mono', Menlo, Consolas, monospace;
          font-size: 0.8125em;
          background-color: #ebeef4;
          color: #181c20;
          padding: 0.15em 0.45em;
          border-radius: 0.25rem;
          font-weight: 500;
        }

        /* Code blocks */
        .md-renderer .wmde-markdown pre {
          background-color: #181c20 !important;
          border-radius: 0.5rem;
          padding: 1.25rem 1.5rem;
          margin-bottom: 1.5em;
          overflow-x: auto;
          border: 1px solid #2d3135;
        }
        .md-renderer .wmde-markdown pre code {
          background: none !important;
          color: #eef1f7;
          padding: 0;
          font-size: 0.8125rem;
          line-height: 1.6;
          font-weight: 400;
        }

        /* Blockquotes */
        .md-renderer .wmde-markdown blockquote {
          border-left: 3px solid #005288;
          background-color: #f1f4fa;
          margin: 1.5em 0;
          padding: 1em 1.5em;
          border-radius: 0 0.25rem 0.25rem 0;
          color: #41474f;
        }
        .md-renderer .wmde-markdown blockquote p:last-child {
          margin-bottom: 0;
        }

        /* Lists */
        .md-renderer .wmde-markdown ul,
        .md-renderer .wmde-markdown ol {
          padding-left: 1.5em;
          margin-bottom: 1.25em;
          color: #41474f;
        }
        .md-renderer .wmde-markdown li {
          margin-bottom: 0.35em;
          line-height: 1.7;
        }
        .md-renderer .wmde-markdown li > ul,
        .md-renderer .wmde-markdown li > ol {
          margin-top: 0.35em;
          margin-bottom: 0;
        }

        /* Task lists */
        .md-renderer .wmde-markdown .contains-task-list {
          list-style: none;
          padding-left: 0;
        }
        .md-renderer .wmde-markdown .contains-task-list .task-list-item {
          display: flex;
          align-items: flex-start;
          gap: 0.5em;
        }
        .md-renderer .wmde-markdown .contains-task-list .task-list-item input[type="checkbox"] {
          margin-top: 0.35em;
          accent-color: #005288;
        }

        /* Horizontal rules */
        .md-renderer .wmde-markdown hr {
          border: none;
          height: 1px;
          background: #dfe3e8;
          margin: 2.5em 0;
        }

        /* Images */
        .md-renderer .wmde-markdown img {
          max-width: 100%;
          height: auto;
          border-radius: 0.5rem;
          border: 1px solid #dfe3e8;
          display: block;
          margin: 1.5em auto;
          box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        /* Tables */
        .md-renderer .wmde-markdown table {
          width: 100%;
          border-collapse: collapse;
          margin-bottom: 1.5em;
          font-size: 0.875rem;
        }
        .md-renderer .wmde-markdown th {
          background-color: #f1f4fa;
          font-weight: 600;
          text-align: left;
          padding: 0.625rem 0.75rem;
          border: 1px solid #dfe3e8;
          color: #181c20;
          font-size: 0.8125rem;
          text-transform: uppercase;
          letter-spacing: 0.04em;
        }
        .md-renderer .wmde-markdown td {
          padding: 0.5rem 0.75rem;
          border: 1px solid #dfe3e8;
          color: #41474f;
        }
        .md-renderer .wmde-markdown tr:nth-child(even) td {
          background-color: #f7f9ff;
        }

        /* Bold / Strong */
        .md-renderer .wmde-markdown strong {
          font-weight: 700;
          color: #181c20;
        }

        /* Inline images shouldn't have borders in flow text */
        .md-renderer .wmde-markdown p img {
          display: inline-block;
          margin: 0 0.25em;
          border: none;
          box-shadow: none;
          vertical-align: middle;
        }

        /* Alerts (GitHub blockquote alerts) */
        .md-renderer .wmde-markdown .markdown-alert {
          border-left: 3px solid #005288;
          background: #f1f4fa;
          border-radius: 0 0.25rem 0.25rem 0;
          padding: 1em 1.25em;
          margin: 1.5em 0;
        }
        .md-renderer .wmde-markdown .markdown-alert-title {
          font-weight: 700;
          font-size: 0.8125rem;
          text-transform: uppercase;
          letter-spacing: 0.04em;
          margin-bottom: 0.25em;
        }

        /* Paragraph spacing after block elements */
        .md-renderer .wmde-markdown h1 + p,
        .md-renderer .wmde-markdown h2 + p,
        .md-renderer .wmde-markdown h3 + p,
        .md-renderer .wmde-markdown h4 + p {
          margin-top: 0;
        }
      `}</style>
    </div>
  );
}
