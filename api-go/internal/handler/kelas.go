package handler

import (
	"database/sql"
	"encoding/json"
	"net/http"
	"strconv"

	"kesiswaan/internal/helper"
)

type KelasHandler struct{ DB *sql.DB }

func (h *KelasHandler) List(w http.ResponseWriter, r *http.Request) {
	rows, err := h.DB.Query(`SELECT k.id,k.nama_kelas,k.tingkat,k.wali_kelas_id,COALESCE(u.nama,'—'),k.tahun_ajaran,(SELECT COUNT(*) FROM siswa s WHERE s.kelas_id=k.id AND s.deleted_at IS NULL) FROM kelas k LEFT JOIN users u ON u.id=k.wali_kelas_id WHERE k.deleted_at IS NULL ORDER BY k.tingkat,k.nama_kelas`)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	defer rows.Close()
	type Row struct {
		ID int64 `json:"id"`; Nama string `json:"nama_kelas"`; Tingkat string `json:"tingkat"`; WaliID *int64 `json:"wali_kelas_id"`; Wali string `json:"wali"`; TA string `json:"tahun_ajaran"`; Jml int `json:"jml_siswa"`
	}
	var out []Row
	for rows.Next() {
		var x Row
		rows.Scan(&x.ID, &x.Nama, &x.Tingkat, &x.WaliID, &x.Wali, &x.TA, &x.Jml)
		out = append(out, x)
	}
	if out == nil {
		out = []Row{}
	}
	helper.Success(w, out)
}

func (h *KelasHandler) Create(w http.ResponseWriter, r *http.Request) {
	var req struct {
		NamaKelas   string `json:"nama_kelas"`
		Tingkat     string `json:"tingkat"`
		TahunAjaran string `json:"tahun_ajaran"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.NamaKelas == "" {
		helper.Error(w, 400, "nama_kelas wajib")
		return
	}
	if req.TahunAjaran == "" {
		req.TahunAjaran = "2024/2025"
	}
	res, err := h.DB.Exec(`INSERT INTO kelas(nama_kelas,tingkat,tahun_ajaran) VALUES(?,?,?)`, req.NamaKelas, req.Tingkat, req.TahunAjaran)
	if err != nil {
		helper.Error(w, 400, err.Error())
		return
	}
	id, _ := res.LastInsertId()
	helper.Created(w, map[string]any{"id": id})
}

func (h *KelasHandler) Update(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var req struct {
		NamaKelas   string `json:"nama_kelas"`
		Tingkat     string `json:"tingkat"`
		TahunAjaran string `json:"tahun_ajaran"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		helper.Error(w, 400, "payload tidak valid")
		return
	}
	_, err := h.DB.Exec(`UPDATE kelas SET nama_kelas=?,tingkat=?,tahun_ajaran=? WHERE id=? AND deleted_at IS NULL`, req.NamaKelas, req.Tingkat, req.TahunAjaran, id)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	helper.Message(w, "diperbarui", nil)
}

func (h *KelasHandler) Delete(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	_, err := h.DB.Exec(`UPDATE kelas SET deleted_at=NOW() WHERE id=?`, id)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	helper.Message(w, "dihapus (soft-delete)", nil)
}
