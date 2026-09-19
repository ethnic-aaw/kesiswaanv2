# PRD — Kesiswaan
**Sistem Kesiswaan Sekolah**
Version 1.4 · Stack: PHP Native + MySQL · Deploy: Dokploy

> **Changelog v1.0 → v1.1:** Menambahkan spesifikasi keamanan (hashing password, CSRF, validasi upload), memperbaiki skema database (relasi wali kelas, soft-delete, indeks), dan memperjelas alur bisnis (threshold poin, siklus tahun ajaran, sumber kebenaran bobot poin).
>
> **Changelog v1.1 → v1.2:** Memperbaiki konflik unique constraint dengan soft-delete (NIPD/username/kode pelanggaran tetap unik permanen), memindahkan audit log dasar (khusus override poin) ke scope v1.0, menambahkan validasi format No. HP Orang Tua, dan menambahkan kebijakan backup data.
>
> **Changelog v1.2 → v1.3:** Menghapus fitur "Kenaikan Kelas Tahunan" (§4.5.1 lama beserta tabel `kelas_kenaikan_log`) dari scope — perpindahan kelas siswa antar tahun ajaran kembali dilakukan manual melalui edit siswa biasa (§4.3). Menambahkan fitur **Sinkronisasi Data Dapodik** di Master Data Siswa (§4.3.2) — tombol "Sync Dapodik" yang menarik data siswa dari database Dapodik ke tabel siswa secara berkala.
>
> **Changelog v1.3 → v1.4:** Merombak §3 Design & Frontend agar lebih sesuai tren modern — sidebar diganti dari navy solid ke gelap netral, ditambahkan spesifikasi dark mode, perilaku responsif (desktop/tablet/mobile), pustaka ikon standar (Lucide), state kosong/loading/error, indikator tren pada Card Stats, dan perbaikan kontras warna teks untuk aksesibilitas (WCAG AA). Bagian fitur & database (§4–§9) tidak berubah.

---

## 1. Tujuan Aplikasi

Kesiswaan hadir untuk mendigitalisasi proses pencatatan, pemantauan, dan pelaporan data bimbingan konseling di sekolah. Aplikasi ini menjadi jembatan antara Guru BK, Wali Kelas, dan Siswa dalam satu platform terpusat — menggantikan pencatatan manual yang rawan kehilangan data dan sulit dipantau secara real-time.

**Manfaat utama:**
- Guru BK dapat memantau akumulasi poin pelanggaran siswa secara live
- Wali Kelas mendapat ringkasan kondisi siswa di kelasnya
- Admin memiliki kontrol penuh atas seluruh data master
- Data siswa tersimpan aman dan terstruktur berbasis NIPD/NIS

---

## 2. Pengguna & Hak Akses

| Role | Deskripsi | Hak Akses |
|---|---|---|
| **Admin** | Operator sistem / staf TU | **Full Access** — semua fitur, CRUD semua data |
| Guru BK | Guru BK | Read-only + input pelanggaran *(fase berikutnya)* |
| Wali Kelas | Wali kelas per rombel | Read-only data kelasnya *(fase berikutnya)* |
| Siswa | Peserta didik | Lihat rekap poin diri sendiri *(fase berikutnya)* |

> **Catatan v1.0:** Seluruh hak akses dipegang Admin. Role lain disiapkan strukturnya di database untuk ekspansi berikutnya.

---

## 3. Design & Frontend

> **Perubahan v1.3:** Sidebar diubah dari navy solid ke gelap netral agar terasa lebih kekinian, ditambahkan spesifikasi dark mode, perilaku responsif (mobile/tablet), pustaka ikon standar, state kosong/loading/error, indikator tren pada Card Stats, dan validasi kontras warna teks untuk aksesibilitas.

### 3.1 Design System

**Palet Warna — Light Mode (default)**
```
Primary     : #2563EB  (Biru Pendidikan)
Primary Dark: #1D4ED8
Accent      : #10B981  (Hijau — status baik)
Warning     : #F59E0B  (Kuning — perlu perhatian)
Danger      : #EF4444  (Merah — pelanggaran berat)
Background  : #F8FAFC
Surface     : #FFFFFF
Border      : #E2E8F0
Text Primary: #0F172A
Text Muted  : #475569  (diperbarui dari #64748B — rasio kontras 4.5:1 di atas putih, memenuhi WCAG AA untuk teks kecil)
```

**Palet Warna — Dark Mode (baru — v1.3)**
```
Primary     : #3B82F6  (sedikit lebih terang agar tetap kontras di background gelap)
Accent      : #34D399
Warning     : #FBBF24
Danger      : #F87171
Background  : #0F172A
Surface     : #1E293B
Border      : #334155
Text Primary: #F1F5F9
Text Muted  : #94A3B8
```
- Toggle dark mode ditempatkan di Topbar (ikon bulan/matahari), preferensi disimpan per user (localStorage atau kolom `preferensi_tema` di tabel `users`)
- Warna badge poin (§3.3) tetap memakai versi light-nya di dark mode tapi dengan opacity background diturunkan agar tidak menyilaukan (mis. `bg #DCFCE7` → `bg rgba(22,163,74,0.15)` dengan teks `#4ADE80`)

**Tipografi**
- Font: `Inter` (Google Fonts) — weight 400 / 500 / 600 / 700
- Base size: 14px
- Heading h1: 24px · h2: 20px · h3: 16px

