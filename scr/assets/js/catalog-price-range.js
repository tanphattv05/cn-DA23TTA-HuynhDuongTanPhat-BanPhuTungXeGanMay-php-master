'use strict';
(() => {
    const box = document.getElementById('catalog-price-range');
    if (!box) return;
    const low = document.getElementById('price-low'), high = document.getElementById('price-high');
    const min = document.getElementById('catalog-min'), max = document.getElementById('catalog-max');
    const valid = value => /^\d{1,10}$/.test(value);
    const cap = 9999999999; // Existing GET validation limit, not a catalog price ceiling.
    let start = Math.max(0, Math.floor(Number(box.dataset.min)));
    let end = Math.min(cap, Math.ceil(Number(box.dataset.max)));
    if (valid(min.value)) start = Math.min(start, Number(min.value));
    if (valid(max.value)) end = Math.max(end, Number(max.value));
    if (valid(max.value)) start = Math.min(start, Number(max.value));
    if (valid(min.value)) end = Math.max(end, Number(min.value));
    start = Math.min(start, cap); end = Math.max(start, end);
    low.min = high.min = start; low.max = high.max = end;
    low.value = valid(min.value) ? Number(min.value) : start;
    high.value = valid(max.value) ? Number(max.value) : end;
    if (Number(low.value) > Number(high.value)) low.value = high.value;
    const empty = box.dataset.min === '' || box.dataset.max === '';
    low.disabled = high.disabled = empty || start === end;
    const format = value => new Intl.NumberFormat('vi-VN').format(Number(value)) + ' ₫';
    function draw() {
        document.getElementById('price-low-label').textContent = format(low.value);
        document.getElementById('price-high-label').textContent = format(high.value);
        low.setAttribute('aria-valuetext', format(low.value));
        high.setAttribute('aria-valuetext', format(high.value));
        // At the upper endpoint keep the lower thumb reachable, including equal prices.
        low.style.zIndex = Number(high.value) === end ? '3' : '1';
    }
    function update(which) {
        if (Number(low.value) > Number(high.value)) {
            if (which === low) low.value = high.value; else high.value = low.value;
        }
        min.value = Number(low.value) === start ? '' : low.value;
        max.value = Number(high.value) === end ? '' : high.value;
        draw();
    }
    low.addEventListener('input', () => update(low));
    high.addEventListener('input', () => update(high));
    if (empty) document.getElementById('price-range-note').textContent = 'Chưa có sản phẩm để chọn khoảng giá.';
    else if (start === end) document.getElementById('price-range-note').textContent = 'Các sản phẩm có cùng mức giá.';
    draw(); // Preserve original GET fields until the user actually moves a thumb.
})();
