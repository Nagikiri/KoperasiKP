{{--
    Tombol salin kecil di kartu Identitas detail anggota (revisi pasca-F3b).
    $teks: nilai yang disalin; $label: aria-label/title (mis. "Salin NIK"); $kunci: nilai data-salin.
    Disembunyikan jika navigator.clipboard tidak tersedia (bukan https/localhost). Setelah berhasil:
    ikon centang dan teks "Tersalin" selama ±1,5 detik.
--}}
<span class="inline-flex shrink-0 items-center gap-1"
      x-data="{
          bisa: Boolean(window.isSecureContext && navigator.clipboard),
          tersalin: false,
          jeda: null,
          salin() {
              navigator.clipboard.writeText(@js($teks)).then(() => {
                  this.tersalin = true;
                  clearTimeout(this.jeda);
                  this.jeda = setTimeout(() => this.tersalin = false, 1500);
              });
          },
      }">
    <x-ui.button variant="ghost" size="sm" square type="button" class="-my-1.5" data-salin="{{ $kunci }}"
                 aria-label="{{ $label }}" title="{{ $label }}" x-show="bisa" x-cloak x-on:click="salin()">
        <x-ui.icon name="document-duplicate" class="h-4 w-4 text-stone-400" x-show="! tersalin" />
        <x-ui.icon name="check" class="h-4 w-4 text-emerald-600" x-show="tersalin" x-cloak />
    </x-ui.button>
    <span class="text-xs font-normal text-stone-500" role="status" x-show="tersalin" x-cloak>Tersalin</span>
</span>