**Ikon (baru — v1.3)**
- Pustaka ikon: **Lucide Icons** (open-source, ringan, gaya garis konsisten — banyak dipakai produk modern seperti Vercel, Linear, shadcn/ui)
- Ukuran standar: 20px (menu sidebar), 16px (tombol/badge inline), 24px (empty state/ilustrasi kecil)
- Stroke width konsisten 1.5–2px di seluruh aplikasi, jangan campur gaya outline dan filled

**Border radius:** 8px (card), 6px (input/button), 4px (badge)
**Shadow card:** `0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)` (light mode) — di dark mode, shadow diganti border 1px `#334155` karena shadow kurang terlihat di atas background gelap

---

### 3.2 Layout Utama

```
┌─────────────────────────────────────────────────────────┐
│  SIDEBAR (240px fixed)     │  MAIN CONTENT AREA          │
│                            │                             │
│  [Logo Kesiswaan]           │  [Topbar: judul + user]     │
│  ──────────────            │  ─────────────────────────  │
│  • Dashboard               │                             │
│  • Master Siswa            │  [Konten halaman aktif]     │
│  • Master User             │                             │
│  • Master Kelas            │                             │
│  • Poin Pelanggaran        │                             │
│  ──────────────            │                             │
│  • Logout                  │                             │
└─────────────────────────────────────────────────────────┘
```

**Sidebar (diperbarui — v1.3):**
- Background `#0F172A` (slate-900, gelap netral) — **diganti dari navy solid `#1E3A5F`** yang terkesan gaya dashboard admin lama; slate netral lebih selaras dengan tren produk modern 2024–2025 dan tetap kontras tinggi dengan teks putih
- Item aktif: background `#2563EB` dengan border-left aksen 3px warna primary-dark, teks putih, ikon ikut berubah warna
- Item hover: background `rgba(255,255,255,0.06)`, transisi halus 150ms
- Icon (Lucide, 20px) + label setiap menu item
- **Collapsible (baru — v1.3):** sidebar dapat dilipat jadi mode ikon-saja (64px) lewat tombol toggle di bagian atas, untuk memberi ruang lebih pada konten di layar sedang

**Topbar:**
- Background putih (dark mode: `#1E293B`), border-bottom `#E2E8F0` (dark mode: `#334155`)
- Kiri: Judul halaman aktif (bold)
- Kanan: toggle dark mode + avatar + nama user + dropdown logout

**Perilaku Responsif (baru — v1.3):**
- **Desktop (≥1024px):** sidebar fixed 240px seperti mockup di atas
- **Tablet (768px–1023px):** sidebar otomatis collapse ke mode ikon-saja (64px), expand sementara saat di-hover/tap
- **Mobile (<768px):** sidebar disembunyikan, diganti tombol hamburger di Topbar yang membuka sidebar sebagai overlay/drawer dari kiri; tabel data di halaman list beralih ke tampilan kartu (card list) per baris agar tidak perlu scroll horizontal

---

### 3.3 Komponen UI Reusable

**Card Stats (Dashboard) — diperbarui v1.3**
```
┌─────────────────────┐
│  [Ikon]  Label      │
│  Angka besar (32px) │
│  ▲ 12% dari bulan lalu │
└─────────────────────┘
```
- Baris ketiga kini menampilkan **indikator tren**: panah naik (hijau `#16A34A`) atau turun (merah `#DC2626`) + persentase perubahan dibanding periode sebelumnya, bukan sekadar sub-label statis
- Untuk kartu di mana tren "naik" justru buruk (mis. Total Pelanggaran, Siswa Bermasalah), warna panah dibalik secara semantik: naik = merah, turun = hijau

**Data Table**
- Header: background `#F1F5F9` (dark mode: `#1E293B`), teks uppercase kecil, font-weight 600
- Row hover: `#F8FAFC` (dark mode: `rgba(255,255,255,0.03)`)
- Row stripe: ringan setiap baris genap
- Kolom aksi: tombol Edit (biru outline) + Hapus (merah outline), ukuran kecil, ikon Lucide (pencil/trash) + label
- Pagination: prev/next + info "Menampilkan X–Y dari Z data"
- **Default page size: 25 baris/halaman** (dapat diubah user ke 10/25/50/100)
- **Default sorting:** berdasarkan kolom yang paling relevan per modul (mis. siswa → Nama A-Z, pelanggaran → Tanggal terbaru); header kolom dapat diklik untuk sort manual
- **Di layar mobile:** tabel beralih ke daftar kartu vertikal (lihat §3.2 Perilaku Responsif)

**Form Input**
- Label di atas input, bukan placeholder saja
- Border `#CBD5E1` → focus border `#2563EB` + shadow biru tipis
- Error state: border merah + pesan error di bawah field
- Upload foto: drag-and-drop area atau klik, preview thumbnail 60×60px

**Badge / Chip Poin**
- Rendah (0–25): `bg #DCFCE7` teks `#16A34A`
- Sedang (26–50): `bg #FEF9C3` teks `#CA8A04`
- Tinggi (51–75): `bg #FED7AA` teks `#EA580C`
- Kritis (>75): `bg #FEE2E2` teks `#DC2626`
- (Versi dark mode: lihat §3.1)

**Alert / Notifikasi**
- Success: border-left 4px hijau, bg `#F0FDF4`
- Error: border-left 4px merah, bg `#FFF1F2`
- Warning: border-left 4px kuning, bg `#FFFBEB`
- Auto-dismiss 3 detik atau klik ×

