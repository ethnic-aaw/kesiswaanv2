package handler

import (
	"database/sql"
	"encoding/json"
	"net/http"
	"strconv"

	"kesiswaan/internal/helper"
	"kesiswaan/internal/middleware"
)

type PelanggaranHandler struct{ DB *sql.DB }

// Jenis
func (h *PelanggaranHandler) ListJenis(w http.ResponseWriter, r *http.Request) {
	rows, err := h.DB.Query(`SELECT id,kode,nama,kategori,bobot_poin,deskripsi,konsekuensi FROM jenis_pelanggaran WHERE deleted_at IS NULL ORDER BY bobot_poin DESC`)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	defer rows.Close()
	type Row struct {
		ID int64 `json:"id"`; Kode string `json:"kode"`; Nama string `json:"nama"`; Kategori string `json:"kategori"`; Bobot int `json:"bobot_poin"`; Deskripsi *string `json:"deskripsi"`; Konsekuensi *string `json:"konsekuensi"`
	}
	var out []Row
	for rows.Next() {
		var x Row
		rows.Scan(&x.ID, &x.Kode, &x.Nama, &x.Kategori, &x.Bobot, &x.Deskripsi, &x.Konsekuensi)
		out = append(out, x)
	}
	if out == nil {
		out = []Row{}
	}
	helper.Success(w, out)
}

func (h *PelanggaranHandler) CreateJenis(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Kode string `json:"kode"`; Nama string `json:"nama"`; Kategori string `json:"kategori"`; Bobot int `json:"bobot_poin"`; Deskripsi *string `json:"deskripsi"`; Konsekuensi *string `json:"konsekuensi"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Nama == "" {
		helper.Error(w, 400, "nama wajib")
		return
	}
	if req.Bobot < 1 || req.Bobot > 100 {
		helper.Error(w, 400, "bobot 1-100")
		return
	}
	if req.Kategori == "" {
		req.Kategori = "Lainnya"
	}
	if req.Kode == "" {
		// auto generate PLG-xxx
		var maxID int
		_ = h.DB.QueryRow(`SELECT COALESCE(MAX(id),0)+1 FROM jenis_pelanggaran`).Scan(&maxID)
		req.Kode = "PLG-" + strconv.Itoa(1000+maxID)[1:]
	}
	res, err := h.DB.Exec(`INSERT INTO jenis_pelanggaran(kode,nama,kategori,bobot_poin,deskripsi,konsekuensi) VALUES(?,?,?,?,?,?)`, req.Kode, req.Nama, req.Kategori, req.Bobot, req.Deskripsi, req.Konsekuensi)
	if err != nil {
		helper.Error(w, 400, err.Error())
		return
	}
	id, _ := res.LastInsertId()
	helper.Created(w, map[string]any{"id": id, "kode": req.Kode})
}

func (h *PelanggaranHandler) UpdateJenis(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var req struct {
		Kode string `json:"kode"`; Nama string `json:"nama"`; Kategori string `json:"kategori"`; Bobot int `json:"bobot_poin"`; Deskripsi *string `json:"deskripsi"`; Konsekuensi *string `json:"konsekuensi"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		helper.Error(w, 400, "payload tidak valid")
		return
	}
	if req.Bobot != 0 && (req.Bobot < 1 || req.Bobot > 100) {
		helper.Error(w, 400, "bobot 1-100")
		return
	}
	_, err := h.DB.Exec(`UPDATE jenis_pelanggaran SET kode=COALESCE(NULLIF(?,''),kode), nama=COALESCE(NULLIF(?,''),nama), kategori=COALESCE(NULLIF(?,''),kategori), bobot_poin=COALESCE(NULLIF(?,0),bobot_poin), deskripsi=?, konsekuensi=? WHERE id=? AND deleted_at IS NULL`, req.Kode, req.Nama, req.Kategori, req.Bobot, req.Deskripsi, req.Konsekuensi, id)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	helper.Message(w, "diperbarui", nil)
}

func (h *PelanggaranHandler) DeleteJenis(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	h.DB.Exec(`UPDATE jenis_pelanggaran SET deleted_at=NOW() WHERE id=?`, id)
	helper.Message(w, "dihapus (soft-delete)", nil)
}

