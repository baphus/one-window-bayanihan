const fs = require('fs');
const path = 'resources/js/Pages/Case/Create.jsx';
let t = fs.readFileSync(path, 'utf8');
const steps = [];
function rep(oldS, newS, label) {
  if (!t.includes(oldS)) { steps.push('MISS:' + label); return; }
  t = t.split(oldS).join(newS);
  steps.push('OK:' + label);
}

// A. own-client prefill: prefill own record, lock to existing
const prefillStart = t.indexOf('    useEffect(() => {\n        if (!client || clientPrefillRef.current) return;');
const prefillEndMarker = '    // Seed form state when an existing draft is loaded';
const prefillEnd = t.indexOf(prefillEndMarker);
if (prefillStart === -1 || prefillEnd === -1) {
  steps.push('MISS:prefill-block');
} else {
  const block = t.slice(prefillStart, prefillEnd);
  const replacement = `    // Own-client prefill (account-first): OFW / NEXT_OF_KIN filers arrive
    // with their linked client, so lock the form to that record.
    useEffect(() => {
        if (!client?.id || clientPrefillRef.current) return;
        clientPrefillRef.current = true;
        setClientSource('existing');
        setShowNewOfw(false);
        fillFromClient(client);
        setCurrentStep(2);
    }, [client]);

    // Shared filler for own client + picked existing client.
    function fillFromClient(picked) {
        if (!picked) return;
        setData('selected_client_id', picked.id);
        setData('client', {
            first_name: picked.first_name || '',
            last_name: picked.last_name || '',
            middle_name: picked.middle_name || '',
            suffix: picked.suffix || '',
            date_of_birth: picked.date_of_birth || '',
            sex: normalizeSex(picked.sex) || 'Male',
            email: picked.email || '',
            contact_number: picked.contact_number || '',
        });

        if (picked.addresses?.[0]) {
            const addr = picked.addresses[0];
            setData('address', {
                region: addr.region || '',
                province: addr.province || '',
                city_municipality: addr.city_municipality || '',
                barangay: addr.barangay || '',
                street: addr.street || '',
            });
        }

        if (picked.employments?.[0]) {
            setData('employment', {
                ...buildEmployment(picked.employments[0]),
                employer_name: picked.employments[0].employer_name || '',
                position: picked.employments[0].position || '',
                country: picked.employments[0].country || '',
                start_date: normalizeDateInput(picked.employments[0].start_date),
                end_date: normalizeDateInput(picked.employments[0].end_date),
                last_country: picked.employments[0].last_country || '',
                last_position: picked.employments[0].last_position || '',
                date_of_arrival: normalizeDateInput(picked.employments[0].date_of_arrival),
            });
        }

        if (picked.nextOfKin?.length) {
            setData('next_of_kin', picked.nextOfKin.map((nok) => ({
                id: nok.id,
                first_name: nok.first_name || '',
                middle_name: nok.middle_name || '',
                last_name: nok.last_name || '',
                is_primary: nok.is_primary || false,
                relationship: nok.relationship || '',
                phone_number: nok.phone_number || '',
                email: nok.email || '',
                full_address: nok.full_address || '',
                nok_address: {
                    region: nok.region || '',
                    province: nok.province || '',
                    city_municipality: nok.city_municipality || '',
                    barangay: nok.barangay || '',
                    street: nok.street || '',
                },
            })));
        }

        setSelectedClient(null);
    }

`;
  t = t.slice(0, prefillStart) + replacement + t.slice(prefillEnd);
  steps.push('OK:prefill-block');
}

// B. remove draft-resume effect entirely (from marker to its closing "}, []);")
const resumeStart = t.indexOf('    // Seed form state when an existing draft is loaded');
if (resumeStart === -1) {
  steps.push('MISS:resume-start');
} else {
  const closeIdx = t.indexOf('    }, []);', resumeStart);
  if (closeIdx === -1) { steps.push('MISS:resume-close'); }
  else {
    t = t.slice(0, resumeStart) + t.slice(closeIdx + '    }, []);'.length);
    steps.push('OK:resume-block');
  }
}

// C. save-draft + submit handlers -> direct cases.store with new_ofw support
const saveStart = t.indexOf('    function handleSaveDraft(e) {');
const confirmStart = t.indexOf('    function handleConfirmSubmit() {');
const getInitialIdx = t.indexOf('    function getInitial(name) {');
if (saveStart === -1 || confirmStart === -1 || getInitialIdx === -1) {
  steps.push('MISS:submit-block');
} else {
  const replacement = `    function handleSubmit(e) {
        e.preventDefault();
        if (processing) return;
        if (currentStep !== 3) return;
        setShowCreateModal(true);
    }

    // Account-first submit: existing pick sends selected_client_id, inline
    // "Add new OFW" sends new_ofw, own record sends nothing extra — the
    // backend falls back to the filer's linked client.
    function handleConfirmSubmit() {
        if (processing) return;
        setShowCreateModal(false);

        const payload = {};
        if (clientSource === 'existing' && data.selected_client_id) {
            payload.selected_client_id = data.selected_client_id;
        } else if (showNewOfw && isNextOfKinFiler) {
            payload.new_ofw = {
                first_name: data.client.first_name,
                last_name: data.client.last_name,
                middle_name: data.client.middle_name || null,
                suffix: data.client.suffix || null,
                date_of_birth: data.client.date_of_birth || null,
                sex: data.client.sex || null,
                email: data.client.email || null,
                contact_number: data.client.contact_number || null,
                address: data.address,
                employment: data.employment,
                next_of_kin: data.next_of_kin,
            };
        }

        post(route('cases.store'), {
            ...payload,
            preserveScroll: true,
            onError: (formErrors) => {
                const msgs = Object.values(formErrors || {});
                toast.error(msgs[0] || 'Validation failed.');
            },
        });
    }

`;
  t = t.slice(0, saveStart) + replacement + t.slice(getInitialIdx);
  steps.push('OK:submit-block');
}

fs.writeFileSync(path, t);
console.log(steps.join('\n'));
console.log('remaining existingDraft=' + (t.match(/existingDraft/g) || []).length);
console.log('remaining is_draft=' + (t.match(/is_draft/g) || []).length);
console.log('remaining confirm_duplicate=' + (t.match(/confirm_duplicate/g) || []).length);
console.log('remaining save-draft=' + (t.match(/save-draft/g) || []).length);
console.log('remaining cases.publish=' + (t.match(/cases\.publish/g) || []).length);
console.log('remaining cases.drafts=' + (t.match(/cases\.drafts/g) || []).length);
console.log('remaining hasDirty=' + (t.match(/hasDirty/g) || []).length);
console.log('remaining autoSave=' + (t.match(/autoSave/g) || []).length);
console.log('remaining UnsavedModal=' + (t.match(/UnsavedModal/g) || []).length);
console.log('remaining initialFormRef=' + (t.match(/initialFormRef/g) || []).length);
console.log('remaining getDraftCategoryIds=' + (t.match(/getDraftCategoryIds/g) || []).length);
