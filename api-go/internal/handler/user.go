package handler

import (
	"database/sql"
	"encoding/json"
	"net/http"
	"regexp"
	"strconv"

	"golang.org/x/crypto/bcrypt"
	"kesiswaan/internal/helper"
)

var belajarRe = regexp.MustCompile(`^[^\s@]+@belajar\.id$`)

type UserHandler struct{ DB *sql.DB }

func (h *UserHandler) List(w http.ResponseWriter, r *http.Request) {
	role := r.URL.Query().Get("role")
	q := `SELECT u.id,u.nama,u.username,u.role,COALESCE(u.nip,''),u.status,COALESCE(k.nama_kelas,'—') FROM users u LEFT JOIN kelas k ON k.wali_kelas_id=u.id WHERE u.deleted_at IS NULL`
	args := []any{}
	if role != "" && role != "semua" {
		q += ` AND u.role=?`
		args = append(args, role)
	}
	q += ` ORDER BY u.id DESC`
	rows, err := h.DB.Query(q, args...)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	defer rows.Close()
	type Row struct {
		ID int64 `json:"id"`; Nama string `json:"nama"`; Username string `json:"username"`; Role string `json:"role"`; NIP string `json:"nip"`; Status string `json:"status"`; Kelas string `json:"kelas"`
	}
	var out []Row
	for rows.Next() {
		var x Row
		rows.Scan(&x.ID, &x.Nama, &x.Username, &x.Role, &x.NIP, &x.Status, &x.Kelas)
		out = append(out, x)
	}
	if out == nil {
		out = []Row{}
	}
	helper.Success(w, out)
}

func (h *UserHandler) Create(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Nama     string `json:"nama"`
		Username string `json:"username"`
		Password string `json:"password"`
		Role     string `json:"role"`
		NIP      *string `json:"nip"`
		Status   string `json:"status"`
		KelasID  *int64 `json:"kelas_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Nama == "" || req.Username == "" || req.Password == "" {
		helper.Error(w, 400, "nama, username, password wajib")
		return
	}
	if len(req.Password) < 8 {
		helper.Error(w, 400, "password min 8 karakter")
		return
	}
	if req.Role == "Guru BK" && !belajarRe.MatchString(req.Username) {
		helper.Error(w, 400, "Guru BK wajib format nama@belajar.id")
		return
	}
	if req.Status == "" {
		req.Status = "Aktif"
	}
	if req.Role == "" {
		req.Role = "Admin"
	}
	hash, _ := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
	res, err := h.DB.Exec(`INSERT INTO users(nama,username,password_hash,role,nip,status) VALUES(?,?,?,?,?,?)`, req.Nama, req.Username, string(hash), req.Role, req.NIP, req.Status)
	if err != nil {
		helper.Error(w, 400, err.Error())
		return
	}
	id, _ := res.LastInsertId()
	if req.Role == "Wali Kelas" && req.KelasID != nil {
		h.DB.Exec(`UPDATE kelas SET wali_kelas_id=? WHERE id=?`, id, *req.KelasID)
	}
	helper.Created(w, map[string]any{"id": id})
}

func (h *UserHandler) Update(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var req struct {
		Nama     string `json:"nama"`
		Username string `json:"username"`
		Role     string `json:"role"`
		NIP      *string `json:"nip"`
		Status   string `json:"status"`
		KelasID  *int64 `json:"kelas_id"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		helper.Error(w, 400, "payload tidak valid")
		return
	}
	if req.Role == "Guru BK" && req.Username != "" && !belajarRe.MatchString(req.Username) {
		helper.Error(w, 400, "Guru BK wajib @belajar.id")
		return
	}
	_, err := h.DB.Exec(`UPDATE users SET nama=COALESCE(NULLIF(?,''),nama), username=COALESCE(NULLIF(?,''),username), role=COALESCE(NULLIF(?,''),role), nip=?, status=COALESCE(NULLIF(?,''),status) WHERE id=?`, req.Nama, req.Username, req.Role, req.NIP, req.Status, id)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	if req.KelasID != nil {
		// clear old
		h.DB.Exec(`UPDATE kelas SET wali_kelas_id=NULL WHERE wali_kelas_id=?`, id)
		h.DB.Exec(`UPDATE kelas SET wali_kelas_id=? WHERE id=?`, id, *req.KelasID)
	}
	helper.Message(w, "diperbarui", nil)
}

func (h *UserHandler) Delete(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	h.DB.Exec(`UPDATE kelas SET wali_kelas_id=NULL WHERE wali_kelas_id=?`, id)
	h.DB.Exec(`UPDATE users SET deleted_at=NOW() WHERE id=?`, id)
	helper.Message(w, "dihapus (soft-delete)", nil)
}

func (h *UserHandler) ResetPassword(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var req struct{ Password string `json:"password"` }
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || len(req.Password) < 8 {
		helper.Error(w, 400, "password min 8 karakter")
		return
	}
	hash, _ := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
	h.DB.Exec(`UPDATE users SET password_hash=? WHERE id=?`, string(hash), id)
	helper.Message(w, "password direset", nil)
}
