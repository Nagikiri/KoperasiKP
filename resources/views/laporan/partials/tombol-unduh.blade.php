{{-- Tombol unduh (04 R4): elemen <a> dengan kelas x-primary-button / x-secondary-button Breeze. --}}
<p>
    <a href="{{ $urlPdf }}" data-tombol-unduh="pdf"
       class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Unduh PDF</a>
    <a href="{{ $urlExcel }}" data-tombol-unduh="excel"
       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Unduh Excel</a>
</p>
