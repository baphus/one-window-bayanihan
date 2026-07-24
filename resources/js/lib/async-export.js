/**
 * Dispatch an async export request and show appropriate toast feedback.
 *
 * @param {string} url - The export endpoint URL
 * @param {object} toast - Toast instance from useToast()
 * @param {object} [options] - Additional options
 * @param {function} [options.onStart] - Called when request starts
 * @param {function} [options.onDone] - Called when request completes (success or fail)
 * @returns {Promise<{id: string, status: string}|null>}
 */
export async function dispatchAsyncExport(url, toast, options = {}) {
  const { onStart, onDone } = options;

  try {
    onStart?.();

    const res = await fetch(url, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
    });

    if (!res.ok) {
      const errorData = await res.json().catch(() => null);
      throw new Error(errorData?.message || `Export request failed (${res.status})`);
    }

    const data = await res.json();

    if (data.status === 'pending') {
      toast.success("Generating your file… You'll be notified when it's ready.");
    }

    return data;
  } catch (error) {
    toast.error(error.message || 'Failed to start export. Please try again.');
    return null;
  } finally {
    onDone?.();
  }
}
