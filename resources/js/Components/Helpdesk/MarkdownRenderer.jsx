import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import rehypeSanitize, { defaultSchema } from 'rehype-sanitize';

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
// ponytail: hand-rolled tree walk; unist-util-visit did exactly this for one call site.
function walkHast(node, visit) {
  visit(node);
  for (const child of node?.children ?? []) walkHast(child, visit);
}

function rehypeHeadingLinks() {
  return (tree) => {
    walkHast(tree, (node) => {
      if (node?.type !== 'element') return;
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
      <div className="flex items-center justify-center py-12 text-sm text-slate-600">
        No content available.
      </div>
    );
  }

  return (
    <div className="md-renderer">
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        rehypePlugins={[[rehypeSanitize, sanitizeSchema], rehypeHeadingLinks]}
      >
        {normalizeHeadings(content)}
      </ReactMarkdown>
    </div>
  );
}
