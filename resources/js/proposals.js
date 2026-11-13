// Saran referensi kini ditangani combobox di workspace.js.
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
