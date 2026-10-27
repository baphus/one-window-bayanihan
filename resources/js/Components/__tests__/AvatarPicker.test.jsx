import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

// CropImageModal pulls in react-easy-crop and canvas APIs that jsdom does not
// implement. The picker's contract is "hand the cropped File to the parent",
// so stub the modal and drive onCropComplete directly.
vi.mock('@/Components/CropImageModal', () => ({
    default: ({ open, onCropComplete, onCancel }) => (
        open ? (
            <div data-testid="crop-modal">
                <button
                    type="button"
                    onClick={() => onCropComplete(new File(['cropped'], 'cropped.jpg', { type: 'image/jpeg' }))}
                >
                    crop-confirm
                </button>
                <button type="button" onClick={onCancel}>crop-cancel</button>
            </div>
        ) : null
    ),
}));

import AvatarPicker from '../AvatarPicker';

function pickFile(container, file) {
    const input = container.querySelector('input[type="file"]');
    fireEvent.change(input, { target: { files: [file] } });
    return input;
}

describe('AvatarPicker', () => {
    beforeEach(() => {
        global.URL.createObjectURL = vi.fn(() => 'blob:preview');
        global.URL.revokeObjectURL = vi.fn();
    });

    it('opens the cropper when a file is selected instead of uploading immediately', () => {
        const onFileChange = vi.fn();
        const { container } = render(<AvatarPicker file={null} onFileChange={onFileChange} name="Juan" />);

        const file = new File(['raw'], 'photo.png', { type: 'image/png' });
        pickFile(container, file);

        expect(screen.getByTestId('crop-modal')).toBeInTheDocument();
        // The raw file must not be handed to the parent until it is cropped.
        expect(onFileChange).not.toHaveBeenCalled();
    });

    it('reports the cropped file to the parent', () => {
        const onFileChange = vi.fn();
        const { container } = render(<AvatarPicker file={null} onFileChange={onFileChange} name="Juan" />);

        pickFile(container, new File(['raw'], 'photo.png', { type: 'image/png' }));
        fireEvent.click(screen.getByText('crop-confirm'));

        expect(onFileChange).toHaveBeenCalledTimes(1);
        const received = onFileChange.mock.calls[0][0];
        expect(received).toBeInstanceOf(File);
        expect(received.name).toBe('cropped.jpg');
    });

    it('does not select anything when the cropper is cancelled', () => {
        const onFileChange = vi.fn();
        const { container } = render(<AvatarPicker file={null} onFileChange={onFileChange} name="Juan" />);

        pickFile(container, new File(['raw'], 'photo.png', { type: 'image/png' }));
        fireEvent.click(screen.getByText('crop-cancel'));

        expect(screen.queryByTestId('crop-modal')).not.toBeInTheDocument();
        expect(onFileChange).not.toHaveBeenCalled();
    });

    it('clears the selection when Remove is pressed', () => {
        const onFileChange = vi.fn();
        const { container } = render(<AvatarPicker file={null} onFileChange={onFileChange} name="Juan" />);

        pickFile(container, new File(['raw'], 'photo.png', { type: 'image/png' }));
        fireEvent.click(screen.getByText('crop-confirm'));
        expect(onFileChange).toHaveBeenLastCalledWith(expect.any(File));

        fireEvent.click(screen.getByText('Remove'));

        expect(onFileChange).toHaveBeenLastCalledWith(null);
    });

    it('shows the validation error passed by the parent', () => {
        render(<AvatarPicker file={null} onFileChange={vi.fn()} name="Juan" error="Profile picture must be at least 32x32 pixels." />);

        expect(screen.getByText('Profile picture must be at least 32x32 pixels.')).toBeInTheDocument();
    });

    it('does not open the file dialog while disabled', () => {
        const { container } = render(<AvatarPicker file={null} onFileChange={vi.fn()} name="Juan" disabled />);

        expect(container.querySelector('input[type="file"]')).toBeDisabled();
    });
});
