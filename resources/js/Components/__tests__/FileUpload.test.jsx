import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import FileUpload from '../FileUpload';

function createFile(name, type, size = 1024) {
    return new File(['x'.repeat(size)], name, { type });
}

describe('FileUpload', () => {
    it('renders with label', () => {
        render(<FileUpload label="Upload Document" onFilesSelected={() => {}} />);
        expect(screen.getByText('Upload Document')).toBeInTheDocument();
        expect(screen.getByText('Drag & drop or click to browse')).toBeInTheDocument();
    });

    it('calls onFilesSelected on file select', () => {
        const onFilesSelected = vi.fn();
        render(<FileUpload onFilesSelected={onFilesSelected} />);

        const file = createFile('test.pdf', 'application/pdf');
        const input = screen.getByLabelText('File upload drop zone').querySelector('input[type="file"]');

        fireEvent.change(input, { target: { files: [file] } });

        expect(onFilesSelected).toHaveBeenCalledTimes(1);
        expect(onFilesSelected).toHaveBeenCalledWith(file);
    });

    it('shows error for invalid MIME type', () => {
        const onError = vi.fn();
        render(
            <FileUpload
                accept="application/pdf"
                onFilesSelected={() => {}}
                onError={onError}
            />,
        );

        const exeFile = createFile('virus.exe', 'application/x-msdownload');
        const input = screen.getByLabelText('File upload drop zone').querySelector('input[type="file"]');

        fireEvent.change(input, { target: { files: [exeFile] } });

        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(onError).toHaveBeenCalledTimes(1);
        expect(onError).toHaveBeenCalledWith(
            expect.stringContaining('virus.exe'),
        );
    });

    it('shows error for invalid extension', () => {
        render(
            <FileUpload
                accept=".pdf,.doc"
                onFilesSelected={() => {}}
            />,
        );

        const exeFile = createFile('virus.exe', 'application/x-msdownload');
        const input = screen.getByLabelText('File upload drop zone').querySelector('input[type="file"]');

        fireEvent.change(input, { target: { files: [exeFile] } });

        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(screen.getByRole('alert').textContent).toContain('virus.exe');
    });

    it('shows error for oversized file', () => {
        const onError = vi.fn();
        render(
            <FileUpload
                maxSize={500}
                onFilesSelected={() => {}}
                onError={onError}
            />,
        );

        const largeFile = createFile('large.pdf', 'application/pdf', 1000);
        const input = screen.getByLabelText('File upload drop zone').querySelector('input[type="file"]');

        fireEvent.change(input, { target: { files: [largeFile] } });

        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(onError).toHaveBeenCalledWith(
            expect.stringContaining('large.pdf'),
        );
    });

    it('calls onFilesSelected with multiple files when multiple=true', () => {
        const onFilesSelected = vi.fn();
        render(<FileUpload multiple onFilesSelected={onFilesSelected} />);

        const file1 = createFile('doc1.pdf', 'application/pdf');
        const file2 = createFile('doc2.pdf', 'application/pdf');
        const input = screen.getByLabelText('File upload drop zone').querySelector('input[type="file"]');

        fireEvent.change(input, { target: { files: [file1, file2] } });

        expect(onFilesSelected).toHaveBeenCalledTimes(1);
        const selected = onFilesSelected.mock.calls[0][0];
        expect(Array.isArray(selected)).toBe(true);
        expect(selected).toHaveLength(2);
    });

    it('toggles drag-over class on dragover and dragleave', () => {
        render(<FileUpload onFilesSelected={() => {}} />);

        const dropZone = screen.getByLabelText('File upload drop zone');

        // Initially no drag-over class
        expect(dropZone.className).not.toContain('bg-indigo-50');

        // Drag over
        fireEvent.dragOver(dropZone);
        expect(dropZone.className).toContain('bg-indigo-50');
        expect(screen.getByText('Drop files here...')).toBeInTheDocument();

        // Drag leave
        fireEvent.dragLeave(dropZone);
        expect(dropZone.className).not.toContain('bg-indigo-50');
        expect(screen.getByText('Choose files')).toBeInTheDocument();
    });

    it('handles file drop', () => {
        const onFilesSelected = vi.fn();
        render(<FileUpload accept=".pdf" onFilesSelected={onFilesSelected} />);

        const dropZone = screen.getByLabelText('File upload drop zone');
        const file = createFile('test.pdf', 'application/pdf');

        fireEvent.drop(dropZone, {
            dataTransfer: { files: [file] },
        });

        expect(onFilesSelected).toHaveBeenCalledWith(file);
    });

    it('disabled prevents interaction', () => {
        const onFilesSelected = vi.fn();
        render(<FileUpload disabled onFilesSelected={onFilesSelected} />);

        const dropZone = screen.getByLabelText('File upload drop zone');

        // Click should not trigger file selection
        fireEvent.click(dropZone);
        expect(onFilesSelected).not.toHaveBeenCalled();

        // Drop should not work
        const file = createFile('test.pdf', 'application/pdf');
        fireEvent.drop(dropZone, {
            dataTransfer: { files: [file] },
        });
        expect(onFilesSelected).not.toHaveBeenCalled();

        // Should have cursor-not-allowed class
        expect(dropZone.className).toContain('cursor-not-allowed');
    });

    it('clears error after successful validation', () => {
        render(
            <FileUpload
                accept="application/pdf"
                onFilesSelected={() => {}}
            />,
        );

        const dropZone = screen.getByLabelText('File upload drop zone');
        const input = dropZone.querySelector('input[type="file"]');

        // First, trigger an error
        const exeFile = createFile('bad.exe', 'application/x-msdownload');
        fireEvent.change(input, { target: { files: [exeFile] } });
        expect(screen.getByRole('alert')).toBeInTheDocument();

        // Then, upload a valid file
        const pdfFile = createFile('good.pdf', 'application/pdf');
        fireEvent.change(input, { target: { files: [pdfFile] } });
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('has accessible attributes', () => {
        render(<FileUpload label="Upload File" onFilesSelected={() => {}} />);

        const dropZone = screen.getByLabelText('File upload drop zone');
        expect(dropZone).toHaveAttribute('role', 'button');
        expect(dropZone).toHaveAttribute('tabindex', '0');
        expect(dropZone).toHaveAttribute('aria-disabled', 'false');
    });

    it('disables drop zone and sets tabindex=-1 when disabled', () => {
        render(<FileUpload disabled onFilesSelected={() => {}} />);

        const dropZone = screen.getByLabelText('File upload drop zone');
        expect(dropZone).toHaveAttribute('tabindex', '-1');
        expect(dropZone).toHaveAttribute('aria-disabled', 'true');
    });
});
