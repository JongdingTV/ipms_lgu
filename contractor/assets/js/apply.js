/* ============================================================
   contractor/assets/js/apply.js — multi-step "Become a Contractor"
   wizard: step navigation + validation, drag-and-drop required
   document uploads, optional document rows, a review step, and a
   localStorage draft autosave (text fields only — files can't be
   restored from localStorage, so those must be re-attached).
   ============================================================ */

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[ch]));
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

/* ---- Required document dropzones (fixed indices 0-4) ---- */
const REQUIRED_DOCS = [
    { type: 'DTI/SEC Registration', label: 'DTI or SEC Registration', hint: 'Proof of legal business registration' },
    { type: 'Business Permit', label: "Mayor's / Business Permit", hint: 'Current year permit' },
    { type: 'Tax Clearance', label: 'Tax Clearance Certificate', hint: 'From the BIR, for bidding purposes' },
    { type: 'PCAB License', label: 'PCAB License', hint: 'Must match your license number above' },
    { type: 'Audited Financial Statement', label: 'Audited Financial Statement', hint: 'Most recent fiscal year, BIR-stamped' },
];

const uploadIconSvg = '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.25 13.25a.75.75 0 001.5 0V4.636l2.955 3.129a.75.75 0 001.09-1.03l-4.25-4.5a.75.75 0 00-1.09 0l-4.25 4.5a.75.75 0 101.09 1.03L9.25 4.636v8.614z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>';

function dropzoneHtml(index, doc) {
    return `
        <label class="dropzone" data-index="${index}">
            <input type="file" name="document_files[${index}]" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
            <input type="hidden" name="documents[${index}][document_type]" value="${doc.type}">
            <input type="hidden" name="documents[${index}][title]" value="${doc.label}">
            <span class="dropzone-icon">${uploadIconSvg}</span>
            <span class="dropzone-body">
                <span class="dropzone-title">${doc.label} <span class="required">*</span></span>
                <span class="dropzone-sub">${doc.hint}</span>
                <span class="dropzone-filename"></span>
            </span>
            <button type="button" class="dropzone-remove" aria-label="Remove file">&times;</button>
        </label>
    `;
}

function wireDropzone(zone) {
    const fileInput = zone.querySelector('input[type="file"]');
    const filenameEl = zone.querySelector('.dropzone-filename');
    const removeBtn = zone.querySelector('.dropzone-remove');

    function updateVisual() {
        const file = fileInput.files && fileInput.files[0];
        if (file) {
            zone.classList.add('has-file');
            zone.classList.remove('is-invalid');
            filenameEl.textContent = `${file.name} (${formatFileSize(file.size)})`;
        } else {
            zone.classList.remove('has-file');
            filenameEl.textContent = '';
        }
    }

    fileInput.addEventListener('change', updateVisual);

    zone.addEventListener('dragover', (event) => {
        event.preventDefault();
        zone.classList.add('is-dragover');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        zone.classList.remove('is-dragover');
        if (event.dataTransfer.files && event.dataTransfer.files.length) {
            fileInput.files = event.dataTransfer.files;
            updateVisual();
        }
    });

    removeBtn.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        fileInput.value = '';
        updateVisual();
    });
}

const requiredContainer = document.getElementById('requiredDropzones');
REQUIRED_DOCS.forEach((doc, index) => {
    requiredContainer.insertAdjacentHTML('beforeend', dropzoneHtml(index, doc));
});
requiredContainer.querySelectorAll('.dropzone').forEach(wireDropzone);

/* ---- Optional document rows (indices start after the required docs) ---- */
const OPTIONAL_DOCUMENT_TYPES = ['Portfolio', 'Other'];
const docRows = document.getElementById('docRows');
const docAddBtn = document.getElementById('docAddBtn');
let nextOptionalIndex = REQUIRED_DOCS.length;

function docRowHtml(index) {
    return `
        <div class="doc-row" data-doc-index="${index}">
            <select class="doc-type" name="documents[${index}][document_type]">
                ${OPTIONAL_DOCUMENT_TYPES.map(t => `<option value="${t}">${t}</option>`).join('')}
            </select>
            <input type="text" name="documents[${index}][title]" placeholder="Document title">
            <input type="file" name="document_files[${index}]">
            <button type="button" class="doc-row-remove" aria-label="Remove">&times;</button>
        </div>
    `;
}

