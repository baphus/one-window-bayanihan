import MDEditor from '@uiw/react-md-editor';

export default function MarkdownRenderer({ content }) {
  if (!content) {
    return (
      <div className="flex items-center justify-center py-12 text-sm text-slate-400">
        No content available.
      </div>
    );
  }

  return (
    <div className="prose prose-slate max-w-none prose-headings:font-bold prose-h1:text-xl prose-h2:text-lg prose-h3:text-base prose-a:text-primary prose-a:no-underline hover:prose-a:underline prose-code:rounded prose-code:bg-slate-100 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-xs prose-pre:bg-slate-900 prose-pre:text-slate-100">
      <MDEditor.Markdown source={content} />
    </div>
  );
}
