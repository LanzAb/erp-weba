# PRD — Sistem ERP Produksi Berbasis Web dengan DSS Metode SAW

**Judul penelitian:** Rancang Bangun Sistem Enterprise Resource Planning (ERP) Produksi Berbasis Web dengan Decision Support System Metode Simple Additive Weighting (SAW) untuk Menentukan Prioritas Penyelesaian Produksi (Studi Kasus: PT. Weba International)

**Basis sistem:** Aureus ERP (Laravel 13 + Filament 5, arsitektur plugin) — repo `aureuserp/aureuserp`
**Versi:** Draft 2 — fokus pada **fitur yang belum ada**. Kriteria, bobot, dan data studi kasus masih asumsi, harus divalidasi dengan PT. Weba (§11).
**Catatan penulisan:** ejaan resmi: "International" (dikonfirmasi pengguna).

---

## 1. Latar Belakang & Tujuan

Penentuan urutan penyelesaian order produksi di PT. Weba diasumsikan masih manual (perkiraan/urutan masuk), sehingga rawan terlambat dan tidak transparan. Sistem ini adalah ERP produksi yang:

1. **Mengelola** siklus produksi end-to-end: penjualan → perencanaan → bahan baku/pembelian → produksi → hasil/stok.
2. **Memonitor** status produksi real-time (progres, keterlambatan, beban mesin, reject).
3. **Menentukan prioritas** penyelesaian order secara objektif dengan **DSS metode SAW**.

**Tujuan terukur (bab pengujian):**
- Ranking SAW ≤ 3 detik untuk ≤ 200 order aktif.
- Hasil sistem identik dengan hitungan manual (Excel).
- Dashboard memuat < 2 detik, ter-refresh ≤ 30 detik.

## 2. Status Saat Ini (per 7 Okt 2026)

| Item | Status |
|---|---|
| Aureus ERP ter-clone di `D:\erp` | ✅ |
| PHP 8.3.35, MySQL (XAMPP, db `weba_erp`), `.env`, `composer install` | ✅ |
| `erp:install` (admin `admin@weba.local`) | ✅ |
| Plugin terpasang: `products`, `inventories`, `manufacturing`, `sales` | ✅ |
| Server lokal `D:\erp\serve.bat` → `http://127.0.0.1:8080/admin/login` | ✅ |
| Plugin `purchases` | ⬜ belum di-install (ada di repo) |
| **Fitur SAW/DSS** | ❌ tidak ada di Aureus (sudah diverifikasi lewat pencarian kode) |
| Lokalisasi Indonesia, data PT. Weba, semua fitur custom di bawah | ❌ belum |

## 3. Ruang Lingkup

**Sudah dicakup Aureus (dipakai ulang, bukan dibuat):** order produksi, work order, work center, BoM, stok & lot, penerimaan/pengiriman, scrap, replenishment (order point), pembelian (RFQ/PO/vendor/bill/receipt), penjualan (quotation/SO), RBAC (Shield), log aktivitas (chatter), barcode, kalender.

**Perlu dibangun (inti dokumen ini):** lihat §4.

**Di luar lingkup:** akuntansi penuh, HR/rekrutmen/time-off, website/blog, proyek, maintenance → tidak di-install.

## 4. Daftar Fitur yang Belum Ada (Gap)

Prioritas: **MVP** = wajib untuk skripsi · **EXT** = pelengkap "ERP produksi" (kerjakan jika waktu cukup) · **OPT** = nilai tambah.

### A. DSS — Prioritas Produksi dengan SAW  *(inti penelitian, MVP)*
| ID | Fitur | Prio |
|---|---|---|
| A1 | Master kriteria & bobot (tipe benefit/cost, aktif, urutan, validasi total bobot = 1) | MVP |
| A2 | Mesin hitung SAW (normalisasi, skor, ranking, edge case) | MVP |
| A3 | Atribut order: tingkat urgensi (1–5) & nilai order | MVP |
| A4 | Penghitung nilai kriteria otomatis dari data produksi (§5.1) | MVP |
| A5 | Evaluasi: jalankan manual & terjadwal, simpan snapshot | MVP |
| A6 | Halaman hasil transparan: matriks keputusan, normalisasi, skor, ranking | MVP |
| A7 | "Terapkan prioritas" ke order (top-N → Urgent) + alasan | MVP |
| A8 | Riwayat evaluasi & perbandingan dua evaluasi | EXT |
| A9 | Simulasi/analisis sensitivitas bobot | OPT |

