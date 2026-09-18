(() => {
    const input = document.getElementById('image');
    const preview = document.getElementById('image-preview');
    const original = preview.getAttribute('src');
    let objectUrl;
    input.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = input.files[0];
        if (file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
        } else {
            preview.hidden = !original;
            if (original) preview.src = original;
            else preview.removeAttribute('src');
        }
    });
})();