**State Kosong, Loading, dan Error (baru — v1.3)**
- **Empty state:** setiap tabel/list yang datanya kosong menampilkan ilustrasi/ikon sederhana (mis. ikon Lucide `inbox` atau `users` berukuran 48px, abu-abu muda) + teks singkat ("Belum ada data siswa") + tombol aksi utama halaman tersebut (mis. "+ Tambah Siswa"), bukan sekadar tabel kosong tanpa keterangan
- **Loading state:** saat data sedang dimuat (query, submit form, sync Dapodik), tampilkan skeleton placeholder (baris abu-abu berkedip halus) menggantikan tabel/card, bukan spinner polos di tengah layar — memberi kesan halaman sudah "terbentuk" sebelum data terisi
- **Error state:** kegagalan memuat data (mis. koneksi DB Dapodik gagal saat sync) ditampilkan sebagai alert error (lihat komponen Alert di atas) lengkap dengan tombol "Coba Lagi", bukan halaman putih kosong atau pesan error PHP mentah

---

## 4. Core Features (v1.0)

### 4.1 Autentikasi

- Halaman login full-page: logo Kesiswaan, form username + password
- Guru menggunakan **akun belajar.id** sebagai username (format: `nama@belajar.id`)
- Admin menggunakan username lokal (bebas format)
- Session PHP native, remember-me opsional
- Redirect ke dashboard setelah login berhasil
- Proteksi: semua halaman require session aktif

**Spesifikasi keamanan (baru — v1.1):**
- Password **wajib** disimpan dengan `password_hash()` (algoritma default bcrypt/Argon2i); **tidak boleh** MD5, SHA1, atau plaintext
- Verifikasi login menggunakan `password_verify()`
- **Rate limiting login:** maksimal 5 percobaan gagal per username dalam 15 menit → lock sementara 15 menit, tampilkan pesan generik ("username atau password salah") tanpa membocorkan username valid/tidak
- Session diregenerasi (`session_regenerate_id(true)`) setiap kali login berhasil, untuk mencegah session fixation
- Cookie session: `HttpOnly`, `Secure` (jika HTTPS), `SameSite=Lax`
- Remember-me (jika diaktifkan): token acak disimpan hashed di DB (tabel `remember_tokens`), bukan menyimpan session ID langsung di cookie

---

### 4.2 Dashboard

**Summary Cards (baris atas):**
| Card | Isi |
|---|---|
| Total Siswa | Jumlah siswa aktif seluruh angkatan |
| Total Kelas | Jumlah rombongan belajar |
| Total Pelanggaran | Total kejadian tercatat bulan ini |
| Siswa Bermasalah | Siswa dengan poin > threshold kritis |

**Top 10 Siswa Poin Tertinggi (tabel utama dashboard):**
Kolom: Rank · Foto (thumbnail 36px) · Nama Siswa · NIPD/NIS · Kelas · Total Poin · Status Badge · Tanggal Pelanggaran Terakhir

- Diurutkan DESC berdasarkan total poin akumulasi
- Baris rank 1–3 mendapat highlight subtle (background lebih terang)
- Klik nama siswa → link ke detail siswa

**Grafik Pelanggaran per Bulan:**
- Bar chart sederhana 6 bulan terakhir
- Implementasi dengan `Chart.js` via CDN (ringan, tanpa dependensi berat)

**Klarifikasi threshold & siklus poin (baru — v1.1):**
- Nilai "threshold kritis" untuk kartu **Siswa Bermasalah** **dikonfigurasi oleh Admin** (bukan hardcode), disimpan di tabel `settings` (key: `threshold_poin_kritis`, default: `76` — selaras dengan batas bawah badge "Kritis" di §3.3)
- **Siklus akumulasi poin: per tahun ajaran.** Total poin yang ditampilkan di dashboard, badge, dan detail siswa dihitung ulang dari awal setiap pergantian tahun ajaran (mengacu ke `tahun_ajaran` pada tabel `kelas`). Riwayat pelanggaran tahun ajaran sebelumnya tetap tersimpan dan dapat dilihat sebagai arsip di detail siswa, tapi tidak diakumulasi ke total poin tahun berjalan
- Admin dapat menjalankan proses "Tutup Tahun Ajaran" (menu terpisah, ekspansi selanjutnya) untuk mengarsipkan data poin sebelum tahun ajaran baru dimulai

---

### 4.3 Master Data Siswa

**Tabel Daftar Siswa:**
Kolom: Foto · NIPD/NIS · Nama Lengkap · Kelas · Jenis Kelamin · Total Poin · Aksi

**Form Tambah/Edit Siswa:**

| Field | Tipe | Validasi |
|---|---|---|
| NIPD / NIS | Text | Required, unik, max 20 karakter |
| Nama Lengkap | Text | Required, max 100 karakter |
| Jenis Kelamin | Select | L / P |
| Kelas | Select | FK ke master kelas |
| Tempat Lahir | Text | Opsional |
| Tanggal Lahir | Date | Opsional |
| Nama Orang Tua | Text | Opsional |
| No. HP Orang Tua | Text | Opsional. **Format wajib dinormalisasi** ke `08xxxxxxxxxx` atau `+62xxxxxxxxxx` saat disimpan (validasi & strip karakter non-digit di server), agar siap dipakai untuk integrasi WhatsApp Gateway (lihat §9 poin 3) tanpa perlu pembersihan data ulang |
| Foto | File Upload | JPG/PNG, max 500KB, diresize ke 150×150px |
| Alamat | Textarea | Opsional |
| Status | Select | Aktif / Tidak Aktif / Pindah / Lulus |

