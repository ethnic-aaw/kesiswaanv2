package handler

import (
	"database/sql"
	"encoding/csv"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"github.com/google/uuid"
	"kesiswaan/internal/helper"
)

var hpRe = regexp.MustCompile(`[^0-9+]`)

func normalizeHP(s string) string {
	if s == "" {
		return s
	}
	// strip non-digit except leading +
	hasPlus := strings.HasPrefix(strings.TrimSpace(s), "+")
	clean := hpRe.ReplaceAllString(s, "")
	if hasPlus && !strings.HasPrefix(clean, "+") {
		clean = "+" + clean
	}
	// normalize 62 -> +62, 0 -> keep 08
	if strings.HasPrefix(clean, "62") {
		clean = "+62" + clean[2:]
	}
	return clean
}

type SiswaHandler struct {
	DB        *sql.DB
	UploadDir string
}

func (h *SiswaHandler) List(w http.ResponseWriter, r *http.Request) {
	q := strings.TrimSpace(r.URL.Query().Get("q"))
	kelas := r.URL.Query().Get("kelas")
	status := r.URL.Query().Get("status")
	page, _ := strconv.Atoi(r.URL.Query().Get("page"))
	size, _ := strconv.Atoi(r.URL.Query().Get("size"))
	sort := r.URL.Query().Get("sort")
	if page < 1 {
		page = 1
	}
	if size < 1 || size > 100 {
		size = 25
	}
	where := `WHERE s.deleted_at IS NULL`
	args := []any{}
	if q != "" {
		where += ` AND (s.nama LIKE ? OR s.nipd LIKE ?)`
		args = append(args, "%"+q+"%", "%"+q+"%")
	}
	if kelas != "" {
		where += ` AND s.kelas_id = ?`
		// allow kelas name or id
		if id, err := strconv.Atoi(kelas); err == nil {
			args = append(args, id)
		} else {
			where = strings.Replace(where, "s.kelas_id = ?", "k.nama_kelas = ?", 1)
			args[len(args)-1] = kelas
		}
	}
	if status != "" {
		where += ` AND s.status = ?`
		args = append(args, status)
	}
	// count
	var total int
	h.DB.QueryRow(`SELECT COUNT(*) FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id `+where, args...).Scan(&total)

	order := `s.nama ASC`
	if sort == "nipd" {
		order = `s.nipd ASC`
	} else if sort == "poin" {
		order = `total_poin DESC`
	}
	// determine current TA for poin
	var ta string
	_ = h.DB.QueryRow(`SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1`).Scan(&ta)
	if ta == "" {
		ta = "2024/2025"
	}
	offset := (page - 1) * size
	query := fmt.Sprintf(`SELECT s.id,s.nipd,s.nama,s.jenis_kelamin,s.kelas_id,COALESCE(k.nama_kelas,'—'),s.tempat_lahir,s.tanggal_lahir,s.nama_ortu,s.hp_ortu,s.foto,s.alamat,s.status,COALESCE(SUM(ps.poin_final),0) as total_poin
		FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN pelanggaran_siswa ps ON ps.siswa_id=s.id AND ps.tahun_ajaran=?
		%s GROUP BY s.id ORDER BY %s LIMIT ? OFFSET ?`, where, order)
	allArgs := append([]any{ta}, args...)
	allArgs = append(allArgs, size, offset)
	rows, err := h.DB.Query(query, allArgs...)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	defer rows.Close()
	type Row struct {
		ID int64 `json:"id"`; NIPD string `json:"nipd"`; Nama string `json:"nama"`; JK string `json:"jenis_kelamin"`; KelasID *int64 `json:"kelas_id"`; Kelas string `json:"kelas"`; Tempat *string `json:"tempat_lahir"`; Tgl *string `json:"tanggal_lahir"`; Ortu *string `json:"nama_ortu"`; HP *string `json:"hp_ortu"`; Foto *string `json:"foto"`; Alamat *string `json:"alamat"`; Status string `json:"status"`; Poin int `json:"total_poin"`
	}
	var out []Row
	for rows.Next() {
		var x Row
		var tgl sql.NullString
		rows.Scan(&x.ID, &x.NIPD, &x.Nama, &x.JK, &x.KelasID, &x.Kelas, &x.Tempat, &tgl, &x.Ortu, &x.HP, &x.Foto, &x.Alamat, &x.Status, &x.Poin)
		if tgl.Valid {
			s := tgl.String
			x.Tgl = &s
		}
		out = append(out, x)
	}
	if out == nil {
		out = []Row{}
	}
	helper.Success(w, map[string]any{"rows": out, "total": total, "page": page, "size": size})
}

