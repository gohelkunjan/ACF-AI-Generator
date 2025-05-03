// admin-script.js
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#acf-ai-generator-form');
    const textarea = document.querySelector('#acf_prompt');
    const submitButton = document.querySelector('#generate_acf_code');

    if (form && textarea && submitButton) {
        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.value = 'Generating...';
        });
    }
});
