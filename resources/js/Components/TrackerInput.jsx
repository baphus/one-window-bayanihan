import AppButton from '@/Components/landing/AppButton';

export default function TrackerInput({ trackerNumber, errorMessage, onTrackerChange, onSubmit, isDisabled }) {
  return (
    <section>
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => { e.preventDefault(); onSubmit(); }}
      >
        <div className="relative">
          <input
            type="text"
            value={trackerNumber}
            onChange={(e) => onTrackerChange(e.target.value)}
            placeholder="Enter Tracking Number"
            className="w-full border border-outline bg-surface-container px-4 py-4 text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary md:px-6 md:py-5"
            aria-label="Tracker Number"
          />
        </div>

        {errorMessage && <p className="text-sm text-error font-medium">{errorMessage}</p>}

        <AppButton
          type="submit"
          disabled={isDisabled}
          variant="mint"
          icon="search"
          className="md:py-5 w-full"
        >
          Go to Tracking
        </AppButton>
      </form>
    </section>
  );
}