func (h *SiswaHandler) Get(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var ta string
	_ = h.DB.QueryRow(`SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1`).Scan(&ta)
	if ta == "" {
		ta = "2024/2025"
	}
	var s struct {
		ID int64 `json:"id"`; NIPD string `json:"nipd"`; Nama string `json:"nama"`; JK string `json:"jenis_kelamin"`; KelasID *int64 `json:"kelas_id"`; Kelas string `json:"kelas"`; Tempat *string `json:"tempat_lahir"`; Tgl *string `json:"tanggal_lahir"`; Ortu *string `json:"nama_ortu"`; HP *string `json:"hp_ortu"`; Foto *string `json:"foto"`; Alamat *string `json:"alamat"`; Status string `json:"status"`; Poin int `json:"total_poin"`
	}
	var tgl sql.NullString
	err := h.DB.QueryRow(`SELECT s.id,s.nipd,s.nama,s.jenis_kelamin,s.kelas_id,COALESCE(k.nama_kelas,'—'),s.tempat_lahir,s.tanggal_lahir,s.nama_ortu,s.hp_ortu,s.foto,s.alamat,s.status,COALESCE((SELECT SUM(poin_final) FROM pelanggaran_siswa WHERE siswa_id=s.id AND tahun_ajaran=?),0)
		FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id WHERE s.id=? AND s.deleted_at IS NULL`, ta, id).Scan(&s.ID, &s.NIPD, &s.Nama, &s.JK, &s.KelasID, &s.Kelas, &s.Tempat, &tgl, &s.Ortu, &s.HP, &s.Foto, &s.Alamat, &s.Status, &s.Poin)
	if err != nil {
		helper.Error(w, 404, "siswa tidak ditemukan")
		return
	}
	if tgl.Valid {
		s.Tgl = &tgl.String
	}
	// riwayat
	type R struct {
		ID int64 `json:"id"`; Tanggal string `json:"tanggal"`; Pelanggaran string `json:"pelanggaran"`; Poin int `json:"poin"`; Pelapor string `json:"pelapor"`; TA string `json:"tahun_ajaran"`; Ket *string `json:"keterangan"`
	}
	var riwayat []R
	rs, _ := h.DB.Query(`SELECT ps.id, ps.tanggal, COALESCE(jp.nama,'-'), ps.poin_final, COALESCE(u.nama,'-'), ps.tahun_ajaran, ps.keterangan FROM pelanggaran_siswa ps LEFT JOIN jenis_pelanggaran jp ON jp.id=ps.jenis_pelanggaran_id LEFT JOIN users u ON u.id=ps.pelapor_id WHERE ps.siswa_id=? ORDER BY ps.tanggal DESC`, id)
	if rs != nil {
		defer rs.Close()
		for rs.Next() {
			var x R
			rs.Scan(&x.ID, &x.Tanggal, &x.Pelanggaran, &x.Poin, &x.Pelapor, &x.TA, &x.Ket)
			riwayat = append(riwayat, x)
		}
	}
	helper.Success(w, map[string]any{"siswa": s, "riwayat": riwayat})
}