### B. Monitoring Produksi  *(MVP)*
| ID | Fitur | Prio |
|---|---|---|
| B1 | Progres order % (work order selesai/total, fallback `quantity_producing/quantity`) | MVP |
| B2 | Sisa durasi produksi, sisa hari ke deadline, status **Terlambat** | MVP |
| B3 | Persentase ketersediaan bahan per order | MVP |
| B4 | Dashboard produksi: order per status, terlambat, jatuh tempo ≤ 3 hari, rata-rata progres | MVP |
| B5 | Widget Top-10 ranking SAW & tabel order terlambat | MVP |
| B6 | Beban & kapasitas work center | EXT |
| B7 | Notifikasi database: order terlambat / mendekati deadline | EXT |

### C. Fitur ERP Produksi Tambahan *(menutup kekurangan Aureus untuk judul "ERP produksi")*
| ID | Fitur | Alasan belum ada | Prio |
|---|---|---|---|
| C1 | **Laporan produksi harian** (target vs realisasi per order/mesin/shift) | Tidak ada laporan di modul manufaktur | MVP |
| C2 | **Pencatatan reject/cacat produksi** (jumlah, jenis cacat, penyebab) → ke scrap inventori | Tidak ada modul kualitas; scrap terpisah di inventori | EXT |
| C3 | **Jadwal produksi** (kalender/Gantt order & work order berdasarkan deadline) | Tidak ada Gantt; hanya plugin kalender umum | EXT |
| C4 | **Perencanaan kebutuhan bahan (MRP-lite)**: kekurangan bahan = kebutuhan order aktif − stok − PO berjalan; tombol "Buat RFQ" ke modul pembelian | Tidak ada MRP/forecast | EXT |
| C5 | **Biaya produksi (HPP) per order**: bahan + biaya work center (`costs_per_hour` sudah ada di WO) | Tidak ada roll-up biaya | EXT |
| C6 | Alur **Sales Order → Order Produksi** terverifikasi (nilai order & customer ikut terbawa) | Perlu diuji; integrasi penuh belum terkonfirmasi | MVP |
| C7 | Impor data massal dari Excel (produk, BoM, order awal) | Tidak ditemukan fitur impor | EXT |
| C8 | Ekspor laporan PDF/Excel (ranking SAW, monitoring, produksi harian) | Tidak ada ekspor di manufaktur | MVP |

### D. Lokalisasi & Identitas
| ID | Fitur | Prio |
|---|---|---|
| D1 | Bahasa Indonesia (`id`) — aplikasi inti + plugin yang dipakai + plugin custom | MVP |
| D2 | Rupiah (IDR), WIB, format tanggal/angka Indonesia, PPN 11% (jika pembelian/penjualan dipakai) | MVP |
| D3 | Logo, favicon, warna PT. Weba | EXT |

### E. Data, Akses, & Kualitas
| ID | Fitur | Prio |
|---|---|---|
| E1 | Seeder data PT. Weba (produk, BoM, work center, vendor, customer, order uji) | MVP |
| E2 | Peran & permission: Admin, Manajer Produksi/PPIC, Supervisor, Pimpinan, Gudang/Pembelian | MVP |
| E3 | Uji otomatis (Pest) mesin SAW, resolver kriteria, otorisasi | MVP |
| E4 | Deploy ke server Linux, backup DB, panduan pengguna | OPT |

## 5. Metode SAW — Spesifikasi Perhitungan

### 5.1 Kriteria default (usulan — **wajib divalidasi** dengan PT. Weba)

| Kode | Kriteria | Tipe | Sumber data | Bobot awal |
|---|---|---|---|---|
| C1 | Sisa waktu menuju deadline (hari) | Cost | `deadline_at − now()` | 0,30 |
| C2 | Tingkat urgensi pelanggan (1–5) | Benefit | atribut order (A3) | 0,20 |
| C3 | Ketersediaan bahan baku (%) | Benefit | B3 | 0,15 |
| C4 | Sisa durasi produksi (jam) | Cost | Σ(`expected_duration` − `duration`) WO belum selesai | 0,15 |
| C5 | Progres penyelesaian (%) | Benefit | B1 | 0,10 |
| C6 | Nilai order (Rp) | Benefit | atribut order / Sales Order | 0,10 |
| | **Total** | | | **1,00** |