// Catat pelanggaran siswa
func (h *PelanggaranHandler) Create(w http.ResponseWriter, r *http.Request) {
	u := middleware.CurrentUser(r)
	var req struct {
		SiswaID            int64  `json:"siswa_id"`
		JenisPelanggaranID int64  `json:"jenis_pelanggaran_id"`
		Poin               *int   `json:"poin"`
		Tanggal            string `json:"tanggal"`
		Lokasi             *string `json:"lokasi"`
		Keterangan         *string `json:"keterangan"`
		Tindakan           *string `json:"tindakan"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.SiswaID == 0 || req.JenisPelanggaranID == 0 {
		helper.Error(w, 400, "siswa_id dan jenis_pelanggaran_id wajib")
		return
	}
	// get bobot default
	var bobot int
	if err := h.DB.QueryRow(`SELECT bobot_poin FROM jenis_pelanggaran WHERE id=? AND deleted_at IS NULL`, req.JenisPelanggaranID).Scan(&bobot); err != nil {
		helper.Error(w, 404, "jenis pelanggaran tidak ditemukan")
		return
	}
	poinFinal := bobot
	if req.Poin != nil {
		poinFinal = *req.Poin
		if poinFinal < 1 || poinFinal > 100 {
			helper.Error(w, 400, "poin 1-100")
			return
		}
		if poinFinal != bobot && (req.Keterangan == nil || *req.Keterangan == "") {
			helper.Error(w, 400, "override poin wajib isi alasan di keterangan")
			return
		}
	}
	if req.Tanggal == "" {
		// default today
		_ = h.DB.QueryRow(`SELECT CURDATE()`).Scan(&req.Tanggal)
	}
	// get tahun_ajaran from siswa's kelas — reject soft-deleted
	var ta string
	if err := h.DB.QueryRow(`SELECT COALESCE(k.tahun_ajaran,'2024/2025') FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.deleted_at IS NULL`, req.SiswaID).Scan(&ta); err != nil || ta == "" {
		helper.Error(w, 404, "siswa tidak ditemukan / sudah dihapus")
		return
	}
	if ta == "" {
		ta = "2024/2025"
	}
	var pelaporID int64
	if u != nil {
		pelaporID = u.UserID
	}
	res, err := h.DB.Exec(`INSERT INTO pelanggaran_siswa(siswa_id,jenis_pelanggaran_id,poin_final,tahun_ajaran,tanggal,lokasi,keterangan,tindakan,pelapor_id) VALUES(?,?,?,?,?,?,?,?,?)`,
		req.SiswaID, req.JenisPelanggaranID, poinFinal, ta, req.Tanggal, req.Lokasi, req.Keterangan, req.Tindakan, pelaporID)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	id, _ := res.LastInsertId()
	// audit if override
	if poinFinal != bobot {
		alasan := ""
		if req.Keterangan != nil {
			alasan = *req.Keterangan
		}
		h.DB.Exec(`INSERT INTO audit_poin(pelanggaran_siswa_id,poin_default,poin_final,alasan,changed_by) VALUES(?,?,?,?,?)`, id, bobot, poinFinal, alasan, pelaporID)
	}
	helper.Created(w, map[string]any{"id": id, "poin_final": poinFinal, "tahun_ajaran": ta})
}

func (h *PelanggaranHandler) List(w http.ResponseWriter, r *http.Request) {
	siswaID := r.URL.Query().Get("siswa_id")
	q := `SELECT ps.id, ps.siswa_id, COALESCE(jp.nama,'-'), ps.poin_final, ps.tahun_ajaran, ps.tanggal, COALESCE(u.nama,'-'), ps.keterangan FROM pelanggaran_siswa ps LEFT JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id LEFT JOIN users u ON u.id=ps.pelapor_id`
	args := []any{}
	if siswaID != "" {
		q += ` WHERE ps.siswa_id=?`
		args = append(args, siswaID)
	}
	q += ` ORDER BY ps.tanggal DESC LIMIT 100`
	rows, _ := h.DB.Query(q, args...)
	type Row struct {
		ID int64 `json:"id"`; SiswaID int64 `json:"siswa_id"`; Jenis string `json:"jenis"`; Poin int `json:"poin_final"`; TA string `json:"tahun_ajaran"`; Tanggal string `json:"tanggal"`; Pelapor string `json:"pelapor"`; Ket *string `json:"keterangan"`
	}
	var out []Row
	if rows != nil {
		defer rows.Close()
		for rows.Next() {
			var x Row
			rows.Scan(&x.ID, &x.SiswaID, &x.Jenis, &x.Poin, &x.TA, &x.Tanggal, &x.Pelapor, &x.Ket)
			out = append(out, x)
		}
	}
	if out == nil {
		out = []Row{}
	}
	helper.Success(w, out)
}
