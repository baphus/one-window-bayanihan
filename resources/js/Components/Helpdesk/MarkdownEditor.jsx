import MDEditor, { commands } from '@uiw/react-md-editor';
import { useCallback, useRef, useState } from 'react';

const ICON_UPLOAD = (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
    <polyline points="17 8 12 3 7 8" />
    <line x1="12" y1="3" x2="12" y2="15" />
  </svg>
);

function createUploadCommand(uploadUrl) {
  return {
    name: 'upload-image',
    keyCommand: 'upload-image',
    buttonProps: { 'aria-label': 'Upload image', title: 'Upload image' },
    icon: ICON_UPLOAD,
    execute: (state, api) => {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/jpeg,image/png,image/gif,image/webp';
      input.multiple = false;
      input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);

        try {
          const response = await fetch(uploadUrl, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: formData,
          });

          if (!response.ok) throw new Error('Upload failed');

          const data = await response.json();
          const markdown = `![${file.name}](${data.url})`;
          api.replaceSelection(markdown);
        } catch (err) {
          console.error('Image upload failed:', err);
        }
      };
      input.click();
    },
  };
}

export default function MarkdownEditor({ value, onChange, uploadUrl }) {
  const [uploading, setUploading] = useState(false);
  const editorRef = useRef(null);
  const uploadCommand = uploadUrl ? createUploadCommand(uploadUrl) : null;

  const customCommands = uploadCommand
    ? [uploadCommand, commands.divider, ...commands.getCommands()]
    : undefined;

  const handlePaste = useCallback(async (event) => {
    if (!uploadUrl) return;

    const items = event.clipboardData?.items;
    if (!items) return;

    for (const item of items) {
      if (item.type?.startsWith('image/')) {
        event.preventDefault();
        const file = item.getAsFile();
        if (!file) continue;

        setUploading(true);
        const formData = new FormData();
        formData.append('image', file);

        try {
          const response = await fetch(uploadUrl, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: formData,
          });

          if (!response.ok) throw new Error('Upload failed');

          const data = await response.json();
          const markdown = `![${file.name}](${data.url})`;

          // Get the textarea and insert at cursor
          const textarea = editorRef.current?.querySelector('textarea');
          if (textarea) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const newValue = text.substring(0, start) + markdown + text.substring(end);
            onChange?.(newValue);
            // Restore cursor position after image markdown
            requestAnimationFrame(() => {
              const pos = start + markdown.length;
              textarea.setSelectionRange(pos, pos);
              textarea.focus();
            });
          }
        } catch (err) {
          console.error('Image paste upload failed:', err);
        } finally {
          setUploading(false);
        }
        break;
      }
    }
  }, [uploadUrl, onChange]);

  return (
    <div data-color-mode="light" ref={editorRef} onPaste={handlePaste} className="md-editor-custom relative">
      {uploading && (
        <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/70 backdrop-blur-sm">
          <div className="flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 shadow-lg border border-slate-200">
            <svg className="animate-spin h-4 w-4 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span className="text-xs font-medium text-slate-600">Uploading image...</span>
          </div>
        </div>
      )}

      <MDEditor
        value={value}
        onChange={onChange}
        height={500}
        preview="live"
        visibleDragbar={false}
        commands={customCommands}
        extraCommands={commands.getExtraCommands()}
      />

      <style>{`
        .md-editor-custom .w-md-editor {
          border-radius: 0.375rem;
          border-color: #c1c7d1;
          box-shadow: none;
          font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
        }
        .md-editor-custom .w-md-editor:hover {
          border-color: #005288;
        }
        .md-editor-custom .w-md-editor:focus-within {
          border-color: #005288;
          box-shadow: 0 0 0 1px #005288;
        }
        .md-editor-custom .w-md-editor-toolbar {
          background: #f7f9ff;
          border-bottom: 1px solid #dfe3e8;
          border-radius: 0.375rem 0.375rem 0 0;
          padding: 0.25rem 0.375rem;
          flex-wrap: wrap;
          gap: 0.125rem;
        }
        .md-editor-custom .w-md-editor-toolbar li > button {
          color: #41474f;
          border-radius: 0.25rem;
          height: 30px;
          width: 30px;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.12s ease;
        }
        .md-editor-custom .w-md-editor-toolbar li > button:hover {
          background-color: #dfe3e8;
          color: #005288;
        }
        .md-editor-custom .w-md-editor-toolbar li > button.active {
          background-color: #d0e4ff;
          color: #005288;
        }
        .md-editor-custom .w-md-editor-toolbar-divider {
          background: #dfe3e8;
          height: 20px;
          margin: 0 0.25rem;
        }
        .md-editor-custom .w-md-editor-text-input {
          font-family: 'SF Mono', 'Fira Code', 'Fira Mono', Menlo, Consolas, monospace;
          font-size: 0.875rem;
          line-height: 1.7;
          color: #181c20;
          -webkit-font-smoothing: antialiased;
        }
        .md-editor-custom .w-md-editor-text-input::placeholder {
          color: #9ca3af;
        }
        .md-editor-custom .w-md-editor-preview {
          background: #ffffff;
          border-left: 1px solid #dfe3e8;
        }
        .md-editor-custom .w-md-editor-content {
          border-radius: 0 0 0.375rem 0.375rem;
        }
        /* Sync renderer preview styles inside editor */
        .md-editor-custom .w-md-editor-preview .wmde-markdown {
          background-color: transparent !important;
          font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
          font-size: 0.9375rem;
          line-height: 1.7;
          color: #181c20;
        }
        .md-editor-custom .w-md-editor-preview .wmde-markdown h1 { font-size: 1.5rem; margin-top: 1.5em; }
        .md-editor-custom .w-md-editor-preview .wmde-markdown h2 { font-size: 1.25rem; margin-top: 1.25em; }
        .md-editor-custom .w-md-editor-preview .wmde-markdown h3 { font-size: 1.1rem; }
        .md-editor-custom .w-md-editor-preview .wmde-markdown h4 { font-size: 1rem; }
        .md-editor-custom .w-md-editor-preview .wmde-markdown p { color: #41474f; }
        .md-editor-custom .w-md-editor-preview .wmde-markdown pre {
          background: #181c20 !important;
          border-radius: 0.375rem;
          padding: 1rem 1.25rem;
        }
        .md-editor-custom .w-md-editor-preview .wmde-markdown pre code {
          background: none !important;
          color: #eef1f7;
          padding: 0;
        }
        .md-editor-custom .w-md-editor-preview .wmde-markdown code {
          background: #ebeef4;
          color: #181c20;
          padding: 0.15em 0.4em;
          border-radius: 0.25rem;
          font-size: 0.8125em;
        }
        .md-editor-custom .w-md-editor-preview .wmde-markdown a {
          color: #005288;
          text-decoration: none;
          font-weight: 500;
        }
        .md-editor-custom .w-md-editor-preview .wmde-markdown blockquote {
          border-left: 3px solid #005288;
          background: #f1f4fa;
          padding: 1em 1.25em;
          border-radius: 0 0.25rem 0.25rem 0;
        }
        .md-editor-custom .w-md-editor-preview .wmde-markdown img {
          max-width: 100%;
          border-radius: 0.375rem;
          border: 1px solid #dfe3e8;
          margin: 1em auto;
        }
      `}</style>
    </div>
  );
}