Kriteria berupa **konfigurasi** (aktif/nonaktif, tipe, bobot), bukan hardcode. Total bobot aktif = 1 (validasi, atau normalisasi otomatis).

### 5.2 Rumus
1. Matriks keputusan **X**: baris = order aktif (`confirmed`, `progress`, `to_close`), kolom = kriteria aktif.
2. Normalisasi: benefit `r = x / max(x)`; cost `r = min(x) / x`.
3. Skor: `V_i = Σ w_j · r_ij`.
4. Ranking menurun; rank 1 = prioritas tertinggi.

### 5.3 Edge case
| Kasus | Penanganan |
|---|---|
| `max(x)=0` (benefit) | semua `r=0` untuk kriteria itu |
| `x=0` (cost) | `r=1` |
| Deadline lewat | sisa hari = 0 → `r=1`, ditandai **Terlambat** |
| Deadline kosong | nilai terbesar di himpunan (prioritas terendah C1) + peringatan |
| 1 order | skor = Σ bobot, rank 1 |
| Skor sama | tie-break: `deadline_at` terawal, lalu `id` terkecil |
| Presisi | simpan 6 desimal, tampil 4 |

### 5.4 Contoh hitung (jadi unit test & bahan bab pembahasan)
C1 cost (bobot 0,6), C2 benefit (bobot 0,4):

| Order | C1 sisa hari | C2 urgensi |
|---|---|---|
| A | 2 | 3 |
| B | 5 | 5 |
| C | 10 | 1 |

Normalisasi C1 (min=2): A=1,000 · B=0,400 · C=0,200. Normalisasi C2 (max=5): A=0,600 · B=1,000 · C=0,200.
**V_A = 0,840 · V_B = 0,640 · V_C = 0,200 → A > B > C.**

## 6. Kebutuhan Fungsional Ringkas
Seluruh fitur pada §4 adalah kebutuhan fungsional; ID tabel (A1…E4) dipakai sebagai referensi tunggal di todolist dan pengujian. Peran pengguna:

| Peran | Akses |
|---|---|
| Admin Sistem | penuh |
| Manajer Produksi / PPIC | kriteria, evaluasi SAW, order, jadwal, MRP |
| Supervisor Produksi | order & work order, laporan harian, reject |
| Gudang / Pembelian | stok, penerimaan, RFQ/PO |
| Pimpinan | dashboard & laporan (baca saja) |

## 7. Kebutuhan Non-Fungsional
- **Kinerja:** SAW ≤ 3 dtk @200 order; dashboard < 2 dtk (query agregat, tanpa N+1).
- **Akurasi:** seluruh hitungan SAW di satu service murni yang bisa diuji unit.
- **Keamanan:** Shield + policy; multi-company scoping; ubah bobot hanya peran berwenang.
- **Audit:** perubahan bobot tercatat; snapshot evaluasi immutable.
- **Maintainability:** kode custom hanya di plugin sendiri; **tidak mengubah `plugins/webkul/*`** (kecuali menambah file bahasa) agar update upstream mudah.
- **Kompatibilitas:** PHP ≥ 8.3, MySQL 8, browser modern, responsif.

## 8. Rancangan Teknis

### 8.1 Struktur plugin
```
plugins/weba/production-priority/          # namespace Weba\ProductionPriority
├── composer.json                          # ikut ter-merge via plugins/*/*/composer.json
├── database/{migrations,seeders,factories}
├── lang/{en,id}
├── src/
│   ├── ProductionPriorityPlugin.php, ...ServiceProvider.php   # hasDependencies(['manufacturing','sales','purchases'])
│   ├── Enums/        CriterionType, CriterionSource
│   ├── Models/       Criterion, Evaluation, EvaluationItem, OrderAttribute, DefectLog (C2)
│   ├── Services/     SawCalculator, CriterionValueResolver, OrderProgressService,
│   │                 MaterialShortageService (C4), ProductionCostService (C5)
│   ├── Filament/     Resources, Pages (Dashboard, Jadwal, Laporan Harian), Widgets, Clusters
│   ├── Exports/      RankingExport, DailyProductionExport
│   ├── Console/      RunEvaluationCommand
│   └── Policies/
└── tests/{Unit,Feature}
```
Plugin manager tidak terkunci ke folder `webkul` (didiscover lewat composer merge-plugin) — tetap diverifikasi di TODO 2.1; fallback `plugins/webkul/` dengan namespace `Webkul\…`.

