const DOCUMENT_TYPES = ['Business Permit', 'DTI/SEC Registration', 'Tax Clearance', 'PCAB License', 'Audited Financial Statement', 'Portfolio', 'Other'];

function docRowHtml(index) {
    return `
        <div class="doc-row" data-doc-index="${index}">
            <select class="doc-type" name="documents[${index}][document_type]">
                ${DOCUMENT_TYPES.map(t => `<option value="${t}">${t}</option>`).join('')}
            </select>
            <input type="text" name="documents[${index}][title]" placeholder="Document title">
            <input type="file" name="document_files[${index}]">
            <button type="button" class="doc-row-remove" aria-label="Remove">&times;</button>
        </div>
    `;
}

const docRows = document.getElementById('docRows');
const docAddBtn = document.getElementById('docAddBtn');
let nextIndex = 1;
docRows.insertAdjacentHTML('beforeend', docRowHtml(0));
docAddBtn.addEventListener('click', () => {
    docRows.insertAdjacentHTML('beforeend', docRowHtml(nextIndex));
    nextIndex += 1;
});
docRows.addEventListener('click', (event) => {
    if (event.target.classList.contains('doc-row-remove')) {
        event.target.closest('.doc-row')?.remove();
    }
});

const applyForm = document.getElementById('applyForm');
applyForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const errorBox = document.getElementById('formError');
    errorBox.style.display = 'none';

    try {
        const res = await fetch(applyForm.dataset.apiUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('[name="_csrf"]').value },
            body: new FormData(applyForm),
        });
        const data = await res.json();
        if (!res.ok || data.error) {
            errorBox.textContent = data.error || 'Something went wrong. Please try again.';
            errorBox.style.display = 'block';
            return;
        }
        window.location.href = applyForm.dataset.redirectUrl;
    } catch (e) {
        errorBox.textContent = 'Something went wrong. Please try again.';
        errorBox.style.display = 'block';
    }
});
