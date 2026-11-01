document.querySelectorAll('[data-reference]').forEach((input) => {
    let timer, controller;
    input.addEventListener('input', () => {
        clearTimeout(timer); controller?.abort();
        timer = setTimeout(async () => {
            const multiple = input.dataset.multipleReference === 'true';
            const parts = multiple ? input.value.split(';') : [input.value];
            const term = parts.pop().trim(); if (!term || term.length > 100) return;
            controller = new AbortController();
            try {
                const response = await fetch(`${input.dataset.reference}?q=${encodeURIComponent(term)}`, {headers:{Accept:'application/json'},signal:controller.signal});
                if (!response.ok) return;
                const data = await response.json(); const list = document.getElementById(input.getAttribute('list'));
                list.replaceChildren(...data.options.map(({code,name}) => { const option = document.createElement('option'); option.value = (multiple && parts.length ? parts.join(';')+'; ' : '')+code; option.label = name; return option; }));
            } catch { /* Typing exact reference codes remains available without suggestions. */ }
        }, 200);
    });
});
const excel = document.querySelector('[data-sheet-file]');
excel?.addEventListener('change', async () => {
    const file=excel.files?.[0], status=document.querySelector('#sheet-status'), select=document.querySelector('#sheet-index');
    select.replaceChildren(new Option('Lembar pertama','0'));
    if(!file)return;
    if(file.size>Number(excel.dataset.maxBytes)){status.textContent='Berkas melebihi batas ukuran yang ditampilkan.';return;}
    status.textContent='Membaca daftar lembar…';
    try{const {default:readExcel}=await import('read-excel-file/universal');const sheets=await readExcel(await file.arrayBuffer());if(excel.files?.[0]!==file)return;
        select.replaceChildren(...sheets.map((sheet,index)=>new Option(sheet.sheet,String(index))));status.textContent='Pilih lembar yang akan diperiksa.';
    }catch{if(excel.files?.[0]!==file)return;status.textContent='Daftar lembar belum dapat dibaca. Lembar pertama tetap dapat diperiksa oleh server.';}
});
