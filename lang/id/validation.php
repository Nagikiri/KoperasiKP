<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Validasi
    |--------------------------------------------------------------------------
    |
    | Struktur mengikuti file validation bawaan Laravel. Rule yang tidak
    | tercantum di sini memakai fallback bahasa Inggris dari framework.
    |
    */

    'after_or_equal' => ':Attribute harus berupa tanggal setelah atau sama dengan :date.',
    'before_or_equal' => ':Attribute harus berupa tanggal sebelum atau sama dengan :date.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi saat ini salah.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'date_format' => ':Attribute tidak sesuai dengan format :format.',
    'digits' => ':Attribute harus terdiri dari :digits digit.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => ':Attribute tidak boleh memiliki lebih dari :max item.',
        'file' => ':Attribute tidak boleh lebih dari :max kilobyte.',
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
        'string' => ':Attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'array' => ':Attribute minimal memiliki :min item.',
        'file' => ':Attribute minimal berukuran :min kilobyte.',
        'numeric' => ':Attribute minimal bernilai :min.',
        'string' => ':Attribute minimal berisi :min karakter.',
    ],
    'numeric' => ':Attribute harus berupa angka.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':Attribute wajib diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah digunakan.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi Khusus
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama Atribut
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'nama' => 'nama',
        'nik' => 'NIK',
        'alamat' => 'alamat',
        'no_telepon' => 'nomor telepon',
        'tanggal_bergabung' => 'tanggal bergabung',
        'anggota_id' => 'anggota',
        'jenis_simpanan_id' => 'jenis simpanan',
        'jumlah' => 'jumlah',
        'tanggal_transaksi' => 'tanggal transaksi',
        'tanggal_mulai' => 'tanggal mulai',
        'tanggal_selesai' => 'tanggal selesai',
        'username' => 'username',
        'password' => 'kata sandi',
        'current_password' => 'kata sandi saat ini',
    ],

];