func (h *SiswaHandler) Create(w http.ResponseWriter, r *http.Request) {
	var req struct {
		NIPD string `json:"nipd"`; Nama string `json:"nama"`; JK string `json:"jenis_kelamin"`; KelasID *int64 `json:"kelas_id"`; KelasNama string `json:"kelas_nama"`; Tempat *string `json:"tempat_lahir"`; Tgl *string `json:"tanggal_lahir"`; Ortu *string `json:"nama_ortu"`; HP *string `json:"hp_ortu"`; Alamat *string `json:"alamat"`; Status string `json:"status"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.NIPD == "" || req.Nama == "" {
		helper.Error(w, 400, "nipd dan nama wajib")
		return
	}
	if len(req.NIPD) > 20 {
		helper.Error(w, 400, "nipd max 20 karakter")
		return
	}
	if req.JK == "" {
		req.JK = "L"
	}
	if req.Status == "" {
		req.Status = "Aktif"
	}
	// resolve kelas by name if id nil
	if req.KelasID == nil && req.KelasNama != "" {
		var id int64
		if err := h.DB.QueryRow(`SELECT id FROM kelas WHERE nama_kelas=? AND deleted_at IS NULL`, req.KelasNama).Scan(&id); err == nil {
			req.KelasID = &id
		}
	}
	hp := ""
	if req.HP != nil {
		hp = normalizeHP(*req.HP)
		if hp != "" && !regexp.MustCompile(`^(\+62|08)[0-9]{8,13}$`).MatchString(strings.ReplaceAll(hp, " ", "")) {
			helper.Error(w, 400, "format HP harus 08… atau +62…")
			return
		}
		req.HP = &hp
	}
	res, err := h.DB.Exec(`INSERT INTO siswa(nipd,nama,jenis_kelamin,kelas_id,tempat_lahir,tanggal_lahir,nama_ortu,hp_ortu,alamat,status) VALUES(?,?,?,?,?,?,?,?,?,?)`,
		req.NIPD, req.Nama, req.JK, req.KelasID, req.Tempat, req.Tgl, req.Ortu, req.HP, req.Alamat, req.Status)
	if err != nil {
		helper.Error(w, 400, err.Error())
		return
	}
	id, _ := res.LastInsertId()
	helper.Created(w, map[string]any{"id": id})
}

func (h *SiswaHandler) Update(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	var req map[string]any
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		helper.Error(w, 400, "payload tidak valid")
		return
	}
	// build dynamic update
	fields := []string{}
	args := []any{}
	set := func(col string, v any) {
		fields = append(fields, col+"=?")
		args = append(args, v)
	}
	if v, ok := req["nipd"].(string); ok {
		if len(v) > 20 {
			helper.Error(w, 400, "nipd max 20")
			return
		}
		set("nipd", v)
	}
	if v, ok := req["nama"].(string); ok {
		set("nama", v)
	}
	if v, ok := req["jenis_kelamin"].(string); ok {
		set("jenis_kelamin", v)
	}
	if v, ok := req["kelas_id"]; ok && v != nil {
		switch x := v.(type) {
		case float64:
			i := int64(x)
			set("kelas_id", i)
		case string:
			if x != "" {
				var kid int64
				if err := h.DB.QueryRow(`SELECT id FROM kelas WHERE nama_kelas=?`, x).Scan(&kid); err == nil {
					set("kelas_id", kid)
				}
			}
		}
	}
	if v, ok := req["kelas_nama"].(string); ok && v != "" {
		var kid int64
		if err := h.DB.QueryRow(`SELECT id FROM kelas WHERE nama_kelas=?`, v).Scan(&kid); err == nil {
			set("kelas_id", kid)
		}
	}
	for _, k := range []string{"tempat_lahir", "tanggal_lahir", "nama_ortu", "alamat", "status"} {
		if v, ok := req[k]; ok {
			set(k, v)
		}
	}
	if v, ok := req["hp_ortu"].(string); ok {
		hp := normalizeHP(v)
		if hp != "" && !regexp.MustCompile(`^(\+62|08)[0-9]{8,13}$`).MatchString(strings.ReplaceAll(hp, " ", "")) {
			helper.Error(w, 400, "format HP salah")
			return
		}
		set("hp_ortu", hp)
	}
	if len(fields) == 0 {
		helper.Error(w, 400, "tidak ada field untuk update")
		return
	}
	args = append(args, id)
	_, err := h.DB.Exec(`UPDATE siswa SET `+strings.Join(fields, ",")+` WHERE id=? AND deleted_at IS NULL`, args...)
	if err != nil {
		helper.Error(w, 400, err.Error())
		return
	}
	helper.Message(w, "diperbarui", nil)
}

func (h *SiswaHandler) Delete(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	h.DB.Exec(`UPDATE siswa SET deleted_at=NOW() WHERE id=?`, id)
	helper.Message(w, "dihapus (soft-delete)", nil)
}

func (h *SiswaHandler) UploadFoto(w http.ResponseWriter, r *http.Request) {
	id, _ := strconv.ParseInt(r.PathValue("id"), 10, 64)
	r.ParseMultipartForm(500 << 10) // 500KB
	file, hdr, err := r.FormFile("foto")
	if err != nil {
		helper.Error(w, 400, "file foto wajib")
		return
	}
	defer file.Close()
	if hdr.Size > 500*1024 {
		helper.Error(w, 400, "maks 500KB")
		return
	}
	buf := make([]byte, 512)
	n, _ := io.ReadFull(file, buf)
	// reset read — we already consumed 512 bytes, need full file; simpler: read all
	// re-read from multipart: get bytes
	// ponytail: proper resize 150x150 — for now just save as-is with random name
	ext := ".jpg"
	ct := http.DetectContentType(buf[:n])
	if ct == "image/png" {
		ext = ".png"
	} else if ct != "image/jpeg" && ct != "image/jpg" {
		helper.Error(w, 400, "hanya JPG/PNG")
		return
	}
	// read remaining file content via hdr.Open
	f2, _ := hdr.Open()
	defer f2.Close()
	// need to save to UploadDir
	name := uuid.NewString() + ext
	// use helper to save file
	if err := saveUpload(h.UploadDir, name, f2); err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	h.DB.Exec(`UPDATE siswa SET foto=? WHERE id=?`, name, id)
	helper.Message(w, "foto diupload", map[string]string{"foto": name})
}

func (h *SiswaHandler) ImportCSV(w http.ResponseWriter, r *http.Request) {
	// expects multipart file "file" with header NIPD,Nama,Kelas,JK
	r.ParseMultipartForm(2 << 20)
	file, _, err := r.FormFile("file")
	if err != nil {
		helper.Error(w, 400, "file CSV wajib (field name=file)")
		return
	}
	defer file.Close()
	reader := csv.NewReader(file)
	reader.TrimLeadingSpace = true
	records, err := reader.ReadAll()
	if err != nil {
		helper.Error(w, 400, "gagal parse CSV: "+err.Error())
		return
	}
	if len(records) < 1 {
		helper.Error(w, 400, "CSV kosong")
		return
	}
	// detect header
	start := 0
	if len(records[0]) >= 4 && strings.EqualFold(strings.TrimSpace(records[0][0]), "NIPD") {
		start = 1
	}
	type RowRes struct {
		Line int `json:"line"`; NIPD string `json:"nipd"`; Nama string `json:"nama"`; Kelas string `json:"kelas"`; JK string `json:"jk"`; Status string `json:"status"`; Error string `json:"error,omitempty"`
	}
	var results []RowRes
	okCount, failCount := 0, 0
	for i := start; i < len(records); i++ {
		rec := records[i]
		if len(rec) < 4 {
			results = append(results, RowRes{Line: i + 1, Status: "gagal", Error: "kolom kurang (butuh 4)"})
			failCount++
			continue
		}
		nipd := strings.TrimSpace(rec[0])
		nama := strings.TrimSpace(rec[1])
		kelasNama := strings.TrimSpace(rec[2])
		jk := strings.TrimSpace(rec[3])
		rr := RowRes{Line: i + 1, NIPD: nipd, Nama: nama, Kelas: kelasNama, JK: jk}
		if nipd == "" {
			rr.Status = "gagal"; rr.Error = "NIPD kosong"
			results = append(results, rr); failCount++; continue
		}
		if nama == "" {
			rr.Status = "gagal"; rr.Error = "Nama kosong"
			results = append(results, rr); failCount++; continue
		}
		// check kelas exists
		var kelasID int64
		if err := h.DB.QueryRow(`SELECT id FROM kelas WHERE nama_kelas=? AND deleted_at IS NULL`, kelasNama).Scan(&kelasID); err != nil {
			rr.Status = "gagal"; rr.Error = "kelas tidak ditemukan: " + kelasNama
			results = append(results, rr); failCount++; continue
		}
		// check duplicate NIPD
		var exists int
		h.DB.QueryRow(`SELECT COUNT(*) FROM siswa WHERE nipd=?`, nipd).Scan(&exists)
		if exists > 0 {
			rr.Status = "gagal"; rr.Error = "NIPD duplikat"
			results = append(results, rr); failCount++; continue
		}
		// insert
		_, err := h.DB.Exec(`INSERT INTO siswa(nipd,nama,jenis_kelamin,kelas_id,status) VALUES(?,?,?,?,?)`, nipd, nama, jk, kelasID, "Aktif")
		if err != nil {
			rr.Status = "gagal"; rr.Error = err.Error()
			results = append(results, rr); failCount++; continue
		}
		rr.Status = "ok"
		results = append(results, rr)
		okCount++
	}
	helper.Success(w, map[string]any{"berhasil": okCount, "gagal": failCount, "rincian": results})
}

// saveUpload writes file to dir/name
func saveUpload(dir, name string, src io.Reader) error {
	return helper.WriteFile(dir+"/"+name, src)
}
