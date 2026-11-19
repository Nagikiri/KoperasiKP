{{-- Tombol unduh (04 R4, F5 C): dua x-ui.button secondary berikon, di actions header. Jangkar test: data-tombol-unduh. --}}
<x-ui.button variant="secondary" :href="$urlPdf" icon="document-arrow-down" data-tombol-unduh="pdf">Unduh PDF</x-ui.button>
<x-ui.button variant="secondary" :href="$urlExcel" icon="document-arrow-down" data-tombol-unduh="excel">Unduh Excel</x-ui.button>
