// Focused event tests; no browser or database needed.
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync('scr/assets/js/catalog-price-range.js', 'utf8');
let checks = 0;
function fixture(a, b, min = '', max = '') {
    const nodes = {};
    for (const id of ['catalog-price-range','price-low','price-high','catalog-min','catalog-max','price-low-label','price-high-label','price-range-note']) {
        nodes[id] = { value:'', style:{}, dataset:{}, events:{}, setAttribute(k,v){this[k]=v;}, addEventListener(k,fn){this.events[k]=fn;} };
    }
    nodes['catalog-price-range'].dataset = {min:a,max:b};
    nodes['catalog-min'].value=min; nodes['catalog-max'].value=max;
    vm.runInNewContext(source, {document:{getElementById:id=>nodes[id]}, Intl});
    return {nodes, move(id,value){nodes[id].value=String(value);nodes[id].events.input();}};
}
function check(value) { assert.ok(value); checks++; }
let f=fixture('100.50','3500000.25');
check(f.nodes['price-low'].min===100 && f.nodes['price-high'].max===3500001);
check(f.nodes['catalog-min'].value==='' && f.nodes['catalog-max'].value==='');
f.move('price-low',200000); f.move('price-high',900000);
check(String(f.nodes['catalog-min'].value)==='200000' && String(f.nodes['catalog-max'].value)==='900000');
check(f.nodes['price-low-label'].textContent==='200.000 ₫');
f.move('price-low',1000000);check(f.nodes['price-low'].value===f.nodes['price-high'].value);
f.move('price-high',100);check(f.nodes['price-high'].value===f.nodes['price-low'].value);
f=fixture('100','1000','300','300');check(Number(f.nodes['price-low'].value)===300 && Number(f.nodes['price-high'].value)===300);
check(f.nodes['catalog-min'].value==='300' && f.nodes['catalog-max'].value==='300');
f=fixture('100','1000','0','2000');check(f.nodes['price-low'].min===0 && f.nodes['price-high'].max===2000);
f=fixture('500','500');check(f.nodes['price-low'].disabled && f.nodes['catalog-max'].value==='');
f=fixture('','');check(f.nodes['price-low'].disabled && f.nodes['catalog-min'].value==='');
f=fixture('100','1000','bad','20');check(f.nodes['catalog-min'].value==='bad');
f.move('price-high',500);check(Number(f.nodes['price-low'].value)<=Number(f.nodes['price-high'].value));
f=fixture('100','1000'); f.move('price-low',400); f.move('price-low',100); f.move('price-high',1000);
check(f.nodes['catalog-min'].value==='' && f.nodes['catalog-max'].value==='');
console.log(`${checks}/${checks} slider checks PASS (input events, GET values, limits, empty/equal)`);
