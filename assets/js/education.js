(() => {
    const forms = document.querySelectorAll('.education-ajax-form');
    const csrfToken = document.querySelector('input[name="_csrf"]')?.value;

    const updateBiodataPreview = (form) => {
        const preview = document.querySelector('[data-biodata-education]');
        if (!preview) return;
        const field = form.dataset.completionField;
        const level = field === 'elementary_school' ? 'primary' : field === 'junior_high_school' ? 'junior_high' : 'senior_high';
        const order = level === 'primary' ? 0 : level === 'junior_high' ? 1 : 2;
        const school = form.elements.namedItem(field)?.value.trim() ?? '';
        const location = form.elements.namedItem(field.replace('_school', '_location'))?.value.trim() ?? '';
        const start = form.elements.namedItem(field.replace('_school', '_year_started'))?.value ?? '';
        const endName = level === 'junior_high' ? 'junior_high_year_completed' : field.replace('_school', '_year_graduated');
        const end = form.elements.namedItem(endName)?.value ?? '';
        const honorsName = `${level === 'primary' ? 'elementary' : level === 'junior_high' ? 'junior_high' : 'senior_high'}_honors`;
        const honors = form.elements.namedItem(honorsName)?.value.trim() ?? '';
        const strand = level === 'senior_high' ? form.elements.namedItem('senior_high_strand')?.value.trim() ?? '' : '';
        const notApplicable = level === 'senior_high' && (form.elements.namedItem('senior_high_not_applicable')?.checked ?? false);
        const selectors = Array.from(preview.querySelectorAll('[data-biodata-level]'));
        let entry = preview.querySelector(`[data-biodata-level="${level}"]`);

        if (!entry && school) {
            entry = document.createElement('div');
            entry.className = 'item';
            entry.dataset.biodataLevel = level;
            entry.dataset.biodataOrder = String(order);
            const schoolNode = document.createElement('strong');
            schoolNode.dataset.biodataSchool = '';
            const labelLine = document.createElement('div');
            const labelNode = document.createElement('span');
            labelNode.dataset.biodataLabel = '';
            labelNode.textContent = level === 'primary' ? 'Primary education' : level === 'junior_high' ? 'Junior high school' : 'Senior high school';
            labelLine.append(labelNode);
            const locationLine = document.createElement('div');
            locationLine.className = 'sub';
            const locationNode = document.createElement('span');
            locationNode.dataset.biodataLocation = '';
            const yearsNode = document.createElement('span');
            yearsNode.dataset.biodataYears = '';
            locationLine.append(locationNode, yearsNode);
            const honorsNode = document.createElement('div');
            honorsNode.className = 'sub';
            honorsNode.dataset.biodataHonors = '';
            entry.append(schoolNode, labelLine, locationLine, honorsNode);
            const nextEntry = selectors.find((item) => Number(item.dataset.biodataOrder) > order);
            preview.insertBefore(entry, nextEntry || null);
        }

        if (entry) {
            entry.hidden = !school || notApplicable;
            const schoolNode = entry.querySelector('[data-biodata-school]');
            if (schoolNode) schoolNode.textContent = school;
            const locationNode = entry.querySelector('[data-biodata-location]');
            if (locationNode) locationNode.textContent = location;
            const yearsNode = entry.querySelector('[data-biodata-years]');
            if (yearsNode) yearsNode.textContent = start || end ? ` · ${start || 'Year not provided'}${end ? `–${end}` : ''}` : '';
            const honorsNode = entry.querySelector('[data-biodata-honors]');
            if (honorsNode) {
                honorsNode.textContent = honors;
                honorsNode.hidden = !honors;
            }
            if (level === 'senior_high') {
                let programNode = entry.querySelector('[data-biodata-program]');
                const labelNode = entry.querySelector('[data-biodata-label]');
                if (strand && !programNode) {
                    programNode = document.createElement('span');
                    programNode.dataset.biodataProgram = '';
                    labelNode?.after(document.createTextNode(' · '), programNode);
                } else if (!strand && programNode) {
                    programNode.previousSibling?.remove();
                    programNode.remove();
                }
                if (programNode) programNode.textContent = strand;
            }
        }

        if (level === 'senior_high') {
            let notApplicableEntry = preview.querySelector('[data-biodata-level="senior_high_na"]');
            if (!notApplicableEntry && notApplicable) {
                notApplicableEntry = document.createElement('div');
                notApplicableEntry.className = 'item';
                notApplicableEntry.dataset.biodataLevel = 'senior_high_na';
                notApplicableEntry.dataset.biodataOrder = '2';
                notApplicableEntry.innerHTML = '<strong>Senior high school</strong><div>Not applicable to this educational pathway.</div>';
                const nextEntry = selectors.find((item) => Number(item.dataset.biodataOrder) > 2);
                preview.insertBefore(notApplicableEntry, nextEntry || null);
            }
            if (notApplicableEntry) notApplicableEntry.hidden = !notApplicable;
        }
    };

    const updateCompletion = () => {
        const sections = [
            { level: 'primary', field: 'elementary_school', label: 'Primary Education' },
            { level: 'junior_high', field: 'junior_high_school', label: 'Junior High School' },
            { level: 'senior_high', field: 'senior_high_school', label: 'Senior High School' },
        ];
        let completed = 0;
        const missing = [];

        sections.forEach(({ level, field, label }) => {
            const card = document.querySelector(`[data-completion-level="${level}"]`);
            const school = card?.querySelector(`[name="${field}"]`)?.value.trim() ?? '';
            const notApplicable = level === 'senior_high' && (card?.querySelector('[name="senior_high_not_applicable"][type="checkbox"]')?.checked ?? false);
            const sectionComplete = Boolean(school || notApplicable);
            const status = card?.querySelector(`[data-education-level-status="${level}"]`);
            if (status) {
                status.classList.toggle('is-complete', sectionComplete);
                status.classList.toggle('is-optional', !sectionComplete);
                status.textContent = notApplicable ? 'Not applicable' : (school ? 'Complete' : 'Optional');
            }
            if (sectionComplete) completed += 1;
            else missing.push(label);
        });

        const tertiaryStatus = document.querySelector('[data-education-level-status="tertiary"]');
        const tertiaryComplete = tertiaryStatus?.dataset.isComplete === '1';
        if (tertiaryComplete) completed += 1;
        else missing.push('Tertiary Education');

        const count = document.querySelector('[data-education-completed-count]');
        if (count) count.textContent = String(completed);
        const percent = Math.round(completed / 4 * 100);
        const progressbar = document.querySelector('[data-education-progressbar]');
        if (progressbar) {
            progressbar.setAttribute('aria-valuenow', String(percent));
            const fill = progressbar.querySelector('span');
            if (fill) fill.style.width = `${percent}%`;
        }
        const percentage = document.querySelector('[data-education-progress-value]');
        if (percentage) percentage.textContent = `${percent}%`;
        const message = document.querySelector('[data-education-progress-message]');
        if (message) {
            message.textContent = missing.length
                ? `Still to document: ${missing.join(', ')}. Optional school stages may be left blank; mark senior high school not applicable when appropriate.`
                : 'All applicable school stages and tertiary details are documented.';
        }
    };

    const bindHistoryEditLink = (link, targetId) => {
        link.href = `#${targetId}`;
        link.addEventListener('click', () => {
            const target = document.getElementById(targetId);
            if (target instanceof HTMLDetailsElement) {
                target.open = true;
                target.querySelector('summary')?.focus();
            } else if (target instanceof HTMLButtonElement && target.matches('[data-edit-tertiary]')) {
                target.click();
            }
        });
    };

    const updateSummary = (form) => {
        const level = form.dataset.completionField;
        const school = form.elements.namedItem(level)?.value.trim() ?? '';
        const card = form.closest('[data-education-card]');
        const status = card?.querySelector('.education-level-status');
        const notApplicable = form.elements.namedItem('senior_high_not_applicable')?.checked ?? false;
        const summaryConfig = {
            elementary_school: { title: 'Primary Education', target: 'education-primary' },
            junior_high_school: { title: 'Junior High School', target: 'education-junior-high' },
            senior_high_school: { title: 'Senior High School', target: 'education-senior-high' },
        }[level];
        if (status) {
            status.classList.toggle('is-complete', Boolean(school || notApplicable));
            status.classList.toggle('is-optional', !school && !notApplicable);
            status.textContent = notApplicable ? 'Not applicable' : (school ? 'Complete' : 'Optional');
        }

        const history = document.querySelector('[data-education-history]');
        let summary = document.querySelector(`[data-education-summary="${level}"]`);
        if (!summary && school && history && summaryConfig) {
            summary = document.createElement('article');
            summary.className = 'education-summary-entry';
            summary.dataset.educationSummary = level;
            summary.innerHTML = '<span class="education-summary-level"></span><div><strong data-summary-school></strong><small data-summary-location></small></div><a class="button button-small button-quiet">Edit</a>';
            summary.querySelector('.education-summary-level').textContent = summaryConfig.title;
            const editLink = summary.querySelector('a');
            if (editLink) bindHistoryEditLink(editLink, summaryConfig.target);
            history.append(summary);
        }
        if (summary) {
            summary.hidden = !school || notApplicable;
            const text = summary.querySelector('[data-summary-school]');
            if (text) text.textContent = school;
            const location = form.elements.namedItem(level.replace('_school', '_location'))?.value.trim() ?? '';
            const start = form.elements.namedItem(level.replace('_school', '_year_started'))?.value ?? '';
            const endField = level === 'junior_high_school' ? 'junior_high_year_completed' : level.replace('_school', '_year_graduated');
            const end = form.elements.namedItem(endField)?.value ?? '';
            const subline = summary.querySelector('[data-summary-location]');
            if (subline) subline.textContent = [location, start || end ? `${start || 'Year not provided'}${end ? `–${end}` : ''}` : 'Years not provided'].filter(Boolean).join(' · ');
        }

        if (level === 'senior_high_school' && history) {
            let notApplicableEntry = history.querySelector('[data-education-summary="senior_high_na"]');
            if (!notApplicableEntry && notApplicable) {
                notApplicableEntry = document.createElement('article');
                notApplicableEntry.className = 'education-summary-entry';
                notApplicableEntry.dataset.educationSummary = 'senior_high_na';
                notApplicableEntry.innerHTML = '<span class="education-summary-level">Senior High School</span><div><strong>Not applicable</strong><small>Senior high school is not part of this educational pathway.</small></div><a class="button button-small button-quiet" href="#education-senior-high">Edit</a>';
                const editLink = notApplicableEntry.querySelector('a');
                if (editLink) bindHistoryEditLink(editLink, 'education-senior-high');
                history.append(notApplicableEntry);
            }
            if (notApplicableEntry) notApplicableEntry.hidden = !notApplicable;
        }

        updateCompletion();
        updateBiodataPreview(form);
    };

    forms.forEach((form) => {
        form.addEventListener('input', () => form.classList.add('is-dirty'));
        form.addEventListener('change', () => form.classList.add('is-dirty'));
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const status = form.querySelector('.education-form-status');
            if (!csrfToken || !button || !status) return;

            button.disabled = true;
            form.setAttribute('aria-busy', 'true');
            status.className = 'education-form-status';
            status.textContent = 'Saving…';
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error(result.error || 'Education details could not be saved.');
                }
                updateSummary(form);
                form.classList.remove('is-dirty');
                status.classList.add('is-success');
                status.textContent = result.notice || 'Educational details saved.';
            } catch (error) {
                status.classList.add('is-error');
                status.setAttribute('role', 'alert');
                status.textContent = error instanceof Error ? error.message : 'Education details could not be saved.';
            } finally {
                button.disabled = false;
                form.removeAttribute('aria-busy');
            }
        });
    });

    document.querySelectorAll('.education-record-form').forEach((form) => {
        form.addEventListener('input', () => form.classList.add('is-dirty'));
        form.addEventListener('change', () => form.classList.add('is-dirty'));
        form.addEventListener('submit', () => form.classList.remove('is-dirty'));
    });

    const tertiaryFormWrapper = document.querySelector('[data-tertiary-form-wrapper]');
    const tertiaryForm = tertiaryFormWrapper?.querySelector('[data-tertiary-form]');
    const tertiaryFormTitle = tertiaryFormWrapper?.querySelector('[data-tertiary-form-title]');
    const tertiarySaveButton = tertiaryFormWrapper?.querySelector('[data-save-tertiary-record]');
    const tertiaryAddButton = document.querySelector('[data-open-tertiary-form]');

    const openTertiaryForm = (mode, sourceForm = null) => {
        if (!tertiaryFormWrapper || !tertiaryForm || !tertiaryFormTitle || !tertiarySaveButton) return;
        tertiaryForm.reset();
        tertiaryForm.classList.remove('is-dirty');
        tertiaryForm.elements.namedItem('action').value = mode === 'edit' ? 'update_education' : 'add_education';
        tertiaryForm.elements.namedItem('record_id').value = mode === 'edit' && sourceForm ? sourceForm.dataset.recordId : '';
        if (sourceForm) {
            ['school_name', 'education_program', 'major', 'department', 'education_year', 'location', 'start_year', 'academic_status', 'expected_graduation_year', 'actual_graduation_year', 'achievements'].forEach((name) => {
                const source = sourceForm.elements.namedItem(name);
                const target = tertiaryForm.elements.namedItem(name);
                if (source && target) target.value = source.value;
            });
            const sourceCurrent = sourceForm.querySelector('input[type="checkbox"][name="is_current"]');
            const targetCurrent = tertiaryForm.querySelector('input[type="checkbox"][name="is_current"]');
            if (sourceCurrent && targetCurrent) targetCurrent.checked = sourceCurrent.checked;
        }
        tertiaryFormTitle.textContent = mode === 'edit' ? 'Edit Tertiary Record' : 'Add Tertiary Record';
        tertiarySaveButton.innerHTML = mode === 'edit'
            ? '<i class="bi bi-check-circle" aria-hidden="true"></i> Update Tertiary Record'
            : '<i class="bi bi-check-circle" aria-hidden="true"></i> Save Tertiary Record';
        tertiaryFormWrapper.hidden = false;
        const statusSelect = tertiaryForm.querySelector('[data-academic-status]');
        if (statusSelect) statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
        tertiaryFormWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
        tertiaryForm.querySelector('[name="school_name"]')?.focus({ preventScroll: true });
    };

    tertiaryAddButton?.addEventListener('click', () => openTertiaryForm('add'));
    tertiaryFormWrapper?.querySelector('[data-cancel-tertiary-form]')?.addEventListener('click', () => {
        if (!tertiaryFormWrapper || !tertiaryForm) return;
        tertiaryForm.reset();
        tertiaryForm.classList.remove('is-dirty');
        tertiaryForm.elements.namedItem('action').value = 'add_education';
        tertiaryForm.elements.namedItem('record_id').value = '';
        tertiaryFormWrapper.hidden = true;
    });
    document.querySelectorAll('[data-edit-tertiary]').forEach((button) => {
        button.addEventListener('click', () => {
            const recordId = button.dataset.editTertiary;
            const sourceForm = document.querySelector(`.education-edit-details form[data-record-id="${recordId}"]`);
            if (sourceForm instanceof HTMLFormElement) openTertiaryForm('edit', sourceForm);
        });
    });

    document.querySelectorAll('[data-academic-status]').forEach((select) => {
        const form = select.closest('form');
        const expected = form?.querySelector('[data-expected-year]');
        const actual = form?.querySelector('[data-actual-year]');
        const syncYearFields = () => {
            const graduated = select.value === 'graduated';
            if (expected) expected.hidden = graduated;
            if (actual) actual.hidden = !graduated;
            if (graduated && expected) expected.querySelector('input').value = '';
            if (!graduated && actual) actual.querySelector('input').value = '';
        };
        select.addEventListener('change', syncYearFields);
        syncYearFields();
    });

    document.querySelectorAll('.education-history-summary .education-summary-entry').forEach((entry) => {
        const label = entry.querySelector('.education-summary-level')?.textContent.trim() ?? '';
        const link = entry.querySelector('a');
        const fixedTargets = {
            'Primary Education': 'education-primary',
            'Junior High School': 'education-junior-high',
            'Senior High School': 'education-senior-high',
        };
        let targetId = fixedTargets[label] ?? '';

        if (label === 'Tertiary Education') {
            const school = entry.querySelector('[data-summary-school], div strong')?.textContent.trim() ?? '';
            const editButton = Array.from(document.querySelectorAll('.education-tertiary-section [data-edit-tertiary]'))
                .find((button) => button.closest('.education-history-card')?.querySelector('.education-history-summary strong')?.textContent.trim() === school);
            targetId = editButton?.id ?? 'education-tertiary';
        }

        if (!link || !targetId) return;
        bindHistoryEditLink(link, targetId);
    });

    window.addEventListener('beforeunload', (event) => {
        if (document.querySelector('.education-ajax-form.is-dirty, .education-record-form.is-dirty')) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
})();
