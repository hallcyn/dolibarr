document.addEventListener('DOMContentLoaded',function(){
    const form=document.getElementById('indy-labels-form');if(!form)return;
    const select=document.getElementById('thirdparties'),start=document.getElementById('start'),count=document.getElementById('indy-labels-count');
    if(!select)return;
    function update(){const n=Array.from(select.selectedOptions).filter(o=>Number(o.value)>0).length;count.textContent=n+' tiers · '+(n?Math.ceil((n+Number(start.value)-1)/16):0)+' page(s)';}
    function changed(){if(window.jQuery)window.jQuery(select).trigger('change');else select.dispatchEvent(new Event('change'));update();}
    document.getElementById('indy-labels-all').addEventListener('click',()=>{Array.from(select.options).forEach(o=>o.selected=Number(o.value)>0);changed();});
    document.getElementById('indy-labels-clear').addEventListener('click',()=>{Array.from(select.options).forEach(o=>o.selected=false);changed();});
    select.addEventListener('change',update);start.addEventListener('change',update);if(window.jQuery)window.jQuery(select).on('change',update);update();
});
