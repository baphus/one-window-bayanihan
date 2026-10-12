const fs = require('fs');
const path = 'resources/js/Pages/Case/Create.jsx';
let t = fs.readFileSync(path, 'utf8');
const steps = [];
function rep(oldS, newS, label) {
  if (!t.includes(oldS)) { steps.push('MISS:' + label); return; }
  t = t.split(oldS).join(newS);
  steps.push('OK:' + label);
}

rep(`    const { data, setData, post, transform, processing, errors, setError, clearErrors } = useForm({`, `    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({`, 'useform-transform');
rep(`        next_of_kin: [],
        selected_nok_index: '',
        consent: false,
        selected_client_id: '',
        is_draft: false,
        case_issue_id: '',
        confirm_duplicate_client: false,
    });`, `        next_of_kin: [],
        selected_nok_index: '',
        consent: false,
        selected_client_id: '',
        case_issue_id: '',
    });`, 'useform-fields');

// handleSwitchToNew: drop snapshot refs
rep(`    function handleSwitchToNew() {
        setClientSource('new');
        setData('selected_client_id', '');
        setData('client', { first_name: '', last_name: '', middle_name: '', suffix: '', date_of_birth: '', sex: 'Male', email: '', contact_number: '' });
        setData('address', { region: '0700000000', province: '', city_municipality: '', barangay: '', street: '' });
        setData('employment', { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '', is_present: false });
        setData('next_of_kin', []);
        setData('consent', false);
        setSelectedClient(null);
        setSearchQuery('');
        setDebouncedSearch('');`, `    // "Add new OFW" inline (NEXT_OF_KIN filer): clear the pick and let the
    // Step 1 fields below become the new_ofw payload on submit.
    function handleSwitchToNew() {
        setClientSource('new');
        setShowNewOfw(true);
        setData('selected_client_id', '');
        setSelectedClient(null);
        setSearchQuery('');
        setDebouncedSearch('');`, 'switch-new-head');

// remove snapshot tail of handleSwitchToNew
rep(`        setSearchQuery('');
        setDebouncedSearch('');
        initialFormRef.current = {
                formData: {
                    client_type: 'OFW',
                    category_ids: [],
                    vulnerability_indicator: 'None',
                    nok_vulnerability_indicator: 'None',
                    summary: '',
                client: { first_name: '', last_name: '', middle_name: '', suffix: '', date_of_birth: '', sex: 'Male', email: '', contact_number: '' },
                address: { region: '0700000000', province: '', city_municipality: '', barangay: '', street: '' },
                employment: { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '', is_present: false },
                next_of_kin: [],
                selected_nok_index: '',
                selected_client_id: '',
                consent: false,
                is_draft: false,
                case_issue_id: '',
                confirm_duplicate_client: false,
            },
            clientSource: 'new',
        };
        setSavedSnapshot(initialFormRef.current);
    }

    function handleSwitchToExisting() {
        setClientSource('existing');
        clearLocalBackup();
    }`, `        setSearchQuery('');
        setDebouncedSearch('');
    }

    function handleSwitchToExisting() {
        setClientSource('existing');
        setShowNewOfw(false);
    }`, 'switch-tail');

// handleConfirmClient: replace with fillFromClient + jump to case setup
const hcStart = t.indexOf('function handleConfirmClient(client) {');
if (hcStart === -1) { steps.push('MISS:hc-start'); }
else {
  const hcEndMarker = '    setCurrentStep(2);\n}';
  const hcEnd = t.indexOf(hcEndMarker, hcStart);
  if (hcEnd === -1) { steps.push('MISS:hc-end'); }
  else {
    const replacement = `function handleConfirmClient(picked) {
    if (!picked) return;
    setClientSource('existing');
    setShowNewOfw(false);
    fillFromClient(picked);
    setCurrentStep(2);
}`;
    t = t.slice(0, hcStart) + replacement + t.slice(hcEnd + hcEndMarker.length);
    steps.push('OK:handleConfirmClient');
  }
}

fs.writeFileSync(path, t);
console.log(steps.join('\n'));
const pats = ['existingDraft','is_draft','confirm_duplicate','save-draft','cases.publish','cases.drafts','hasDirty','autoSave','UnsavedModal','initialFormRef','savedSnapshot','commitSavedSnapshot','bypassNext','clearLocalBackup','hasLocalBackup','localBackup','cancelPendingSave','getDraftCategoryIds','handleSaveDraft','handleRestoreLocalBackup','handleDiscardLocalBackup','isDraft','Update Draft','Save as Draft','Publish Draft','Back to Drafts','Unsaved draft found','transform('];
pats.forEach((p) => console.log(p + '=' + (t.match(new RegExp(p.replace(/[()]/g, (m) => '\\\\' + m)), 'g') || []).length));
