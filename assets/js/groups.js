let groupNameInput = document.getElementsByName('group[name]');

/** @param {HTMLInputElement} el */
groupNameInput.forEach((el) => {
    el.addEventListener("input", () => {
        let normalizedValue = removeAccent(el.value);
        normalizedValue = normalizedValue.replace(/[^\w\s]/gi, '');
        normalizedValue = normalizedValue.replace(/\s+/g, "-");
        normalizedValue = normalizedValue.toLowerCase();

        document.getElementsByName('group[slug]').forEach((e) => {
            e.value = normalizedValue;
        })
    });
});

function removeAccent(text) {
    
    var accentLetters    = 'ÀÁÂÃÄÅàáâãäåÒÓÔÕÕÖØòóôõöøÈÉÊËèéêëðÇçÐÌÍÎÏìíîïÙÚÛÜùúûüÑñŠšŸÿýŽž';
    var plainLetters = "AAAAAAaaaaaaOOOOOOOooooooEEEEeeeeeCcDIIIIiiiiUUUUuuuuNnSsYyyZz";

    var newText = '';
    var transformed = false;

    for(i in text) {
        for(j in accentLetters) {
            if (text[i] == accentLetters[j]) {
                let letter = plainLetters[j];
                newText += letter;
                transformed = true;
                break;
            }
        }
        if (transformed == false) {
            newText += text[i];
        }
    }

    return newText;
}