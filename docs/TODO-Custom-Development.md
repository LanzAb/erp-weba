# TODO — Pengembangan Fitur Custom ERP Produksi + DSS SAW

Acuan: [PRD-Monitoring-Produksi-SAW.md](PRD-Monitoring-Produksi-SAW.md) (ID fitur A1…E4 merujuk §4 PRD).
Estimasi: **S** ≤ 2 jam · **M** ≤ 1 hari · **L** > 1 hari. Prioritas: **MVP** wajib · **EXT** pelengkap · **OPT** nilai tambah.

> Konvensi (`AGENTS.md`): ikuti struktur file saudara; jangan ubah dependensi tanpa persetujuan; jangan edit `plugins/webkul/*` (kode custom di `plugins/weba/*`); test pakai Pest; format pakai Pint.

---

## Fase 0 — Lingkungan & Instalasi

- [x] **0.1** PHP 8.3.35 terpasang di `C:\laragon\bin\php\php-8.3.35-nts-Win32-vs16-x64` + ekstensi
- [x] **0.2** Database `weba_erp` (MySQL XAMPP) + `.env`
- [x] **0.3** `composer install`
- [x] **0.4** `key:generate` + `erp:install` (admin `admin@weba.local`)
- [x] **0.5** Plugin terpasang: `products`, `inventories`, `manufacturing`, `sales`
- [x] **0.6** Server lokal: `D:\erp\serve.bat` → `http://127.0.0.1:8080/admin/login`
- [ ] **0.7** `npm install && npm run build` (periksa apakah tampilan perlu build ulang) **S**
- [ ] **0.8** Install plugin `purchases`: `php artisan purchases:install` (dibutuhkan C4) **S**
- [ ] **0.9** Baseline test hijau: `php artisan test plugins/webkul/manufacturing`; catat hasil **S**
- [ ] **0.10** Fork/buat repo sendiri; `origin` masih upstream, simpan upstream sebagai `upstream`; commit `.env.example`, `serve.bat`, `docs/` **S**
- [ ] **0.11** Ganti password admin; buat `.env` produksi terpisah nanti **S**
- [ ] **0.12** Jelajahi UI: alur Sales Order → Manufacturing Order → Work Order → selesai; catat apa yang bekerja & tidak (input untuk C6) **M**

## Fase 1 — Lokalisasi & Identitas  *(D1–D3)*

- [ ] **1.1** (D1, MVP) Tambah locale `id` di `config/app.php` → `supported_locales` **S**
- [ ] **1.2** (D1, MVP) `lang/id/*` untuk string inti panel; deteksi sisa pakai `FindMissingTranslations` **L**
- [ ] **1.3** (D1, MVP) Terjemahan `id` untuk plugin dipakai: manufacturing, inventories, purchases, sales. Hanya **menambah** file lang (atau override via `lang/vendor/<plugin>/id`), tanpa mengubah kode **L**
- [ ] **1.4** (D1) `APP_LOCALE=id` bila cakupan terjemahan cukup **S**
- [ ] **1.5** (D2, MVP) Rupiah: pastikan base currency IDR, format `Rp 1.234.567`; zona waktu WIB sudah di `.env.example` **S**
- [ ] **1.6** (D2, MVP) Pajak PPN 11% sebagai master pajak (bila pembelian/penjualan dipakai) **S**
- [ ] **1.7** (D3, EXT) Logo/favicon/warna utama (`public/images/logo.svg`, `AdminPanelProvider::colors`) **S**

## Fase 2 — Scaffolding Plugin `weba/production-priority`

- [x] **2.1** **Spike:** plugin kosong di `plugins/weba/production-priority` (pola `plugins/webkul/manufacturing`), `composer dump-autoload`, pastikan ter-discover & muncul di Plugin Manager, bisa `production-priority:install`. Gagal → fallback `plugins/webkul/` + namespace `Webkul\ProductionPriority` **M**
- [x] **2.2** `hasDependencies(['manufacturing','sales'])` (+ `purchases` untuk C4), `hasViews/Translations/Migrations`, install & uninstall command **S**
- [ ] **2.3** Navigasi di `NavigationGroup::Manufacturing` (grup baru butuh persetujuan karena menambah enum) **S**
- [x] **2.4** `lang/en` & `lang/id` plugin **S**

## Fase 3 — Mesin SAW  *(A1, A2 — inti penelitian, MVP)*

