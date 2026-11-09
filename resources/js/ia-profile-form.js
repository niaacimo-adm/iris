// Dependent dropdowns and the member total on the IA profile form.
// office -> provinces it covers -> districts of the province;  office -> assignable users.

function fill(select, items, selected, placeholder) {
    if (!select) {
        return;
    }

    select.innerHTML = '';
    select.add(new Option(placeholder, ''));

    items.forEach((item) => {
        select.add(new Option(item.name, item.id, false, String(item.id) === String(selected)));
    });
}

function initIaForm() {
    const root = document.getElementById('ia-form');

    if (!root || root.dataset.ready) {
        return;
    }

    root.dataset.ready = '1';

    const geo = JSON.parse(root.dataset.geo || '{}');
    const office = root.querySelector('[name="office_id"]');
    const province = root.querySelector('[name="province_id"]');
    const district = root.querySelector('[name="district_id"]');
    const assigned = root.querySelector('[name="assigned_user_id"]');

    const officeProvinces = (officeId) =>
        (geo.officeProvinces?.[officeId] ?? []).map((id) => ({ id, name: geo.provinces?.[id] ?? id }));

    const loadProvinces = (selected) =>
        fill(province, officeProvinces(office?.value), selected, 'Select province');

    const loadDistricts = (selected) =>
        fill(district, geo.districts?.[province?.value] ?? [], selected, 'Select district');

    const loadUsers = (selected) =>
        fill(
            assigned,
            (geo.users ?? []).filter((u) => String(u.office_id) === String(office?.value)),
            selected,
            'Not assigned'
        );

    // First fill: use the values saved earlier (edit page, or after a validation error).
    loadProvinces(province?.dataset.selected);
    loadDistricts(district?.dataset.selected);
    loadUsers(assigned?.dataset.selected);

    office?.addEventListener('change', () => {
        loadProvinces('');
        loadDistricts('');
        loadUsers('');
    });

    province?.addEventListener('change', () => loadDistricts(''));

    // Actual members = male + female, until the user types their own total.
    const male = root.querySelector('[name="male_members"]');
    const female = root.querySelector('[name="female_members"]');
    const actual = root.querySelector('[name="actual_ia_members"]');
    const sum = () => (parseInt(male?.value || '0', 10) || 0) + (parseInt(female?.value || '0', 10) || 0);

    let auto = !actual?.value || Number(actual.value) === sum();

    const recalc = () => {
        if (auto && actual && (male?.value !== '' || female?.value !== '')) {
            actual.value = sum();
        }
    };

    male?.addEventListener('input', recalc);
    female?.addEventListener('input', recalc);
    actual?.addEventListener('input', () => {
        auto = actual.value === '' || Number(actual.value) === sum();
    });
}

document.addEventListener('DOMContentLoaded', initIaForm);
document.addEventListener('livewire:navigated', initIaForm);
if (document.readyState !== 'loading') initIaForm();