### 8.2 Skema database (tabel ekstensi; tidak mengubah tabel inti)
- **`production_priority_criteria`**: `id, company_id, code, name, type(benefit|cost), source, weight decimal(6,4), is_active, sort`
- **`production_priority_order_attributes`** (1:1 MO): `manufacturing_order_id (unique), urgency_level 1–5, order_value, notes`
- **`production_priority_evaluations`**: `id, company_id, name, evaluated_at, evaluated_by, trigger(manual|scheduled), criteria_snapshot json, order_count, notes`
- **`production_priority_evaluation_items`**: `evaluation_id, manufacturing_order_id, raw_values json, normalized_values json, score decimal(10,6), rank, applied` (index `(evaluation_id, rank)`)
- **`production_priority_defect_logs`** (C2): `manufacturing_order_id, work_order_id nullable, defect_type, quantity, cause, recorded_by, recorded_at`
- **`production_priority_daily_reports`** (C1, bila perlu snapshot): `report_date, manufacturing_order_id, work_center_id, shift, target_qty, actual_qty, notes`

### 8.3 Komponen kunci
- **`SawCalculator`** — murni (tanpa DB), diuji dengan contoh §5.4.
- **`CriterionValueResolver`** — map `source` → nilai per order, eager loading.
- **`OrderProgressService`** — satu sumber kebenaran progres/sisa durasi/terlambat untuk dashboard & resolver.
- **`MaterialShortageService`** — kebutuhan BoM order aktif − stok − PO berjalan.
- **Dashboard** — widget mengikuti pola `projects` (`StatsOverviewWidget`, chart, tabel) + `HasWidgetShield`.

### 8.4 Alur pengguna
1. Admin mengatur kriteria & bobot → 2. Order dibuat dari Sales/Manufacturing, PPIC mengisi urgensi & nilai → 3. Sistem menghitung kekurangan bahan, gudang/pembelian membuat RFQ → 4. PPIC menjalankan **SAW** (manual / jadwal harian 06:00 WIB) → 5. Ranking tampil, PPIC **Terapkan prioritas** → 6. Supervisor mengerjakan sesuai ranking, mengisi laporan harian & reject → 7. Pimpinan memantau dashboard & laporan.

## 9. Risiko

| Risiko | Mitigasi |
|---|---|
| Cakupan "ERP produksi" terlalu luas untuk waktu skripsi | Kerjakan **MVP** dulu; EXT/OPT hanya jika sisa waktu; fitur dasar sudah dari Aureus |
| Dosen mempertanyakan orisinalitas karena basis open-source | Sebut Aureus sebagai basis; tonjolkan kontribusi A, B, C1, C4, D dan studi kasus |
| Data PT. Weba belum ada | Data dummy terdokumentasi; validasi ulang saat data nyata ada |
| Update upstream merusak kustom | Kode custom terisolasi + test regresi |
| Modul manufaktur kompleks (rute, lokasi, stok) | Seeder master data + panduan singkat |
| `artisan serve` mati setelah 300 dtk (`set_time_limit` di `AdminPanelProvider`) | Pakai `serve.bat` (sudah dibuat) |

## 10. Kriteria Penerimaan
- [ ] Contoh §5.4 → 0,840 / 0,640 / 0,200 pada unit test.
- [ ] Hasil sistem = Excel pada ≥ 10 dataset.
- [ ] Seluruh fitur **MVP** berfungsi sesuai peran.
- [ ] UI Bahasa Indonesia tanpa string Inggris tersisa di plugin custom.
- [ ] Performa sesuai §7.
- [ ] Test hijau (plugin custom + regresi manufacturing).

## 11. Pertanyaan Terbuka (konfirmasi ke PT. Weba)
1. Kriteria sebenarnya yang dipakai PPIC? Bobot ditetapkan lewat wawancara/kuesioner/AHP? Siapa respondennya?
2. Jenis produk & proses produksi (make-to-order/stock)? Ada berapa mesin/tahap?
3. Data historis order (≥ 30–50) untuk uji manual vs SAW?
4. Apakah pembelian bahan, reject, dan HPP memang dikelola sistem (menentukan EXT mana yang dikerjakan)?
5. ~~Ejaan resmi nama perusahaan~~ — sudah dikonfirmasi: "International".