> **Status:** inti selesai; sisa berkas Excel (3.5) dan form/validasi simpan (3.6, lewat Fase 6.1). Kode: `plugins/weba/production-priority`. Tes: `php artisan test --compact plugins/weba/production-priority/tests/Unit` (40 lulus). Catatan 3.6: validasi bobot ada di `SawCalculator::validateCriteria()` + `normalizeWeights()`; tombol UI-nya dikerjakan di Fase 6.1.
>
> **PERINGATAN tes Feature:** `TestBootstrapHelper` menjalankan `migrate:fresh` + `erp:install`. `phpunit.xml` sekarang memaksa `DB_DATABASE=weba_erp_test` (dibuat manual) dan ada tes penjaga. Jangan hapus override itu, atau tes akan menghapus database dev.

- [x] **3.1** Migrasi `production_priority_criteria` + model `Criterion` + enum `CriterionType`/`CriterionSource` **M**
- [x] **3.2** Seeder 6 kriteria C1–C6 (bobot 0,30/0,20/0,15/0,15/0,10/0,10) — **tandai sementara** **S**
- [x] **3.3** `Services/SawCalculator` (murni): normalisasi, skor, ranking, semua edge case PRD §5.3 **M**
- [x] **3.4** **Unit test Pest** `SawCalculatorTest`: contoh §5.4 (0,840/0,640/0,200 → A>B>C), tiap edge case, validasi total bobot **M**
- [~] **3.5** ≥ 10 dataset uji (**16 kasus selesai**, dihitung independen dengan Python/Fraction di `tests/Fixtures/saw_cases.json`). *Sisa:* file Excel dengan rumus hidup sebagai bukti hitung manual untuk bab pengujian **M**
- [x] **3.6** Validasi bobot: total aktif = 1. `validateCriteria()` di kalkulator (teruji), indikator total bobot di halaman Kriteria, tombol "Normalisasi bobot" (`CriterionWeightNormalizer`, total tepat 1,0000), dan SAW menolak berjalan bila total bobot bukan 1 **S**

## Fase 4 — Data Kriteria & Monitoring Dasar  *(A3, A4, B1–B3)*

> **Status:** 4.1–4.4 selesai; 4.5 dan 4.6 menunggu. Kode: `OrderAttribute`, `OrderProgressCalculator` (murni), `OrderProgressService`, `CriterionValueResolver`.
> **Definisi "tersedia" (4.3):** bahan dianggap tersedia bila sudah **ter-reserve** pada move bahan baku order, bukan sekadar ada stok di gudang. Order yang belum di-*check availability* terbaca 0%.
>
> **Cara menjalankan tes:**
> - Unit (cepat, tanpa database): `php artisan test --compact plugins/weba/production-priority/tests/Unit`
> - Feature (±80 dtk, memakai database `weba_erp_test`): `php vendor/bin/pest --colors=never plugins/weba/production-priority/tests/Feature`. **Jangan** pakai `php artisan test` untuk Feature: proses induk artisan kena `set_time_limit(300)` dari `AdminPanelProvider` sehingga run yang lama tampak gagal semua. Jangan jalankan dua run Feature bersamaan (merusak database tes).

- [x] **4.1** (A3) Migrasi + model `OrderAttribute` (1:1 MO: `urgency_level`, `order_value`); relasi ke `Order` via `resolveRelationUsing` (pola `Product::resolveRelationUsing` di `ManufacturingServiceProvider`) **M**
- [x] **4.2** (B1, B2) `OrderProgressService`: progres %, sisa durasi, `daysToDeadline()`, `isLate()`; unit test **M**
- [x] **4.3** (B3) Ketersediaan bahan % dari `rawMaterialMoves` (reserved/needed), fallback `reservation_state`; test **M**
- [x] **4.4** (A4) `CriterionValueResolver`: map `source` → nilai; satu pass, eager loading; test jumlah query **M**
- [ ] **4.5** (C6) Nilai order dari Sales Order (via `origin`) bila ada, selain itu input manual; tentukan setelah 0.12 **M**
- [x] **4.6** (A3) UI input urgensi & nilai order: contribution ke form MO bila ada hook; jika tidak → tab/halaman "Atribut Prioritas" sendiri **M**

## Fase 5 — Evaluasi & Penerapan  *(A5, A7, A8)*

> **Status:** 5.1–5.5 selesai; 5.6 (perbandingan dua evaluasi, EXT) belum. Kode: `Evaluation`, `EvaluationItem` (snapshot tidak bisa diubah), `EvaluationService`, `PriorityApplier`, command `production-priority:run`, jadwal harian 06:00.
> **Cara kerja:** hanya order berstatus `confirmed`/`progress`/`to_close` yang dinilai. "Terapkan prioritas" hanya mengubah order yang benar-benar berubah (Normal → Urgent) dan mencatat prioritas lamanya, sehingga pembatalan tidak menimpa perubahan manual orang lain. Tiap penerapan meninggalkan catatan di chatter order.
> **Catatan lingkungan tes:** helper bawaan Aureus (`TestBootstrapHelper`) punya bug di Windows (regex jalur memakai `/`), sehingga tes Feature gagal selang-seling. Sudah diperbaiki 1 baris di `plugins/webkul/support/tests/Helpers/TestBootstrapHelper.php` (juga mengenali folder `plugins/weba`). Jika memperbarui dari upstream dan helper tertimpa, perbaikan ini harus diterapkan ulang.

