let groupNameInput = document.getElementsByName('group[name]');

/** @param {HTMLInputElement} el */
groupNameInput.forEach((el) => {
    el.addEventListener("input", () => {
        let normalizedValue = el.value.replace(/[^\w\s]/gi, '');
        normalizedValue = normalizedValue.replace(/\s+/g, "-");
        normalizedValue = normalizedValue.toLowerCase();

        document.getElementsByName('group[slug]').forEach((e) => {
            e.value = normalizedValue;
        })
    });
});