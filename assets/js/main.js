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

    // Rebuild the activity question-row builder from data.questions (array of
    // {skill_id, text}), using the "+ เพิ่มคำถาม" button's own blank-row markup as the
    // template so it stays in sync with whatever question_row_html() rendered server-side.
    var qWrap = form.querySelector('.question-rows-wrap');
    if (qWrap && data.questions !== undefined) {
        var qContainer = qWrap.querySelector('.question-rows');
        var addBtn = qWrap.querySelector('.add-question-row');
        var template = addBtn ? addBtn.getAttribute('data-template') : '';
        qContainer.innerHTML = '';
        var rows = (data.questions && data.questions.length) ? data.questions : [null];
        rows.forEach(function (q) {
            var tmp = document.createElement('div');
            tmp.innerHTML = template;
            var rowEl = tmp.firstElementChild;
            if (q) {
                var sel = rowEl.querySelector('select[name="q_skill[]"]');
                var inp = rowEl.querySelector('input[name="q_text[]"]');
                if (sel) sel.value = q.skill_id || '';
                if (inp) inp.value = q.text || '';
            }
            qContainer.appendChild(rowEl);
        });
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

// Activity question-row builder: "+ เพิ่มคำถาม" clones the button's own template row;
// the per-row "x" removes it (or just clears it, if it's the last row left).
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('add-question-row')) {
        var wrap = e.target.closest('.question-rows-wrap');
        var container = wrap.querySelector('.question-rows');
        var tmp = document.createElement('div');
        tmp.innerHTML = e.target.getAttribute('data-template');
        container.appendChild(tmp.firstElementChild);
    } else if (e.target.classList.contains('remove-question-row')) {
        var row = e.target.closest('.question-row');
        var rows = row.parentElement;
        if (rows.children.length > 1) {
            row.remove();
        } else {
            row.querySelector('select').value = '';
            row.querySelector('input').value = '';
        }
    }
});

// Flash banners (success/error from a redirect) are one-time status messages, not a log
// readers need to keep open -- fade them out automatically after 5s (issu.md #32).
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert.alert-success, .alert.alert-danger').forEach(function (el) {
        if (el.closest('.modal')) return;
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 5000);
    });
});

// Confirm-before-submit + "analyzing" overlay for forms marked with data-confirm-submit
// (self-assessment submission, issu.md #31) -- shows a review step with a way back to
// editing, and a visible spinner while the slow AI call runs before the redirect lands.
function showAnalyzingOverlay(text) {
    var overlay = document.createElement('div');
    overlay.id = 'analyzingOverlay';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(255,255,255,0.92);z-index:2000;display:flex;flex-direction:column;align-items:center;justify-content:center;';
    overlay.innerHTML =
        '<div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status"></div>' +
        '<div class="fw-bold fs-5">' + text + '</div>';
    document.body.appendChild(overlay);
}

function showConfirmSubmitModal(form) {
    var modalEl = document.getElementById('confirmSubmitModal');
    if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = 'confirmSubmitModal';
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.innerHTML =
            '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
            '<div class="modal-header"><h5 class="modal-title"></h5></div>' +
            '<div class="modal-body"></div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">กลับไปแก้ไข</button>' +
            '<button type="button" class="btn btn-primary js-confirm-submit-ok">ยืนยันส่งคำตอบ</button>' +
            '</div></div></div>';
        document.body.appendChild(modalEl);
    }
    modalEl.querySelector('.modal-title').textContent = form.dataset.confirmSubmit || 'ยืนยันการส่ง';
    modalEl.querySelector('.modal-body').textContent = form.dataset.confirmBody || 'ยืนยันที่จะส่งข้อมูลนี้หรือไม่?';
    modalEl.querySelector('.js-confirm-submit-ok').onclick = function () {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        showAnalyzingOverlay(form.dataset.loadingText || 'กำลังดำเนินการ กรุณารอสักครู่...');
        form.dataset.confirmed = '1';
        form.submit();
    };
    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.hasAttribute('data-confirm-submit') || form.dataset.confirmed === '1') return;
    e.preventDefault();
    if (!form.reportValidity()) return;
    showConfirmSubmitModal(form);
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
