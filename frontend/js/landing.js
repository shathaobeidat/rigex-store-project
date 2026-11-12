'use strict';

const cpu = document.querySelector('#build-cpu');
const gpu = document.querySelector('#build-gpu');
const ram = document.querySelector('#build-ram');
const total = document.querySelector('#build-total');
const presets = {
    ultra: ['420', '1300', '165'],
    apex: ['320', '750', '85'],
    phantom: ['250', '450', '85'],
};

function updateBuildEstimate() {
    const estimate = [cpu, gpu, ram].reduce((sum, input) => sum + Number(input.value), 0);
    total.textContent = new Intl.NumberFormat('en-JO').format(estimate);
}

[cpu, gpu, ram].forEach((input) => input.addEventListener('change', updateBuildEstimate));

document.querySelectorAll('[data-preset]').forEach((link) => {
    link.addEventListener('click', () => {
        const values = presets[link.dataset.preset];
        [cpu, gpu, ram].forEach((input, index) => {
            input.value = values[index];
        });
        updateBuildEstimate();
    });
});

updateBuildEstimate();