**Fitur Pendukung:**
- Search real-time by nama / NIPD
- Filter by kelas dan status
- **Import siswa via CSV (kolom: NIPD, Nama, Kelas, JK) — dinaikkan prioritas ke v1.0** *(lihat §4.3.1 di bawah — sebelumnya ditandai ekspansi selanjutnya; input manual satu-satu tidak realistis untuk ratusan siswa saat onboarding awal)*
- Export ke PDF / Excel — tetap ekspansi selanjutnya

**Spesifikasi validasi upload foto (baru — v1.1):**
- Validasi **MIME type asli file** di server (mis. `finfo_file()`), bukan hanya ekstensi nama file
- File disimpan dengan nama acak (mis. hash + timestamp), bukan nama asli, untuk mencegah overwrite dan path traversal
- Ukuran & tipe divalidasi sebelum proses resize; tolak file yang gagal validasi dengan pesan error jelas

#### 4.3.1 Import Siswa via CSV (baru — v1.1, dipindah dari ekspansi ke v1.0)

- Halaman terpisah: `/siswa/import`
- Upload file CSV dengan kolom wajib: `NIPD, Nama, Kelas, JK`
- Preview data hasil parsing sebelum disimpan (tampilkan tabel + baris bermasalah ditandai merah)
- Validasi per baris: NIPD unik & tidak kosong, Kelas harus cocok dengan data master kelas (jika tidak ditemukan → baris ditolak, bukan membuat kelas baru otomatis)
- Hasil akhir: ringkasan "X berhasil diimpor, Y ditolak" + opsi unduh laporan baris yang gagal

#### 4.3.2 Sinkronisasi Data Dapodik (baru — v1.3)

Selain Import CSV manual (§4.3.1), Master Data Siswa mendapat tombol **[Sync Dapodik]** yang menarik data siswa langsung dari database Dapodik sekolah (sumber data resmi Kemendikbud) ke tabel `siswa`, tanpa perlu ekspor-impor file manual.

**Halaman:** `/siswa/sync-dapodik`

- Koneksi ke database Dapodik dikonfigurasi terpisah oleh Admin (host, nama database, kredensial read-only) — disimpan di `config/dapodik_db.php`, **bukan** di tabel `settings` aplikasi utama, karena berisi kredensial database eksternal
- Admin menekan tombol **[Sync Dapodik]** untuk memulai penarikan data secara manual (on-demand); penjadwalan otomatis (cron) dapat ditambahkan di fase berikutnya jika dibutuhkan
- Pemetaan kolom minimal dari Dapodik: NISN/NIPD, Nama, Jenis Kelamin, Kelas/Rombel, Tanggal Lahir, Nama Orang Tua — field yang tidak tersedia di Dapodik (mis. Foto, Alamat, No. HP Orang Tua) tetap dikelola manual di aplikasi dan **tidak ditimpa** oleh proses sync
- **Aturan pencocokan data:** siswa dicocokkan berdasarkan NIPD/NISN.
  - Jika NIPD sudah ada di tabel `siswa` → data biodata (nama, kelas, dll.) **diperbarui** (bukan foto/alamat/HP ortu, lihat poin di atas)
  - Jika NIPD belum ada → dibuat baris siswa baru dengan status default "Aktif"
  - Siswa yang ada di aplikasi tapi **tidak lagi muncul** di data Dapodik **tidak dihapus otomatis** — hanya ditandai di layar hasil sync sebagai "tidak ditemukan di Dapodik" agar Admin meninjau manual (mis. cek apakah perlu diubah statusnya jadi Pindah/Lulus)
  - Jika `Kelas/Rombel` dari Dapodik tidak cocok dengan data master `kelas` di aplikasi, baris tersebut ditandai gagal dan tidak diproses otomatis (sama seperti aturan Import CSV di §4.3.1) — Admin perlu menyesuaikan master kelas terlebih dahulu
- Setelah proses selesai, ditampilkan ringkasan: jumlah siswa baru ditambahkan, jumlah diperbarui, jumlah gagal/ditandai, dengan rincian per baris
- Setiap proses sync dicatat di tabel `dapodik_sync_log` (kapan, oleh siapa, jumlah baru/update/gagal) untuk keperluan audit — lihat §5.1 poin 12

---

### 4.4 Master Data User

**Sub-menu User dibagi berdasarkan role:**

**Tab / Filter: Admin | Guru BK | Wali Kelas | Siswa**

**Form Tambah User:**

| Field | Tipe | Keterangan |
|---|---|---|
| Nama Lengkap | Text | Required |
| Username | Text | Untuk admin: bebas; untuk guru: format `nama@belajar.id` |
| Password | Password | Min 8 karakter; hidden by default |
| Role | Select | Admin / Guru BK / Wali Kelas / Siswa |
| Kelas Diampu | Select | Muncul jika role = Wali Kelas |
| NIP (guru) | Text | Opsional, untuk Guru BK |
| Status | Select | Aktif / Nonaktif |

**Catatan Akun Guru:**
- Username wajib format `@belajar.id` jika role Guru BK
- Validasi format email `belajar.id` di sisi server (PHP)

**Tabel User:**
Kolom: Nama · Username · Role Badge · Kelas (jika wali kelas) · Status · Aksi

**Catatan relasi wali kelas (baru — v1.1):** lihat §5.1 — field "Kelas Diampu" di form ini adalah satu-satunya tempat relasi user↔kelas diisi (bukan dari sisi master kelas), untuk menghindari dua sumber kebenaran.

---

### 4.5 Master Kelas

**Tabel Kelas:**
Kolom: Nama Kelas · Tingkat · Wali Kelas · Jumlah Siswa · Aksi

**Form Tambah/Edit Kelas:**

