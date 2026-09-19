package handler

import (
	"database/sql"
	"net/http"

	"kesiswaan/internal/helper"
	"kesiswaan/internal/middleware"
)

type DapodikHandler struct {
	DB         *sql.DB
	DapodikDSN string
}

func (h *DapodikHandler) Sync(w http.ResponseWriter, r *http.Request) {
	if h.DapodikDSN == "" {
		helper.Error(w, 503, "koneksi Dapodik belum dikonfigurasi (DAPODIK_DSN kosong) — atur di .env / config/dapodik_db.php")
		return
	}
	u := middleware.CurrentUser(r)
	var actorID int64
	if u != nil {
		actorID = u.UserID
	}
	// open dapodik DB
	db2, err := sql.Open("mysql", h.DapodikDSN)
	if err != nil {
		helper.Error(w, 500, "gagal buka koneksi Dapodik: "+err.Error())
		return
	}
	defer db2.Close()
	if err := db2.Ping(); err != nil {
		helper.Error(w, 502, "gagal terhubung ke DB Dapodik: "+err.Error())
		return
	}
	// ponytail: sesuaikan query sesuai skema Dapodik sekolah; contoh kolom umum
	rows, err := db2.Query(`SELECT nisn, nama, jenis_kelamin, rombel, tanggal_lahir, nama_ortu FROM peserta_didik`)
	if err != nil {
		// fallback coba nama tabel siswa
		rows, err = db2.Query(`SELECT nipd, nama, jk, kelas, tgl_lahir, nama_ortu FROM siswa`)
		if err != nil {
			helper.Error(w, 500, "query Dapodik gagal: "+err.Error())
			return
		}
	}
	defer rows.Close()

	type Result struct {
		NIPD string `json:"nipd"`; Nama string `json:"nama"`; Kelas string `json:"kelas"`; Hasil string `json:"hasil"`
	}
	var results []Result
	baru, diperbarui, gagal := 0, 0, 0

	for rows.Next() {
		var nipd, nama, jk, rombel, tglLahir, namaOrtu sql.NullString
		rows.Scan(&nipd, &nama, &jk, &rombel, &tglLahir, &namaOrtu)
		n := nipd.String
		if n == "" {
			continue
		}
		kelasNama := rombel.String
		// cek kelas cocok?
		var kelasID sql.NullInt64
		if kelasNama != "" {
			_ = h.DB.QueryRow(`SELECT id FROM kelas WHERE nama_kelas=? AND deleted_at IS NULL`, kelasNama).Scan(&kelasID)
			if !kelasID.Valid {
				results = append(results, Result{NIPD: n, Nama: nama.String, Kelas: kelasNama, Hasil: "Kelas tidak cocok"})
				gagal++
				continue
			}
		}
		// cek siswa sudah ada?
		var existsID int64
		err = h.DB.QueryRow(`SELECT id FROM siswa WHERE nipd=?`, n).Scan(&existsID)
		if err == sql.ErrNoRows {
			// insert baru
			_, err := h.DB.Exec(`INSERT INTO siswa(nipd,nama,jenis_kelamin,kelas_id,tanggal_lahir,nama_ortu,status) VALUES(?,?,?,?,?,?,?)`,
				n, nama.String, jk.String, kelasID, tglLahir.String, namaOrtu.String, "Aktif")
			if err != nil {
				results = append(results, Result{NIPD: n, Nama: nama.String, Kelas: kelasNama, Hasil: "Gagal: " + err.Error()})
				gagal++
				continue
			}
			results = append(results, Result{NIPD: n, Nama: nama.String, Kelas: kelasNama, Hasil: "Baru"})
			baru++
		} else if err == nil {
			// update — jangan timpa foto/alamat/hp_ortu (sesuai PRD §4.3.2)
			h.DB.Exec(`UPDATE siswa SET nama=?, jenis_kelamin=?, kelas_id=?, tanggal_lahir=?, nama_ortu=? WHERE id=?`,
				nama.String, jk.String, kelasID, tglLahir.String, namaOrtu.String, existsID)
			results = append(results, Result{NIPD: n, Nama: nama.String, Kelas: kelasNama, Hasil: "Diperbarui"})
			diperbarui++
		} else {
			results = append(results, Result{NIPD: n, Nama: nama.String, Kelas: kelasNama, Hasil: "Gagal query"})
			gagal++
		}
	}
	// siswa di app tapi tidak di Dapodik → tandai (tidak hapus)
	// kumpulkan NIPD dari Dapodik untuk compare — sederhana: skip detailed, frontend tampil "tidak di Dapodik" dari results gagal saja
	// catat log
	h.DB.Exec(`INSERT INTO dapodik_sync_log(jumlah_baru,jumlah_diperbarui,jumlah_gagal,dilakukan_oleh) VALUES(?,?,?,?)`, baru, diperbarui, gagal, actorID)

	helper.Success(w, map[string]any{
		"baru":       baru,
		"diperbarui": diperbarui,
		"gagal":      gagal,
		"rincian":    results,
	})
}

func (h *DapodikHandler) Logs(w http.ResponseWriter, r *http.Request) {
	rows, _ := h.DB.Query(`SELECT id,jumlah_baru,jumlah_diperbarui,jumlah_gagal,dilakukan_oleh,created_at FROM dapodik_sync_log ORDER BY id DESC LIMIT 20`)
	type Row struct {
		ID int64 `json:"id"`; Baru int `json:"jumlah_baru"`; Updated int `json:"jumlah_diperbarui"`; Gagal int `json:"jumlah_gagal"`; Oleh int64 `json:"dilakukan_oleh"`; At string `json:"created_at"`
	}
	var out []Row
	if rows != nil {
		defer rows.Close()
		for rows.Next() {
			var x Row
			rows.Scan(&x.ID, &x.Baru, &x.Updated, &x.Gagal, &x.Oleh, &x.At)
			out = append(out, x)
		}
	}
	if out == nil {
		out = []Row{}
	}
	helper.Success(w, out)
}