docAddBtn.addEventListener('click', () => {
    docRows.insertAdjacentHTML('beforeend', docRowHtml(nextOptionalIndex));
    nextOptionalIndex += 1;
});
docRows.addEventListener('click', (event) => {
    if (event.target.classList.contains('doc-row-remove')) {
        event.target.closest('.doc-row')?.remove();
    }
});

/* ---- Step navigation ---- */
const TOTAL_STEPS = 4;
let currentStep = 1;
const stepEls = document.querySelectorAll('.step');
const stepperItems = document.querySelectorAll('.stepper-item');
const backBtn = document.getElementById('stepBackBtn');
const nextBtn = document.getElementById('stepNextBtn');
const submitBtn = document.getElementById('stepSubmitBtn');
const formErrorBox = document.getElementById('formError');

function showStep(n) {
    currentStep = n;
    stepEls.forEach((el) => el.classList.toggle('is-active', Number(el.dataset.step) === n));
    stepperItems.forEach((item) => {
        const s = Number(item.dataset.step);
        item.classList.toggle('is-active', s === n);
        item.classList.toggle('is-done', s < n);
    });
    backBtn.style.visibility = n === 1 ? 'hidden' : 'visible';
    nextBtn.style.display = n === TOTAL_STEPS ? 'none' : 'inline-block';
    submitBtn.style.display = n === TOTAL_STEPS ? 'inline-block' : 'none';
    formErrorBox.style.display = 'none';
    if (n === TOTAL_STEPS) renderReview();
    document.querySelector('.apply-main-inner')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function validateStep(n) {
    const stepEl = document.querySelector(`.step[data-step="${n}"]`);
    let valid = true;
    let firstInvalid = null;

    stepEl.querySelectorAll('input[required], select[required]').forEach((input) => {
        const group = input.closest('.form-group');
        const ok = input.checkValidity();
        if (group) {
            group.classList.toggle('is-invalid', !ok);
            group.classList.toggle('is-valid', ok && input.value.trim() !== '');
        }
        if (!ok) {
            valid = false;
            firstInvalid = firstInvalid || input;
        }
    });

    if (n === 3) {
        requiredContainer.querySelectorAll('.dropzone').forEach((zone) => {
            const fileInput = zone.querySelector('input[type="file"]');
            const ok = fileInput.files && fileInput.files.length > 0;
            zone.classList.toggle('is-invalid', !ok);
            if (!ok) {
                valid = false;
                firstInvalid = firstInvalid || zone;
            }
        });
    }

    if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return valid;
}

nextBtn.addEventListener('click', () => {
    if (!validateStep(currentStep)) return;
    if (currentStep < TOTAL_STEPS) showStep(currentStep + 1);
});
backBtn.addEventListener('click', () => {
    if (currentStep > 1) showStep(currentStep - 1);
});
stepperItems.forEach((item) => {
    item.style.cursor = 'pointer';
    item.addEventListener('click', () => {
        const target = Number(item.dataset.step);
        if (target < currentStep) showStep(target);
    });
});

/* ---- Review step ---- */
function fieldValue(name) {
    const el = document.querySelector(`[name="${name}"]`);
    return el ? el.value : '';
}

function renderReview() {
    const docItems = [];
    requiredContainer.querySelectorAll('.dropzone').forEach((zone) => {
        const label = zone.querySelector('.dropzone-title').textContent.replace('*', '').trim();
        const file = zone.querySelector('input[type="file"]').files[0];
        docItems.push({ label, filename: file ? file.name : 'Not attached yet' });
    });
    docRows.querySelectorAll('.doc-row').forEach((row) => {
        const type = row.querySelector('.doc-type').value;
        const title = row.querySelector('input[type="text"]').value || type;
        const file = row.querySelector('input[type="file"]').files[0];
        if (file || row.querySelector('input[type="text"]').value) {
            docItems.push({ label: title, filename: file ? file.name : 'No file attached' });
        }
    });

    const docsHtml = docItems.map((d) => `
        <li><span class="doc-name">${escapeHtml(d.label)}</span><span class="doc-file">${escapeHtml(d.filename)}</span></li>
    `).join('');

    document.getElementById('reviewContent').innerHTML = `
        <div class="review-group">
            <h3>Company Details <button type="button" class="review-edit" data-jump="1">Edit</button></h3>
            <dl class="review-grid">
                <dt>Company Name</dt><dd>${escapeHtml(fieldValue('name'))}</dd>
                <dt>Contact Person</dt><dd>${escapeHtml(fieldValue('contact_person') || '—')}</dd>
                <dt>Email</dt><dd>${escapeHtml(fieldValue('email'))}</dd>
                <dt>Phone</dt><dd>${escapeHtml(fieldValue('phone') || '—')}</dd>
                <dt>Address</dt><dd>${escapeHtml(fieldValue('address') || '—')}</dd>
            </dl>
        </div>
        <div class="review-group">
            <h3>Legal &amp; PCAB <button type="button" class="review-edit" data-jump="2">Edit</button></h3>
            <dl class="review-grid">
                <dt>PCAB License No.</dt><dd>${escapeHtml(fieldValue('pcab_license_no'))}</dd>
                <dt>Classification</dt><dd>${escapeHtml(fieldValue('pcab_classification'))}</dd>
            </dl>
        </div>
        <div class="review-group">
            <h3>Documents <button type="button" class="review-edit" data-jump="3">Edit</button></h3>
            <ul class="review-docs">${docsHtml}</ul>
        </div>
    `;

    document.querySelectorAll('.review-edit').forEach((btn) => {
        btn.addEventListener('click', () => showStep(Number(btn.dataset.jump)));
    });
}

/* ---- Draft autosave (text fields only — files can't survive localStorage) ---- */
const DRAFT_KEY = 'contractor_apply_draft_v1';
const DRAFT_FIELDS = ['name', 'contact_person', 'email', 'phone', 'address', 'pcab_license_no', 'pcab_classification'];

function saveDraft() {
    const data = {};
    DRAFT_FIELDS.forEach((f) => {
        data[f] = fieldValue(f);
    });
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(data)); } catch (e) { /* ignore */ }
}