| Field | Tipe | Keterangan |
|---|---|---|
| Nama Kelas | Text | Contoh: X IPA 1, XI IPS 2 |
| Tingkat | Select | X / XI / XII (SMA) atau VII/VIII/IX (SMP) |
| Wali Kelas | *(read-only, ditampilkan otomatis)* | Diisi dari data user dengan role Wali Kelas yang memilih kelas ini di §4.4 — **bukan input langsung di form ini** |
| Tahun Ajaran | Text | Contoh: 2024/2025 |

> **Perubahan v1.1:** Kolom "Wali Kelas" di form Master Kelas diubah menjadi tampilan read-only (bukan dropdown input). Penunjukan wali kelas dilakukan dari Master User (§4.4) agar hanya ada satu arah relasi. Ini mencegah data tidak konsisten jika dua tempat input saling menimpa.

> **Catatan v1.3:** Fitur "Kenaikan Kelas Tahunan" yang sebelumnya direncanakan di sini (v1.2) telah **dihapus dari scope**. Perpindahan siswa ke kelas baru setiap tahun ajaran dilakukan manual melalui form Edit Siswa (§4.3) satu per satu, atau lewat Import CSV/Sync Dapodik (§4.3.1–4.3.2) jika data kelas terbaru sudah tersedia dari sumbernya.

---

### 4.6 Master Poin Pelanggaran

**Tabel Jenis Pelanggaran:**
Kolom: Kode · Nama Pelanggaran · Kategori · Bobot Poin · Deskripsi · Aksi

**Form Tambah/Edit Pelanggaran:**

| Field | Tipe | Keterangan |
|---|---|---|
| Kode | Text | Auto-generate atau manual, contoh: PLG-001 |
| Nama Pelanggaran | Text | Required, max 150 karakter |
| Kategori | Select | Kedisiplinan / Tata Krama / Kekerasan / Narkoba / Lainnya |
| Bobot Poin | Number | 1–100, required |
| Deskripsi | Textarea | Penjelasan detail pelanggaran |
| Konsekuensi | Textarea | Tindakan yang diambil sekolah |

**Skala Poin (referensi):**
- 1–10 poin: Pelanggaran ringan (terlambat, seragam tidak lengkap)
- 11–25 poin: Pelanggaran sedang (membolos, bertengkar)
- 26–50 poin: Pelanggaran berat (merusak fasilitas, intimidasi)
- 51–100 poin: Pelanggaran sangat berat (kekerasan fisik, narkoba)

---

### 4.7 Pencatatan Pelanggaran Siswa

**Form Input Pelanggaran:**

| Field | Tipe | Keterangan |
|---|---|---|
| Siswa | Select / Search | Cari by nama atau NIPD |
| Jenis Pelanggaran | Select | FK ke master pelanggaran (poin auto-fill) |
| Poin (auto-fill, dapat diubah) | Number | **Baru v1.1** — nilai default diambil dari `jenis_pelanggaran.bobot_poin`, namun dapat di-override manual oleh Guru BK/Admin untuk kasus khusus (mis. pelanggaran berulang, faktor pemberat/peringan). Jika diubah dari default, nilai asli dan alasan override **wajib** dicatat di kolom `keterangan`, dan sistem otomatis mencatat baris audit (lihat §4.7.1) |
| Tanggal Kejadian | Date | Default: hari ini |
| Lokasi Kejadian | Text | Opsional |
| Keterangan | Textarea | Detail kejadian |
| Tindakan Diambil | Textarea | Tindak lanjut Guru BK |
| Pelapor | *(auto-fill, read-only)* | **Diperbaiki v1.1** — diambil otomatis dari `user_id` sesi login (FK ke tabel `users`), **bukan input teks bebas** seperti v1.0 |

**Riwayat Pelanggaran Siswa:**
- Diakses dari detail halaman siswa
- Tabel: Tanggal · Pelanggaran · Poin · Pelapor · Keterangan
- Total poin akumulasi ditampilkan di header (mengikuti siklus tahun ajaran berjalan, lihat §4.2)

#### 4.7.1 Audit Log — Override Poin (baru — v1.2, dipindah dari roadmap ke v1.0)

Override poin adalah aksi sensitif yang bisa disalahgunakan, sehingga pencatatannya **tidak ditunda ke fase audit log umum** (dulu di §9 poin 8) — minimal untuk kasus ini dicatat sejak v1.0/v1.2:

- Setiap kali poin diubah dari default `jenis_pelanggaran.bobot_poin`, sistem menyimpan baris di tabel `audit_poin` berisi: siapa yang mengubah (`user_id`), kapan, nilai default, nilai final, dan alasan (disalin dari field `keterangan`)
- Audit log untuk aksi lain (edit/hapus data master secara umum) tetap berada di roadmap §9 sebagai fitur menyeluruh fase berikutnya — cakupan v1.0/v1.2 dibatasi khusus pada override poin karena risikonya paling tinggi

---

## 5. Struktur Database (Diperbarui — v1.3)

### 5.1 Perubahan Kunci dari v1.0

