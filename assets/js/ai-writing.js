(() => {
    const dialog = document.querySelector('[data-ai-writing-dialog]');
    if (!dialog || !window.fetch) return;

    const taskSelect = dialog.querySelector('[data-ai-writing-task]');
    const actionSelect = dialog.querySelector('[data-ai-writing-action]');
    const toneSelect = dialog.querySelector('[data-ai-writing-tone]');
    const extraDetails = dialog.querySelector('[data-ai-extra-context]');
    const consent = dialog.querySelector('[data-ai-consent]');
    const generateButton = dialog.querySelector('[data-ai-generate]');
    const preview = dialog.querySelector('[data-ai-preview]');
    const result = dialog.querySelector('[data-ai-result]');
    const status = dialog.querySelector('[data-ai-status]');
    const title = dialog.querySelector('[data-ai-writing-title]');
    let target = null;
    let inFlight = false;
    let requestSequence = 0;
    let requestController = null;

    const fieldLabels = {
        student_summary: 'Student professional summary',
        student_achievement: 'Student achievement',
        student_project_description: 'Project description',
        student_experience_description: 'Experience description',
        student_skill_description: 'Skill description',
        student_internship_objectives: 'Internship learning objectives',
        student_application_letter: 'Application letter',
        employer_company_description: 'Company description',
        employer_mission_statement: 'Proposed mission statement',
        employer_vision_statement: 'Proposed vision statement',
        employer_internship_description: 'Internship description',
        employer_internship_responsibilities: 'Suggested internship responsibilities',
        employer_internship_qualifications: 'Suggested qualifications',
        employer_preferred_skills: 'Suggested preferred skills',
        employer_learning_objectives: 'Suggested learning objectives',
        employer_training_plan: 'Suggested training plan',
    };

    const namedValue = (form, name) => {
        const element = form?.elements.namedItem(name);
        return element && typeof element.value === 'string' ? element.value.trim() : '';
    };

    const selectedContext = (field) => {
        const form = target?.closest('form');
        const context = {};
        const formContextKeys = {
            title: 'title',
            project_title: 'title',
            project_role: 'role',
            experience_title: 'title',
            technologies: 'technologies',
            organization: 'organization',
            skill_name: 'skill_name',
            proficiency: 'proficiency',
            category: 'category',
            location: 'location',
            work_mode: 'work_mode',
            program: 'program',
            year_level: 'year_level',
            requirements: 'required_skills',
        };
        for (const [name, key] of Object.entries(formContextKeys)) {
            const value = namedValue(form, name);
            if (value) context[key] = value;
        }

        if (target?.name?.endsWith('_honors')) {
            context.school_level = target.name.replace(/_honors$/, '').replaceAll('_', ' ');
        }
        try {
            const pageContext = JSON.parse(form?.dataset.aiContext || '{}');
            for (const [key, value] of Object.entries(pageContext)) {
                if (typeof value === 'string') context[key] = value;
            }
        } catch {
            status.textContent = 'The page context could not be read. You can still write your own details below.';
        }

        const additional = extraDetails.value.trim();
        if (additional) context.additional_details = additional;
        return context;
    };

    const setTasks = (element) => {
        const fields = [element.dataset.aiWriting, ...(element.dataset.aiWritingOptions || '').split(',')]
            .map(value => value.trim())
            .filter(value => fieldLabels[value]);
        const uniqueFields = [...new Set(fields)];
        taskSelect.replaceChildren(...uniqueFields.map(field => {
            const option = document.createElement('option');
            option.value = field;
            option.textContent = fieldLabels[field];
            return option;
        }));
        taskSelect.value = element.dataset.aiWriting;
    };

    const generate = async (writingAction = actionSelect.value) => {
        if (!target || inFlight) return;
        if (!consent.checked) {
            status.textContent = 'Please confirm consent before sending the selected text and details to the configured AI provider.';
            consent.focus();
            return;
        }
        const form = target.closest('form');
        const csrf = form?.querySelector('input[name="_csrf"]')?.value;
        if (!csrf) {
            status.textContent = 'A security token is missing. Refresh the page and try again.';
            return;
        }

        inFlight = true;
        const currentRequest = ++requestSequence;
        requestController = new AbortController();
        generateButton.disabled = true;
        dialog.querySelector('[data-ai-regenerate]').disabled = true;
        status.textContent = 'Preparing a draft…';
        const body = new URLSearchParams({
            _csrf: csrf,
            action: 'ai_write',
            field: taskSelect.value,
            writing_action: writingAction,
            tone: toneSelect.value,
            existing_text: target.value,
            consent: '1',
        });
        const internshipId = namedValue(form, 'internship_id');
        if (internshipId) body.set('internship_id', internshipId);
        for (const [key, value] of Object.entries(selectedContext(taskSelect.value))) {
            body.set(`context[${key}]`, value);
        }

        try {
            const response = await fetch('index.php?page=ai_write', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: body.toString(),
                credentials: 'same-origin',
                signal: requestController.signal,
            });
            let payload;
            try {
                payload = await response.json();
            } catch {
                throw new Error('The writing service returned an invalid response. Please try again.');
            }
            if (currentRequest !== requestSequence) return;
            if (!response.ok || !payload.ok || typeof payload.text !== 'string' || payload.text.trim() === '') {
                throw new Error(payload.error || 'A writing draft could not be generated.');
            }
            result.value = payload.text;
            preview.hidden = false;
            status.textContent = 'Draft ready. Review it carefully; nothing has been changed or saved.';
            dialog.querySelector('[data-ai-use]').focus();
        } catch (error) {
            if (currentRequest === requestSequence && error.name !== 'AbortError') {
                status.textContent = error instanceof Error ? error.message : 'A writing draft could not be generated.';
            }
        } finally {
            if (currentRequest === requestSequence) {
                inFlight = false;
                requestController = null;
                generateButton.disabled = false;
                dialog.querySelector('[data-ai-regenerate]').disabled = false;
            }
        }
    };

    document.querySelectorAll('textarea[name="bio"], textarea[name="achievements"], textarea[name="experience_description"]').forEach((element) => {
        if (element.name === 'bio') element.dataset.aiWriting = 'student_summary';
        if (element.name === 'achievements') element.dataset.aiWriting = 'student_achievement';
        if (element.name === 'experience_description') element.dataset.aiWriting = 'student_experience_description';
    });

    document.querySelectorAll('textarea[data-ai-writing]').forEach((element) => {
        if (element.dataset.aiWritingReady === 'true') return;
        element.dataset.aiWritingReady = 'true';
        const button = document.createElement('button');
        button.className = 'ai-writing-trigger';
        button.type = 'button';
        button.innerHTML = '<i class="bi bi-stars" aria-hidden="true"></i> Write with AI';
        button.addEventListener('click', () => {
            target = element;
            setTasks(element);
            title.textContent = `Write with AI · ${fieldLabels[taskSelect.value] || 'Writing assistant'}`;
            actionSelect.value = element.value.trim() ? 'improve' : 'generate';
            toneSelect.value = 'professional';
            extraDetails.value = '';
            consent.checked = false;
            result.value = '';
            preview.hidden = true;
            status.textContent = '';
            dialog.showModal();
            taskSelect.focus();
        });
        element.closest('label')?.after(button);
    });

    taskSelect.addEventListener('change', () => {
        title.textContent = `Write with AI · ${fieldLabels[taskSelect.value] || 'Writing assistant'}`;
        if (taskSelect.value === 'student_application_letter') {
            extraDetails.placeholder = 'Optional details about your interest or relevant experience. Do not enter contact information.';
        } else {
            extraDetails.placeholder = 'Optional factual details. Do not enter passwords, contact details, private messages, or confidential documents.';
        }
    });
    generateButton.addEventListener('click', () => generate());
    dialog.querySelector('[data-ai-regenerate]').addEventListener('click', () => generate('regenerate'));
    dialog.querySelector('[data-ai-copy]').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(result.value);
            status.textContent = 'Draft copied to the clipboard.';
        } catch {
            result.focus();
            result.select();
            status.textContent = 'Clipboard access was unavailable. Select and copy the draft manually.';
        }
    });
    dialog.querySelector('[data-ai-use]').addEventListener('click', () => {
        if (!target) return;
        target.value = result.value;
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
        dialog.close();
        target.focus();
    });
    dialog.querySelectorAll('[data-ai-cancel]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => {
        requestSequence++;
        requestController?.abort();
        requestController = null;
        target = null;
        inFlight = false;
        generateButton.disabled = false;
        dialog.querySelector('[data-ai-regenerate]').disabled = false;
    });
})();