- [x] **5.1** Migrasi + model `Evaluation` & `EvaluationItem` (snapshot kriteria, nilai mentah, ternormalisasi, skor, rank) **M**
- [x] **5.2** Service jalankan evaluasi: order aktif → resolver → calculator → simpan dalam satu transaksi **M**
- [x] **5.3** Command `production-priority:run` + jadwal harian 06:00 WIB **S**
- [x] **5.4** (A7) Aksi "Terapkan prioritas": top-N → `priority = URGENT` + catatan chatter; bisa dibatalkan **M**
- [x] **5.5** Feature test: snapshot tidak berubah saat bobot diubah; scoping per company **M**
- [ ] **5.6** (A8, EXT) Perbandingan dua evaluasi (perubahan rank) **M**

## Fase 6 — Antarmuka DSS  *(A1, A6, A9)*

> **Status:** 6.1–6.3 selesai (6.4 simulasi bobot, OPT, belum). Menu: **Manufacturing → Prioritas Produksi** dengan tiga halaman: *Monitoring Order* (juga tempat mengisi urgensi dan nilai order lewat tombol per baris), *Kriteria & Bobot*, dan *Hasil Evaluasi SAW*.
> **Verifikasi:** 18 tes Livewire (render halaman, form, semua aksi). **Belum dilihat di browser**; tampilan visual perlu dicek manual.
> **Akses:** permission `<aksi>_production_priority_criterion` / `_evaluation` dibuat oleh `production-priority:install`. Monitoring Order mengikuti permission order produksi bawaan. Evaluasi tidak bisa dihapus (jejak audit).

- [x] **6.1** `CriterionResource`: CRUD, urut drag, indikator total bobot **M**
- [x] **6.2** `EvaluationResource`: riwayat, tombol **Jalankan SAW**, detail dengan tab *Matriks Keputusan · Normalisasi · Skor & Ranking* **L**
- [x] **6.3** Daftar order versi monitoring: bar progres, sisa hari, badge **Terlambat**, skor & rank terakhir, filter status **M**
- [ ] **6.4** (A9, OPT) Simulasi bobot (what-if tanpa simpan) **M**

## Fase 7 — Dashboard Monitoring  *(B4–B7)*

> **Status:** 7.1–7.5 selesai, 7.6 sebagian, 7.7 (notifikasi, EXT) belum. Menu: **Dashboard → Dashboard Produksi** (lima widget: ringkasan, order per status, beban work center, 10 ranking SAW teratas, order terlambat). Data order dimuat sekali per request (`ProductionOverview`) dan semua widget memperbarui diri tiap 30–60 detik.
> **Aturan penting untuk widget:** judul dan konfigurasi tabel widget **tidak boleh mengquery database**. Filament Shield menyusun tabel setiap widget untuk membaca labelnya saat `shield:generate` (termasuk dari `erp:install`), sebelum tabel plugin ada. Ada tes regresi untuk ini.

- [x] **7.1** `ProductionStatsWidget`: total aktif, terlambat, jatuh tempo ≤ 3 hari, rata-rata progres (polling 30 dtk) **M**
- [x] **7.2** `OrdersByStatusChart` **S**
- [x] **7.3** `PriorityRankingWidget`: Top 10 SAW terakhir **M**
- [x] **7.4** `LateOrdersTable` **M**
- [x] **7.5** Halaman Dashboard Produksi + permission widget (`HasWidgetShield`) **S**
- [~] **7.6** (B6, EXT) `WorkCenterLoadWidget`: sisa jam kerja per work center dari order berjalan. *Sisa:* membandingkan dengan kapasitas work center (belum, karena butuh kalender kerja dan kapasitas) **M**
- [ ] **7.7** (B7, EXT) Notifikasi database terjadwal untuk terlambat/mendekati deadline **M**

## Fase 8 — Fitur ERP Produksi Tambahan  *(C1–C8)*