1. **Relasi wali kelas** — dulu ada dua arah (`users.kelas_id` dan `kelas.wali_kelas_id`), berpotensi tidak sinkron. Sekarang: **hanya `kelas.wali_kelas_id`** yang menjadi sumber kebenaran (FK ke `users.id`, nullable). Kolom `users.kelas_id` dihapus untuk role Wali Kelas; untuk role Siswa, relasi ke kelas disimpan di `siswa.kelas_id` (bukan di `users`).
2. **Soft-delete** — tabel `siswa`, `users`, `kelas`, `jenis_pelanggaran` mendapat kolom `deleted_at` (nullable timestamp). Aksi "Hapus" di UI melakukan soft-delete, bukan `DELETE` permanen, agar riwayat pelanggaran & laporan historis tidak rusak akibat data induk hilang.
3. **Pelapor pada pelanggaran** — `pelanggaran_siswa.pelapor_id` tetap FK ke `users.id`, diisi otomatis dari sesi (lihat §4.7), konsisten dengan tipe data (sebelumnya form menyebutnya "Text" — sudah diperbaiki).
4. **Poin override** — `pelanggaran_siswa` mendapat kolom `poin_final` (poin yang benar-benar tercatat, bisa beda dari `jenis_pelanggaran.bobot_poin` jika di-override) agar `bobot_poin` di master tidak berubah retroaktif memengaruhi riwayat lama. Setiap override dicatat di tabel `audit_poin` (§4.7.1).
5. **Tahun ajaran pada pelanggaran** — kolom `tahun_ajaran` ditambahkan ke `pelanggaran_siswa` (disalin dari kelas siswa saat pencatatan) agar query akumulasi per tahun ajaran efisien tanpa join berlapis.
6. **Tabel `settings`** — baru, untuk konfigurasi seperti `threshold_poin_kritis` (lihat §4.2).
7. **Tabel `remember_tokens`** — baru, untuk fitur remember-me yang aman (lihat §4.1).
8. **Indeks** — ditambahkan pada kolom yang sering di-filter/join: `siswa.nipd` (unique), `siswa.kelas_id`, `pelanggaran_siswa.siswa_id`, `pelanggaran_siswa.tanggal`, `users.username` (unique).
9. **Unique constraint vs soft-delete (baru — v1.2)** — kolom `siswa.nipd`, `users.username`, dan `jenis_pelanggaran.kode` diberi `UNIQUE` **permanen**, termasuk untuk baris yang sudah soft-deleted. Artinya: NIPD, username, atau kode pelanggaran yang pernah dipakai **tidak boleh didaftarkan ulang** ke entitas lain, meski pemilik lamanya sudah dihapus (soft-delete). Ini sengaja dipilih ketimbang unique constraint bersyarat (partial unique), karena MySQL tidak mendukungnya secara native dan solusi generated-column menambah kompleksitas yang tidak sepadan untuk kasus penggunaan sekolah — NIPD/NIS resmi dari Dapodik pada praktiknya memang tidak didaur ulang.
10. **Tabel `audit_poin` (baru — v1.2)** — mencatat setiap kejadian override poin pelanggaran (lihat §4.7.1).
11. ~~**Tabel `kelas_kenaikan_log`**~~ — **dihapus di v1.3** bersamaan dengan fitur Kenaikan Kelas Tahunan (lihat §4.5).
12. **Tabel `dapodik_sync_log` (baru — v1.3)** — mencatat riwayat setiap proses sinkronisasi data Dapodik (lihat §4.3.2), untuk keperluan audit jika ada pertanyaan soal asal-usul perubahan data siswa.

### 5.2 Skema SQL (Ringkasan)

```sql
-- Pengaturan sistem
settings (
  id, key_name UNIQUE, value, updated_at
)
-- contoh row: ('threshold_poin_kritis', '76')

-- Token remember-me (hashed, bukan session id mentah)
remember_tokens (
  id, user_id FK->users.id, token_hash, expires_at, created_at
)

-- User (admin, guru bk, wali kelas, siswa-login)
users (
  id, nama, username UNIQUE, password_hash, role,
  nip, status, deleted_at, created_at, updated_at
)
-- Catatan: kolom kelas_id DIHAPUS dari sini (lihat §5.1 poin 1)

-- Kelas / rombel
kelas (
  id, nama_kelas, tingkat, wali_kelas_id FK->users.id NULLABLE,
  tahun_ajaran, deleted_at, created_at, updated_at
)

-- Data siswa
siswa (
  id, nipd UNIQUE, nama, jenis_kelamin, kelas_id FK->kelas.id,
  tempat_lahir, tanggal_lahir, nama_ortu, hp_ortu, foto, alamat,
  status, deleted_at, created_at, updated_at
)
INDEX (kelas_id), INDEX (status)

-- Master jenis pelanggaran
jenis_pelanggaran (
  id, kode UNIQUE, nama, kategori, bobot_poin, deskripsi,
  konsekuensi, deleted_at, created_at, updated_at
)

-- Catatan pelanggaran siswa
pelanggaran_siswa (
  id, siswa_id FK->siswa.id, jenis_pelanggaran_id FK->jenis_pelanggaran.id,
  poin_final,              -- poin aktual tercatat (bisa override, lihat §5.1 poin 4)
  tahun_ajaran,            -- disalin dari kelas siswa saat input (§5.1 poin 5)
  tanggal, lokasi, keterangan, tindakan,
  pelapor_id FK->users.id,
  created_at
)
INDEX (siswa_id), INDEX (tanggal), INDEX (tahun_ajaran)

-- Audit khusus override poin (baru v1.2)
audit_poin (
  id, pelanggaran_siswa_id FK->pelanggaran_siswa.id,
  poin_default, poin_final, alasan,
  changed_by FK->users.id, changed_at
)

-- Riwayat proses sinkronisasi data Dapodik (baru v1.3)
dapodik_sync_log (
  id, jumlah_baru, jumlah_diperbarui, jumlah_gagal,
  dilakukan_oleh FK->users.id, created_at
)
```

---