function clearDraft() {
    try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* ignore */ }
}

function loadDraft() {
    let data = null;
    try { data = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { data = null; }
    if (!data) return;
    let restored = false;
    DRAFT_FIELDS.forEach((f) => {
        const el = document.querySelector(`[name="${f}"]`);
        if (el && data[f]) {
            el.value = data[f];
            restored = true;
        }
    });
    if (restored) document.getElementById('draftBanner').style.display = 'flex';
}

DRAFT_FIELDS.forEach((f) => {
    const el = document.querySelector(`[name="${f}"]`);
    el?.addEventListener('input', saveDraft);
    el?.addEventListener('change', saveDraft);
});

document.getElementById('draftClear')?.addEventListener('click', () => {
    DRAFT_FIELDS.forEach((f) => {
        const el = document.querySelector(`[name="${f}"]`);
        if (el) el.value = '';
    });
    clearDraft();
    document.getElementById('draftBanner').style.display = 'none';
});

loadDraft();

/* ---- Submit ---- */
const applyForm = document.getElementById('applyForm');
applyForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    formErrorBox.style.display = 'none';

    if (!validateStep(1) || !validateStep(2) || !validateStep(3)) {
        formErrorBox.textContent = 'Please complete all required fields before submitting.';
        formErrorBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting…';

    try {
        const res = await fetch(applyForm.dataset.apiUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('[name="_csrf"]').value },
            body: new FormData(applyForm),
        });
        const data = await res.json();
        if (!res.ok || data.error) {
            formErrorBox.textContent = data.error || 'Something went wrong. Please try again.';
            formErrorBox.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Application';
            return;
        }
        clearDraft();
        window.location.href = applyForm.dataset.redirectUrl;
    } catch (e) {
        formErrorBox.textContent = 'Something went wrong. Please try again.';
        formErrorBox.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Application';
    }
});
