const fs = require('fs');
const path = 'resources/js/Pages/Case/Create.jsx';
let t = fs.readFileSync(path, 'utf8');
let steps = [];
function rep(oldS, newS, label) {
  if (!t.includes(oldS)) { steps.push('MISS:' + label); return; }
  t = t.split(oldS).join(newS);
  steps.push('OK:' + label);
}

// 1. remove getDraftCategoryIds helper
rep(`function getDraftCategoryIds(draft) {
    // The relation is authoritative when it is present; category_id is only a
    // compatibility fallback for older drafts.
    if (Array.isArray(draft?.categories) && draft.categories.length) {
        return normalizeCategoryIds(draft.categories);
    }
    return normalizeCategoryIds(draft?.category_ids ?? draft?.category_id ?? draft?.category);
}

// `, ``, 'rm-getDraftCategoryIds');

// 2. modal button label
rep(`{processing ? (isDraft ? 'Publishing...' : 'Creating...') : 'Confirm & Create Case'}`, `{processing ? 'Creating...' : 'Confirm & Create Case'}`, 'modal-label');
rep(`function CaseSummaryModal({ show, data, caseId, trackingId, categories, caseIssues, notificationEmail, resolvedAddress, onClose, onConfirm, processing, isDraft, nokSummary }) {`, `function CaseSummaryModal({ show, data, caseId, trackingId, categories, caseIssues, notificationEmail, resolvedAddress, onClose, onConfirm, processing, nokSummary }) {`, 'modal-sig');

// 3. next/back guards
rep(`    function handleNext() {
        if (currentStep < 3) {
            cancelPendingSave();
            if (!validateStep(currentStep)) return;`, `    function handleNext() {
        if (currentStep < 3) {
            if (!validateStep(currentStep)) return;`, 'next-guard');
rep(`    function handleBack() {
        if (currentStep > 1) {
            cancelPendingSave();
            clearErrors();`, `    function handleBack() {
        if (currentStep > 1) {
            clearErrors();`, 'back-guard');

// 4. title / header (two spots)
rep(`        <AppLayout title={existingDraft ? \`Editing Draft: \${existingDraft.case_number}\` : 'Create New Case'}>
            <Head title={existingDraft ? \`Editing Draft: \${existingDraft.case_number}\` : 'Create New Case'} />`, `        <AppLayout title="Create New Case">
            <Head title="Create New Case" />`, 'title');
rep(`                        <h1 className="text-2xl font-bold text-slate-900">{existingDraft ? \`Editing Draft: \${existingDraft.case_number}\` : 'Create New Case'}</h1>
                        <p className="text-sm text-slate-500 mt-1">{existingDraft ? 'Continue editing your draft case before submitting or publishing.' : 'A guided onboarding flow to register the case with confidence.'}</p>
                    </div>
                    <Link href={existingDraft ? route('cases.drafts') : route('cases.index')} className="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary-container transition-colors shrink-0">&larr; {existingDraft ? 'Back to Drafts' : 'Back to Cases'}</Link>`, `                        <h1 className="text-2xl font-bold text-slate-900">Create New Case</h1>
                        <p className="text-sm text-slate-500 mt-1">A guided onboarding flow to register the case with confidence.</p>
                    </div>
                    <Link href={route('cases.index')} className="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary-container transition-colors shrink-0">&larr; Back to Cases</Link>`, 'header');

// 5. local backup banner
rep(`            {hasLocalBackup && !existingDraft && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <strong>Unsaved draft found</strong> from a previous session on this device. Restore it or start fresh.
                    <div className="mt-2 flex gap-2">
                        <button type="button" onClick={handleRestoreLocalBackup} className="rounded-md bg-primary px-3 py-1.5 text-[12px] font-bold text-white hover:bg-primary-container transition-colors">Restore draft</button>
                        <button type="button" onClick={handleDiscardLocalBackup} className="rounded-md border border-amber-300 bg-white px-3 py-1.5 text-[12px] font-bold text-amber-800 hover:bg-amber-100 transition-colors">Discard</button>
                    </div>
                </div>
            )}

`, ``, 'backup-banner');

// 6. footer buttons
rep(`                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={handleSaveDraft} disabled={processing || autoSaveStatus === 'saving' || !hasDirty}
                                        className="inline-flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-5 py-2.5 text-[13px] font-bold text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">
                                        <span className="material-symbols-outlined text-[18px]">save</span>
                                        {processing ? (existingDraft ? 'Updating...' : 'Saving...') : (existingDraft ? 'Update Draft' : 'Save as Draft')}
                                    </button>
                                    {autoSaveStatus === 'saving' && (
                                        <span className="text-[12px] text-slate-500 animate-pulse">Saving...</span>
                                    )}
                                    {autoSaveStatus === 'saved' && (
                                        <span className="text-[12px] text-green-600">Draft saved</span>
                                    )}
                                    {autoSaveStatus === 'error' && (
                                        <span className="text-[12px] text-red-500">Save failed</span>
                                    )}
                                </div>`, ``, 'save-btn');
rep(`                                <button type="button" onClick={handleSubmit} disabled={processing || (existingDraft && (hasDirty || autoSaveStatus === 'saving')) || !canSubmit()}
                                    className="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-2.5 text-[13px] font-bold text-white transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">
                                    {processing ? (existingDraft ? 'Publishing...' : 'Creating...') : (existingDraft ? 'Publish Draft' : 'Create Case')}
                                </button>`, `                                <button type="button" onClick={handleSubmit} disabled={processing || !canSubmit()}
                                    className="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-2.5 text-[13px] font-bold text-white transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">
                                    {processing ? 'Creating...' : 'Create Case'}
                                </button>`, 'submit-btn');
rep(`            {UnsavedModal}
            <CaseSummaryModal`, `            <CaseSummaryModal`, 'unsaved-modal');
rep(`                processing={processing}
                isDraft={!!existingDraft}
                nokSummary={nokSummary}`, `                processing={processing}
                nokSummary={nokSummary}`, 'modal-isdraft');

fs.writeFileSync(path, t);
console.log(steps.join('\n'));
console.log('remaining existingDraft=' + (t.match(/existingDraft/g) || []).length);