## 6. User Flow — Admin

### 6.1 Login
```
Buka /login
  → Isi username + password
  → [PHP session check + rate limit check]
  → Berhasil: regenerate session ID, redirect /dashboard
  → Gagal: pesan error generik, form tetap tampil
  → 5x gagal dalam 15 menit: akun terkunci sementara
```

### 6.2 Dashboard
```
/dashboard
  → Tampil 4 summary card (query COUNT, threshold dari tabel settings)
  → Tampil tabel Top 10 siswa poin tertinggi (akumulasi tahun ajaran berjalan)
  → Tampil bar chart 6 bulan (data dari query GROUP BY MONTH)
```

### 6.3 Kelola Master Siswa
```
/siswa (daftar)
  → Search / filter kelas
  → Klik [+ Tambah Siswa] → /siswa/tambah
      → Isi form → Upload foto → Submit
      → Validasi server (NIPD unik, MIME type & size foto)
      → Berhasil: redirect /siswa + flash "Siswa berhasil ditambahkan"
      → Gagal: kembali ke form + highlight error
  → Klik [Edit] → /siswa/edit/{id} → proses sama
  → Klik [Hapus] → konfirmasi modal → soft-delete (set deleted_at) → reload tabel
  → Klik nama siswa → /siswa/detail/{id}
      → Info lengkap + foto
      → Riwayat pelanggaran (tabel, termasuk arsip tahun ajaran lalu)
      → Total poin akumulasi tahun ajaran berjalan
      → Tombol [+ Catat Pelanggaran]
  → Klik [Import CSV] → /siswa/import
      → Upload file → preview & validasi per baris → konfirmasi → simpan
      → Ringkasan hasil + unduh laporan baris gagal
  → Klik [Sync Dapodik] → /siswa/sync-dapodik
      → Sistem terhubung ke DB Dapodik → tarik data → cocokkan by NIPD/NISN
      → Tampilkan ringkasan: baru ditambahkan / diperbarui / gagal (kelas tidak cocok) / tidak ditemukan di Dapodik
      → Simpan hasil ke dapodik_sync_log
```

### 6.4 Kelola Master User
```
/user (daftar, default tab: semua)
  → Filter by role (tab)
  → Klik [+ Tambah User] → /user/tambah
      → Pilih role → field relevan muncul dinamis
      → Jika role Wali Kelas: pilih "Kelas Diampu" → otomatis set kelas.wali_kelas_id
      → Jika role Guru: validasi username format @belajar.id
      → Submit → validasi → password di-hash → redirect + flash
  → Klik [Edit] → /user/edit/{id}
  → Klik [Hapus] → konfirmasi → soft-delete
  → Reset password: ikon kunci → set password baru → di-hash ulang
```

### 6.5 Kelola Kelas
```
/kelas (daftar)
  → CRUD kelas (tambah, edit, hapus/soft-delete)
  → Field Wali Kelas tampil read-only (diisi dari §6.4)
  → Klik nama kelas → daftar siswa di kelas tersebut
```

### 6.6 Kelola Poin Pelanggaran
```
/pelanggaran-master (daftar jenis)
  → CRUD jenis pelanggaran (hapus = soft-delete agar riwayat lama tetap valid)
  → Tabel sortable by bobot poin
```

### 6.7 Catat Pelanggaran Siswa
```
/pelanggaran/tambah
  → Search siswa (AJAX / form biasa)
  → Pilih jenis pelanggaran → poin auto-tampil (dapat diubah manual jika perlu)
  → Isi detail → Pelapor otomatis dari sesi login
  → Submit → simpan poin_final + tahun_ajaran → redirect ke detail siswa + flash sukses
```

### 6.8 Logout
```
Klik avatar → dropdown → [Logout]
  → session_destroy() + hapus remember_token jika ada
  → redirect /login
```

---

## 7. Halaman & Route (PHP Native)

| Route | File PHP | Deskripsi |
|---|---|---|
| `/login` | `login.php` | Halaman autentikasi |
| `/dashboard` | `dashboard.php` | Dashboard utama |
| `/siswa` | `siswa/index.php` | Daftar siswa |
| `/siswa/tambah` | `siswa/tambah.php` | Form tambah siswa |
| `/siswa/edit/{id}` | `siswa/edit.php?id=` | Form edit siswa |
| `/siswa/detail/{id}` | `siswa/detail.php?id=` | Detail + riwayat pelanggaran |
| `/siswa/import` | `siswa/import.php` | **Baru v1.1** — Import CSV massal |
| `/siswa/sync-dapodik` | `siswa/sync_dapodik.php` | **Baru v1.3** — Sinkronisasi data dari database Dapodik |
| `/user` | `user/index.php` | Daftar user |
| `/user/tambah` | `user/tambah.php` | Form tambah user |
| `/user/edit/{id}` | `user/edit.php?id=` | Form edit user |
| `/kelas` | `kelas/index.php` | Master kelas |
| `/pelanggaran-master` | `pelanggaran/master.php` | Jenis pelanggaran |
| `/pelanggaran/tambah` | `pelanggaran/tambah.php` | Input pelanggaran siswa |
| `/pengaturan` | `pengaturan.php` | **Baru v1.1** — Konfigurasi threshold poin kritis |
| `/logout` | `logout.php` | Destroy session |

> **Catatan implementasi:** Route berformat path (`/siswa/edit/{id}`) memerlukan URL rewrite (`.htaccess` + `mod_rewrite`, atau konfigurasi setara di Dokploy/Nginx) agar file PHP native dengan query string (`edit.php?id=`) dapat diakses dengan URL bersih sesuai tabel di atas.

