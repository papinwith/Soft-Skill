// Fill an edit modal from the clicked button's data-fill JSON
document.addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    if (!btn || !btn.dataset.fill) return;
    var data = JSON.parse(btn.dataset.fill);
    var form = e.target.querySelector('form');
    if (!form) return;
    Object.keys(data).forEach(function (key) {
        var value = data[key];
        if (Array.isArray(value)) {
            form.querySelectorAll('input[name="' + key + '[]"]').forEach(function (box) {
                box.checked = value.map(String).indexOf(box.value) !== -1;
            });
        } else if (form.elements[key]) {
            form.elements[key].value = value === null ? '' : value;
        }
    });
    var facultySelect = form.querySelector('select[name="faculty_id"]');
    if (facultySelect) facultySelect.dispatchEvent(new Event('change', { bubbles: true }));
    if (data.department_id !== undefined && form.elements['department_id']) {
        form.elements['department_id'].value = data.department_id === null ? '' : data.department_id;
    }
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
