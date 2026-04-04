document.addEventListener('DOMContentLoaded', () => {
    const bufferModeSelect = document.getElementById('buffer_mode');

    if (bufferModeSelect) {
        bufferModeSelect.addEventListener('change', (e) => {
            const dateWrapper = document.getElementById('buffer_date_wrapper');

            if (e.target.value === 'customScheduled') {
                dateWrapper.style.display = 'block';
            } else {
                dateWrapper.style.display = 'none';
            }
        });
    }
});