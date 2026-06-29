import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import ErrorBoundary from '../ErrorBoundary';

// A component that throws during render
function BuggyComponent({ shouldThrow = false }) {
  if (shouldThrow) {
    throw new Error('Test render error');
  }
  return <div>Working component</div>;
}

describe('ErrorBoundary', () => {
  beforeEach(() => {
    // Suppress console.warn from the boundary during tests
    vi.spyOn(console, 'warn').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders children when there is no error', () => {
    render(
      <ErrorBoundary>
        <div>Child content</div>
      </ErrorBoundary>,
    );

    expect(screen.getByText('Child content')).toBeInTheDocument();
  });

  it('catches a thrown error and shows fallback UI', () => {
    render(
      <ErrorBoundary>
        <BuggyComponent shouldThrow />
      </ErrorBoundary>,
    );

    expect(screen.getByText('Something went wrong')).toBeInTheDocument();
    expect(
      screen.getByText('An unexpected error occurred. Please try reloading the page.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Reload Page')).toBeInTheDocument();
  });

  it('shows incidentId when provided', () => {
    render(
      <ErrorBoundary incidentId="INC-42">
        <BuggyComponent shouldThrow />
      </ErrorBoundary>,
    );

    expect(screen.getByText('Ref: INC-42')).toBeInTheDocument();
  });

  it('calls console.warn on error', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

    render(
      <ErrorBoundary>
        <BuggyComponent shouldThrow />
      </ErrorBoundary>,
    );

    expect(warnSpy).toHaveBeenCalledWith(
      '[ErrorBoundary] Caught render error:',
      expect.any(Error),
      expect.any(Object),
    );

    warnSpy.mockRestore();
  });

  it('calls onError callback when provided', () => {
    const onError = vi.fn();

    render(
      <ErrorBoundary onError={onError}>
        <BuggyComponent shouldThrow />
      </ErrorBoundary>,
    );

    expect(onError).toHaveBeenCalledTimes(1);
    expect(onError).toHaveBeenCalledWith(
      expect.any(Error),
      expect.any(Object),
    );
  });

  it('uses custom fallback prop when provided', () => {
    render(
      <ErrorBoundary fallback={<div>Custom fallback</div>}>
        <BuggyComponent shouldThrow />
      </ErrorBoundary>,
    );

    expect(screen.getByText('Custom fallback')).toBeInTheDocument();
    expect(screen.queryByText('Something went wrong')).not.toBeInTheDocument();
  });

  it('reloads page when Reload Page button is clicked', () => {
    const originalLocation = window.location;
    delete window.location;
    window.location = { ...originalLocation, reload: vi.fn() };

    render(
      <ErrorBoundary>
        <BuggyComponent shouldThrow />
      </ErrorBoundary>,
    );

    screen.getByText('Reload Page').click();
    expect(window.location.reload).toHaveBeenCalledTimes(1);

    window.location = originalLocation;
  });

  it('does not show incidentId when not provided', () => {
    render(
      <ErrorBoundary>
        <BuggyComponent shouldThrow />
      </ErrorBoundary>,
    );

    expect(screen.queryByText(/Ref:/)).not.toBeInTheDocument();
  });
});
