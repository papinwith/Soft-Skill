// Fills a form's fields from a plain {name: value} object (used both by the edit-modal
// data-fill button and by re-opening a modal after a failed submit).
function fillForm(form, data) {
    Object.keys(data).forEach(function (key) {
        var value = data[key];
        if (Array.isArray(value)) {
            form.querySelectorAll('input[name="' + key + '[]"]').forEach(function (box) {
                box.checked = value.map(String).indexOf(box.value) !== -1;
            });
        } else if (form.elements[key]) {
            form.elements[key].value = value === null || value === undefined ? '' : value;
        }
    });
    var facultySelect = form.querySelector('select[name="faculty_id"]');
    if (facultySelect) facultySelect.dispatchEvent(new Event('change', { bubbles: true }));
    if (data.department_id !== undefined && form.elements['department_id']) {
        form.elements['department_id'].value = data.department_id === null ? '' : data.department_id;
    }
}

// Clears any is-invalid state / inline messages left over from a previous failed submit.
function clearFieldErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('.invalid-feedback.js-field-error').forEach(function (el) { el.remove(); });
}

// Highlights each named field in red and shows its message right under the input, so a
// failed validation points at exactly what's wrong instead of a single banner up top.
function showFieldErrors(form, errors) {
    Object.keys(errors || {}).forEach(function (key) {
        var field = form.elements[key];
        if (!field) return;
        field.classList.add('is-invalid');
        var msg = document.createElement('div');
        msg.className = 'invalid-feedback d-block js-field-error';
        msg.textContent = errors[key];
        field.insertAdjacentElement('afterend', msg);
    });
}

// Re-opens a modal after a failed submit: refills it with what the user typed (so nothing
// retyped is lost) and marks the specific field(s) that failed validation.
function reopenModalWithErrors(modalId, data, errors) {
    var modalEl = document.getElementById(modalId);
    if (!modalEl) return;
    var form = modalEl.querySelector('form');
    if (!form) return;
    clearFieldErrors(form);
    fillForm(form, data || {});
    showFieldErrors(form, errors || {});
    if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

// Fill an edit modal from the clicked button's data-fill JSON
document.addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    if (!btn || !btn.dataset.fill) return;
    var data = JSON.parse(btn.dataset.fill);
    var form = e.target.querySelector('form');
    if (!form) return;
    clearFieldErrors(form);
    fillForm(form, data);
});

// Dependent faculty -> department dropdown
document.addEventListener('change', function (e) {
    if (!e.target.matches('select[name="faculty_id"]')) return;
    var form = e.target.form;
    var dept = form.querySelector('select[name="department_id"]');
    if (!dept) return;
    var current = dept.value;
    dept.querySelectorAll('option[data-faculty]').forEach(function (opt) {
        var show = opt.dataset.faculty === e.target.value;
        opt.hidden = !show;
        opt.disabled = !show;
    });
    var selected = dept.querySelector('option[value="' + current + '"]');
    if (!selected || selected.disabled) dept.value = '';
    dept.disabled = e.target.value === '';
});
