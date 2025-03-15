// on ready
(function () {
    for (let lab of document.querySelectorAll('.lab')) {
        let labDescription = lab.querySelector('.lab-description');
        let display = labDescription.getAttribute('data-display');
        for (let link of lab.querySelectorAll('.lab-display-more, .lab-display-less')) {
            link.addEventListener('click', () => {
                let invertDisplay = display === 'short' ? 'full' : 'short';
                labDescription.setAttribute('data-display', invertDisplay);
                //console.log(labDescription);
                labDescription.querySelector('.short').classList.toggle('d-none');
                labDescription.querySelector('.full').classList.toggle('d-none');
                lab.querySelector('.lab-display-more').classList.toggle('d-none');
                lab.querySelector('.lab-display-less').classList.toggle('d-none');
            })
        }
    }
})()