- [ ] **8.1** (C1, MVP) Laporan produksi harian: target vs realisasi per order/mesin/shift (halaman + filter tanggal) **L**
- [ ] **8.2** (C8, MVP) Ekspor Excel (Maatwebsite): ranking SAW, monitoring, laporan harian **M**
- [ ] **8.3** (C8, MVP) Ekspor PDF (DomPDF) dengan kop PT. Weba **M**
- [ ] **8.4** (C6, MVP) Verifikasi/perbaiki alur Sales Order → Order Produksi; nilai order & customer terbawa ke atribut **M**
- [ ] **8.5** (C2, EXT) Migrasi/model `DefectLog` + form catat reject (jumlah, jenis, penyebab) terhubung ke scrap inventori **L**
- [ ] **8.6** (C3, EXT) Jadwal produksi: kalender/Gantt order & work order (pakai plugin `full-calendar`) **L**
- [ ] **8.7** (C4, EXT) `MaterialShortageService` + halaman kekurangan bahan: kebutuhan − stok − PO berjalan; tombol "Buat RFQ" ke `purchases` **L**
- [ ] **8.8** (C5, EXT) `ProductionCostService`: HPP per order = bahan + biaya work center (`costs_per_hour × duration`); tampil di detail order & laporan **L**
- [ ] **8.9** (C7, EXT) Impor Excel (produk, BoM, order awal) dengan template & validasi **L**

## Fase 9 — Hak Akses & Keamanan  *(E2)*

- [ ] **9.1** Policy + permission Shield: Criterion, Evaluation, OrderAttribute, DefectLog, widget & halaman **M**
- [ ] **9.2** Seeder peran: Admin, Manajer Produksi/PPIC, Supervisor, Gudang/Pembelian, Pimpinan (read-only) **M**
- [ ] **9.3** Test otorisasi per peran + isolasi company **M**

## Fase 10 — Data Studi Kasus  *(E1)*

- [ ] **10.1** Kumpulkan data PT. Weba: produk, BoM, mesin/stasiun, vendor, customer, contoh order **M** *(butuh narasumber)*
- [ ] **10.2** Seeder master data PT. Weba (atau dummy terdokumentasi bila data nyata belum ada) **M**
- [ ] **10.3** Seeder data uji ≥ 30 order: beberapa terlambat, variasi progres/urgensi/bahan **M**
- [ ] **10.4** Validasi kriteria & bobot dengan PT. Weba (wawancara/kuesioner/AHP) → perbarui seeder 3.2 **M** *(butuh narasumber)*

## Fase 11 — Pengujian & Validasi (bahan Bab Pengujian)

- [ ] **11.1** Uji fungsional black-box: skenario × hasil untuk seluruh fitur MVP **M**
- [ ] **11.2** Uji akurasi: sistem vs Excel (≥ 10 dataset) → selisih 0 **S**
- [ ] **11.3** Uji kinerja: SAW @200 order ≤ 3 dtk; dashboard < 2 dtk **S**
- [ ] **11.4** Perbandingan prioritas manual PPIC vs SAW pada data historis (kesesuaian %, keterlambatan) **M** *(butuh data PT. Weba)*
- [ ] **11.5** UAT + kuesioner (mis. SUS) dengan PPIC/pimpinan **M**
- [ ] **11.6** Regresi: `php artisan test plugins/webkul/manufacturing plugins/weba/production-priority`; `vendor/bin/pint --dirty` **S**

## Fase 12 — Dokumentasi & Penutup  *(E4)*

- [ ] **12.1** Diagram skripsi: use case, activity, ERD (PRD §8.2), flowchart SAW, arsitektur plugin **M**
- [ ] **12.2** Panduan pengguna singkat dengan tangkapan layar **M**
- [ ] **12.3** README proyek (nama, cara install khusus PT. Weba) **S**
- [ ] **12.4** (OPT) Deploy ke server Linux + backup DB **L**

---

## Urutan Kerja yang Disarankan

**Jalur MVP (skripsi):** `0.7–0.12 → 2.1 → 3 → 4 → 5 → 6 → 7.1–7.5 → 8.1–8.4 → 1.1–1.6 → 9 → 10 → 11 → 12`
**Jika waktu sisa (EXT):** `8.5 → 8.7 → 8.6 → 8.8 → 7.6/7.7 → 8.9 → 5.6 → 1.7`
**OPT:** `6.4`, `12.4`

Fase 3 dikerjakan lebih awal dari UI karena merupakan inti penelitian dan bisa diuji tanpa data manufaktur lengkap.

## Pertanyaan yang harus dijawab (PRD §11)

- [ ] Kriteria & bobot sebenarnya di PT. Weba — mengganti default 3.2
- [ ] Jenis produksi & jumlah mesin/tahap — menentukan 10.2
- [ ] Ketersediaan data historis — menentukan 11.4
- [ ] Apakah pembelian, reject, dan HPP dikelola sistem — menentukan fitur EXT yang dikerjakan
- [x] Ejaan resmi nama perusahaan: "International" (dikonfirmasi)
