import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import WelcomeModal from '../WelcomeModal';

describe('WelcomeModal', () => {
    it('renders "Start Tour" button', () => {
        render(
            <WelcomeModal
                onStartTour={() => {}}
                onSkipTour={() => {}}
                onRemindLater={() => {}}
            />,
        );
        expect(screen.getByText('Start Tour')).toBeInTheDocument();
    });

    it('renders "Skip Tour" button', () => {
        render(
            <WelcomeModal
                onStartTour={() => {}}
                onSkipTour={() => {}}
                onRemindLater={() => {}}
            />,
        );
        expect(screen.getByText('Skip Tour')).toBeInTheDocument();
    });

    it('renders "Remind Me Later" button', () => {
        render(
            <WelcomeModal
                onStartTour={() => {}}
                onSkipTour={() => {}}
                onRemindLater={() => {}}
            />,
        );
        expect(screen.getByText('Remind Me Later')).toBeInTheDocument();
    });

    it('calls onStartTour when Start Tour is clicked', () => {
        const onStartTour = vi.fn();
        render(
            <WelcomeModal
                onStartTour={onStartTour}
                onSkipTour={() => {}}
                onRemindLater={() => {}}
            />,
        );
        fireEvent.click(screen.getByText('Start Tour'));
        expect(onStartTour).toHaveBeenCalledTimes(1);
    });

    it('calls onSkipTour when Skip Tour is clicked', () => {
        const onSkipTour = vi.fn();
        render(
            <WelcomeModal
                onStartTour={() => {}}
                onSkipTour={onSkipTour}
                onRemindLater={() => {}}
            />,
        );
        fireEvent.click(screen.getByText('Skip Tour'));
        expect(onSkipTour).toHaveBeenCalledTimes(1);
    });

    it('calls onRemindLater when Remind Me Later is clicked', () => {
        const onRemindLater = vi.fn();
        render(
            <WelcomeModal
                onStartTour={() => {}}
                onSkipTour={() => {}}
                onRemindLater={onRemindLater}
            />,
        );
        fireEvent.click(screen.getByText('Remind Me Later'));
        expect(onRemindLater).toHaveBeenCalledTimes(1);
    });
});