---

## 8. Struktur Folder PHP

```
kesiswaan/
├── index.php               ← redirect ke /login atau /dashboard
├── login.php
├── logout.php
├── dashboard.php
├── pengaturan.php           ← baru: konfigurasi threshold poin
├── config/
│   ├── db.php              ← koneksi PDO (WAJIB prepared statements)
│   └── dapodik_db.php      ← baru v1.3: koneksi read-only ke DB Dapodik
├── includes/
│   ├── header.php          ← sidebar + topbar HTML
│   ├── footer.php          ← closing tags + scripts
│   ├── auth.php            ← session check, middleware, rate limiting
│   └── csrf.php            ← baru: generate & validasi CSRF token
├── siswa/
│   ├── index.php
│   ├── tambah.php
│   ├── edit.php
│   ├── detail.php
│   ├── hapus.php           ← soft-delete
│   ├── import.php          ← baru: import CSV
│   └── sync_dapodik.php    ← baru v1.3: sinkronisasi dari DB Dapodik
├── user/
│   ├── index.php
│   ├── tambah.php
│   ├── edit.php
│   └── hapus.php           ← soft-delete
├── kelas/
│   ├── index.php
│   ├── tambah.php
│   └── edit.php
├── pelanggaran/
│   ├── master.php
│   └── tambah.php
├── assets/
│   ├── css/
│   │   └── style.css       ← custom CSS di atas Bootstrap/Tailwind CDN
│   ├── js/
│   │   └── main.js         ← confirm dialog, alert dismiss, dsb.
│   └── uploads/
│       └── foto_siswa/     ← foto siswa (writable, gitignore, nama file acak)
└── sql/
    └── kesiswaan.sql        ← schema v1.1 + data seed awal
```

**Catatan keamanan implementasi (baru — v1.1):**
- Semua query DB menggunakan **PDO dengan prepared statements** — tidak ada string concatenation SQL langsung dari input user
- Setiap form POST menyertakan **CSRF token** yang divalidasi di `includes/csrf.php` sebelum diproses
- Folder `assets/uploads/` dikonfigurasi agar file di dalamnya **tidak dapat dieksekusi sebagai PHP** (mis. `.htaccess` dengan `php_flag engine off` di dalam folder tersebut)

**Catatan keamanan koneksi Dapodik (baru — v1.3):**
- Kredensial database Dapodik di `config/dapodik_db.php` menggunakan **user database read-only** (tidak diberi hak `INSERT`/`UPDATE`/`DELETE` di sisi Dapodik) — aplikasi Kesiswaan hanya membaca data, tidak pernah menulis balik ke database Dapodik
- Jika Dapodik berada di server/jaringan terpisah, koneksi sebaiknya dibatasi lewat firewall/VPN, bukan dibuka ke publik

**Kebijakan backup data (baru — v1.2):**
- Karena aplikasi menyimpan data pribadi anak (nama, foto, alamat, kontak orang tua, riwayat pelanggaran), backup database (`mysqldump` terjadwal) **wajib berjalan minimal harian**, disimpan di lokasi terpisah dari server aplikasi (mis. object storage atau server backup lain)
- Folder `assets/uploads/foto_siswa/` ikut dicadangkan pada jadwal yang sama agar foto siswa tidak hilang terpisah dari data database-nya
- Retensi backup minimal 30 hari; backup lebih lama dapat dirotasi/dihapus sesuai kebijakan sekolah

---

## 9. Apa yang Dikembangkan Selanjutnya

Berdasarkan struktur v1.1, urutan pengembangan yang disarankan:

1. **Aktivasi role Guru BK** — beri akses input pelanggaran & lihat data kelasnya
2. **Aktivasi role Wali Kelas** — dashboard ringkas per kelas, notifikasi siswa bermasalah
3. **Notifikasi otomatis** — WhatsApp Gateway (Fonnte/Wablas) ke orang tua saat poin melewati threshold
4. **Laporan & Ekspor** — cetak PDF kartu konseling, ekspor Excel rekap bulanan
5. **Menu "Tutup Tahun Ajaran"** — proses formal pengarsipan poin di akhir tahun ajaran (lihat §4.2)
6. **Penjadwalan otomatis Sync Dapodik** — jalankan sinkronisasi via cron terjadwal, bukan hanya manual (lihat §4.3.2)
7. **Surat Panggilan Orang Tua** — generate surat otomatis dari template, unduh PDF
8. **Aktivasi role Siswa** — login siswa lihat rekap poin & riwayat sendiri
9. **Audit log menyeluruh** — rekam siapa mengubah data master apa dan kapan, untuk semua modul (audit khusus override poin sudah masuk scope v1.2, lihat §4.7.1 — poin ini memperluas cakupan ke aksi lain seperti edit/hapus siswa, user, dan kelas)

> **Catatan:** Import CSV siswa yang sebelumnya berada di daftar ini telah dipindahkan ke scope v1.0 (§4.3.1). Fitur Kenaikan Kelas Tahunan yang sempat direncanakan di v1.2 telah dihapus dari scope (lihat §4.5).

---

*Dokumen ini merupakan PRD v1.4 — revisi dari v1.3, merombak bagian Design & Frontend (§3) agar lebih sesuai tren modern (dark mode, responsif, ikon Lucide, state kosong/loading/error, indikator tren, kontras aksesibel). Bagian fitur, database, dan alur bisnis (§4–§9) tidak berubah. Revisi lanjutan dilakukan seiring kebutuhan stakeholder berkembang.*
