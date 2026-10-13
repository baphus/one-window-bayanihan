const fs = require('fs');
const p = 'resources/js/Pages/Case/Create.jsx';
let t = fs.readFileSync(p, 'utf8');
const steps = [];
// 1. drop dead confirm_duplicate setData lines (field no longer in form)
const a1 = "                                                                setData('confirm_duplicate_client', false);";
if (t.includes(a1)) { t = t.split(a1).join(''); steps.push('OK:rm-confirm-false'); }
else steps.push('MISS:rm-confirm-false');
const a2 = "                                                                            setData('confirm_duplicate_client', true);";
if (t.includes(a2)) { t = t.split(a2).join(''); steps.push('OK:rm-confirm-true'); }
else steps.push('MISS:rm-confirm-true');
// 2. fix prefill comment (NEXT_OF_KIN never pre-fills own row)
const b1 = "    // Own-client prefill (account-first): an OFW filer arrives with their\n    // linked client, so lock the form to that record.";
if (t.includes(b1)) { t = t.split(b1).join("    // Own-client prefill: an OFW filer arrives with their linked client."); steps.push('OK:prefill-comment'); }
else steps.push('MISS:prefill-comment');
// 3. collapse triple blank line after fillFromClient
t = t.replace(/\n\n\n\n    const stepProgress/, '\n\n    const stepProgress');
fs.writeFileSync(p, t);
console.log(steps.join('\n'));
// report leftovers
for (const pat of ['confirm_duplicate', 'existingDraft', 'is_draft', 'showNewOfw', 'isNextOfKinFiler', 'Unsaved', 'autoSave', 'localBackup', 'hasDirty', 'initialFormRef', 'savedSnapshot']) {
  const c = (t.match(new RegExp(pat, 'g')) || []).length;
  console.log(pat + '=' + c);
}
