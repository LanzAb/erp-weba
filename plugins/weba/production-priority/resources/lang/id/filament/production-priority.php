<?php

return [
    'cluster' => [
        'navigation' => [
            'title' => 'Prioritas Produksi',
        ],
    ],

    'criterion' => [
        'navigation' => [
            'title' => 'Kriteria & Bobot',
        ],

        'form' => [
            'section'     => 'Kriteria SAW',
            'code'        => 'Kode',
            'name'        => 'Nama kriteria',
            'type'        => 'Tipe',
            'source'      => 'Sumber data',
            'weight'      => 'Bobot',
            'weight-hint' => 'Angka 0 sampai 1. Total bobot semua kriteria aktif harus tepat 1.',
            'is-active'   => 'Aktif',
        ],

        'table' => [
            'code'      => 'Kode',
            'name'      => 'Nama',
            'type'      => 'Tipe',
            'source'    => 'Sumber data',
            'weight'    => 'Bobot',
            'is-active' => 'Aktif',
        ],

        'total' => [
            'valid'   => '✓ Total bobot kriteria aktif: :total. SAW siap dijalankan.',
            'invalid' => '⚠ Total bobot kriteria aktif: :total. Harus tepat 1 sebelum SAW bisa dijalankan. Gunakan "Normalisasi bobot" untuk memperbaikinya.',
        ],

        'actions' => [
            'normalize' => [
                'label'       => 'Normalisasi bobot',
                'description' => 'Bobot semua kriteria aktif akan diskalakan secara proporsional sehingga totalnya tepat 1.',
                'success'     => 'Bobot berhasil dinormalisasi.',
                'failed'      => 'Bobot tidak dapat dinormalisasi.',
            ],
        ],
    ],

    'evaluation' => [
        'navigation' => [
            'title' => 'Hasil Evaluasi SAW',
        ],

        'table' => [
            'name'         => 'Nama evaluasi',
            'evaluated-at' => 'Waktu evaluasi',
            'trigger'      => 'Pemicu',
            'order-count'  => 'Jumlah order',
            'evaluator'    => 'Dijalankan oleh',
            'applied'      => 'Diterapkan',
        ],

        'infolist' => [
            'summary'     => 'Ringkasan evaluasi',
            'status'      => 'Status prioritas',
            'applied'     => 'Diterapkan ke :count order teratas',
            'not-applied' => 'Belum diterapkan',
            'notes'       => 'Catatan',

            'tabs' => [
                'ranking'           => 'Skor & Ranking',
                'decision-matrix'   => 'Matriks Keputusan',
                'normalized-matrix' => 'Matriks Normalisasi',
                'criteria'          => 'Kriteria & Bobot',
                'warnings'          => 'Peringatan',
            ],

            'ranking' => [
                'rank'     => 'Peringkat',
                'order'    => 'Order produksi',
                'product'  => 'Produk',
                'score'    => 'Skor (V)',
                'priority' => 'Prioritas',
                'urgent'   => 'Mendesak',
            ],

            'warnings' => [
                'order'     => 'Order',
                'criterion' => 'Kriteria',
                'reason'    => 'Keterangan',
                'reasons'   => [
                    'missing_value' => 'Nilai kosong, dihitung sebagai nilai terburuk',
                ],
            ],
        ],

        'actions' => [
            'run' => [
                'label'            => 'Jalankan SAW',
                'heading'          => 'Jalankan evaluasi SAW',
                'description'      => 'Semua order berstatus confirmed, progress, dan to close akan diberi peringkat berdasarkan kriteria aktif.',
                'submit'           => 'Jalankan',
                'name'             => 'Nama evaluasi (opsional)',
                'name-placeholder' => 'Terisi otomatis dengan tanggal dan jam',
                'notes'            => 'Catatan (opsional)',
                'failed'           => 'Evaluasi gagal dijalankan',
                'nothing-to-rank'  => 'Tidak ada order produksi terbuka untuk diberi peringkat',
                'success'          => 'Evaluasi SAW selesai',
                'success-body'     => ':count order telah diberi peringkat.',
            ],

            'apply' => [
                'label'       => 'Terapkan prioritas',
                'heading'     => 'Terapkan prioritas ke order produksi',
                'description' => 'Order dengan peringkat teratas akan ditandai Mendesak. Penerapan ini dapat dibatalkan.',
                'top-n'       => 'Jumlah order teratas',
                'reason'      => 'Alasan (opsional)',
                'success'     => ':count order ditandai Mendesak',
            ],

            'revert' => [
                'label'       => 'Batalkan penerapan',
                'description' => 'Prioritas order dikembalikan ke kondisi sebelum diterapkan. Order yang prioritasnya sudah diubah orang lain tidak disentuh.',
                'success'     => ':count order dikembalikan prioritasnya',
            ],
        ],
    ],

    'monitoring' => [
        'model-label' => 'Order produksi',
        'hours'       => 'jam',

        'navigation' => [
            'title' => 'Monitoring Order',
        ],

        'table' => [
            'order'       => 'Order',
            'product'     => 'Produk',
            'state'       => 'Status',
            'priority'    => 'Prioritas',
            'deadline'    => 'Deadline',
            'time-status' => 'Waktu',
            'progress'    => 'Progres',
            'material'    => 'Bahan',
            'remaining'   => 'Sisa kerja',
            'urgency'     => 'Urgensi',
            'order-value' => 'Nilai order',
            'saw'         => 'SAW terakhir',
        ],

        'filters' => [
            'late' => 'Hanya yang terlambat',
        ],

        'time' => [
            'no-deadline' => 'Tanpa deadline',
            'late'        => 'Terlambat :days hari',
            'left'        => 'Sisa :days hari',
        ],

        'urgency-levels' => [
            1 => 'Sangat rendah',
            2 => 'Rendah',
            3 => 'Normal',
            4 => 'Tinggi',
            5 => 'Sangat mendesak',
        ],

        'actions' => [
            'attributes' => [
                'label'       => 'Atur urgensi & nilai',
                'heading'     => 'Atribut prioritas order',
                'urgency'     => 'Tingkat urgensi pelanggan',
                'order-value' => 'Nilai order',
                'notes'       => 'Catatan',
                'success'     => 'Atribut prioritas disimpan',
            ],
        ],
    ],

    'dashboard' => [
        'navigation' => [
            'title' => 'Dashboard Produksi',
        ],

        'stats' => [
            'heading'                => 'Ringkasan produksi',
            'open-orders'            => 'Order berjalan',
            'open-orders-hint'       => 'Confirmed, progress, dan to close',
            'late'                   => 'Terlambat',
            'late-hint'              => 'Deadline sudah lewat',
            'due-soon'               => 'Segera jatuh tempo',
            'due-soon-hint'          => 'Deadline dalam :days hari',
            'average-progress'       => 'Rata-rata progres',
            'average-progress-hint'  => 'Semua order berjalan',
            'material-short'         => 'Bahan belum lengkap',
            'material-short-hint'    => 'Order dengan bahan belum ter-reserve penuh',
        ],

        'status-chart' => [
            'heading'     => 'Order per status',
            'description' => 'Order selesai dihitung :days hari terakhir',
        ],

        'work-center-load' => [
            'heading'     => 'Beban work center',
            'description' => 'Sisa pekerjaan pada order berjalan (belum dibandingkan dengan kapasitas)',
            'dataset'     => 'Sisa jam kerja',
        ],

        'ranking' => [
            'heading'                 => ':limit order prioritas teratas',
            'evaluation'              => 'Evaluasi terakhir: :name (:date)',
            'empty-heading'           => 'Belum ada evaluasi SAW',
            'empty-description'       => 'Jalankan SAW untuk mendapatkan peringkat prioritas penyelesaian.',
            'open-evaluations'        => 'Buka hasil evaluasi',
        ],

        'late' => [
            'heading'       => 'Order terlambat (maks. :limit, paling lama dulu)',
            'days-late'     => 'Terlambat',
            'days'          => 'hari',
            'empty-heading' => 'Tidak ada order yang terlambat',
        ],
    ],
];
