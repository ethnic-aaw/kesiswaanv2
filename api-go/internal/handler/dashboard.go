package handler

import (
	"database/sql"
	"net/http"

	"kesiswaan/internal/helper"
)

type DashboardHandler struct{ DB *sql.DB }

func (h *DashboardHandler) Get(w http.ResponseWriter, r *http.Request) {
	var threshold string
	_ = h.DB.QueryRow(`SELECT value FROM settings WHERE key_name='threshold_poin_kritis'`).Scan(&threshold)
	if threshold == "" {
		threshold = "76"
	}
	// determine current TA: latest kelas.tahun_ajaran
	var ta string
	_ = h.DB.QueryRow(`SELECT tahun_ajaran FROM kelas WHERE deleted_at IS NULL ORDER BY tahun_ajaran DESC LIMIT 1`).Scan(&ta)
	if ta == "" {
		ta = "2024/2025"
	}
	var totalSiswa, totalKelas, totalPelBulan int
	_ = h.DB.QueryRow(`SELECT COUNT(*) FROM siswa WHERE status='Aktif' AND deleted_at IS NULL`).Scan(&totalSiswa)
	_ = h.DB.QueryRow(`SELECT COUNT(*) FROM kelas WHERE deleted_at IS NULL`).Scan(&totalKelas)
	_ = h.DB.QueryRow(`SELECT COUNT(*) FROM pelanggaran_siswa WHERE DATE_FORMAT(tanggal,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')`).Scan(&totalPelBulan)

	// siswa bermasalah: akumulasi poin TA berjalan > threshold
	rows, _ := h.DB.Query(`
		SELECT s.id, s.nama, s.nipd, k.nama_kelas, SUM(ps.poin_final) as total
		FROM siswa s
		LEFT JOIN kelas k ON k.id=s.kelas_id
		LEFT JOIN pelanggaran_siswa ps ON ps.siswa_id=s.id AND ps.tahun_ajaran=?
		WHERE s.deleted_at IS NULL
		GROUP BY s.id HAVING total > ?`, ta, threshold)
	bermasalah := 0
	if rows != nil {
		for rows.Next() {
			bermasalah++
		}
		rows.Close()
	}

	// top 10
	type Top struct {
		ID    int64  `json:"id"`
		Nama  string `json:"nama"`
		NIPD  string `json:"nipd"`
		Kelas string `json:"kelas"`
		Poin  int    `json:"poin"`
	}
	var top []Top
	q := `
		SELECT s.id,s.nama,s.nipd,COALESCE(k.nama_kelas,'-') as kelas, COALESCE(SUM(ps.poin_final),0) as poin
		FROM siswa s
		LEFT JOIN kelas k ON k.id=s.kelas_id
		LEFT JOIN pelanggaran_siswa ps ON ps.siswa_id=s.id AND ps.tahun_ajaran=?
		WHERE s.deleted_at IS NULL
		GROUP BY s.id ORDER BY poin DESC LIMIT 10`
	rs, _ := h.DB.Query(q, ta)
	if rs != nil {
		defer rs.Close()
		for rs.Next() {
			var t Top
			rs.Scan(&t.ID, &t.Nama, &t.NIPD, &t.Kelas, &t.Poin)
			top = append(top, t)
		}
	}

	// chart 6 bulan
	type Pt struct {
		Bulan string `json:"bulan"`
		Total int    `json:"total"`
	}
	var chart []Pt
	cr, _ := h.DB.Query(`SELECT DATE_FORMAT(tanggal,'%Y-%m') as bulan, COUNT(*) as total FROM pelanggaran_siswa WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY bulan ORDER BY bulan`)
	if cr != nil {
		defer cr.Close()
		for cr.Next() {
			var p Pt
			cr.Scan(&p.Bulan, &p.Total)
			chart = append(chart, p)
		}
	}

	helper.Success(w, map[string]any{
		"threshold":         threshold,
		"tahun_ajaran":      ta,
		"total_siswa":       totalSiswa,
		"total_kelas":       totalKelas,
		"total_pelanggaran": totalPelBulan,
		"siswa_bermasalah":  bermasalah,
		"top10":             top,
		"chart":             chart,
	})
}
