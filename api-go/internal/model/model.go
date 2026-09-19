package model

import "time"

type User struct {
	ID        int64      `json:"id"`
	Nama      string     `json:"nama"`
	Username  string     `json:"username"`
	Role      string     `json:"role"`
	NIP       *string    `json:"nip"`
	Status    string     `json:"status"`
	DeletedAt *time.Time `json:"-"`
	CreatedAt time.Time  `json:"created_at"`
	UpdatedAt time.Time  `json:"updated_at"`
}

type Kelas struct {
	ID         int64      `json:"id"`
	NamaKelas  string     `json:"nama_kelas"`
	Tingkat    string     `json:"tingkat"`
	WaliKelasID *int64    `json:"wali_kelas_id"`
	WaliNama   *string    `json:"wali_nama,omitempty"`
	JmlSiswa   int        `json:"jml_siswa,omitempty"`
	TahunAjaran string    `json:"tahun_ajaran"`
	DeletedAt  *time.Time `json:"-"`
}

type Siswa struct {
	ID           int64      `json:"id"`
	NIPD         string     `json:"nipd"`
	Nama         string     `json:"nama"`
	JenisKelamin string     `json:"jenis_kelamin"`
	KelasID      *int64     `json:"kelas_id"`
	KelasNama    *string    `json:"kelas_nama,omitempty"`
	TempatLahir  *string    `json:"tempat_lahir"`
	TanggalLahir *string    `json:"tanggal_lahir"` // YYYY-MM-DD
	NamaOrtu     *string    `json:"nama_ortu"`
	HpOrtu       *string    `json:"hp_ortu"`
	Foto         *string    `json:"foto"`
	Alamat       *string    `json:"alamat"`
	Status       string     `json:"status"`
	TotalPoin    int        `json:"total_poin,omitempty"`
	DeletedAt    *time.Time `json:"-"`
}

type JenisPelanggaran struct {
	ID         int64      `json:"id"`
	Kode       string     `json:"kode"`
	Nama       string     `json:"nama"`
	Kategori   string     `json:"kategori"`
	BobotPoin  int        `json:"bobot_poin"`
	Deskripsi  *string    `json:"deskripsi"`
	Konsekuensi *string   `json:"konsekuensi"`
	DeletedAt  *time.Time `json:"-"`
}

type PelanggaranSiswa struct {
	ID                  int64   `json:"id"`
	SiswaID             int64   `json:"siswa_id"`
	JenisPelanggaranID  int64   `json:"jenis_pelanggaran_id"`
	JenisNama           string  `json:"jenis_nama,omitempty"`
	PoinFinal           int     `json:"poin_final"`
	TahunAjaran         string  `json:"tahun_ajaran"`
	Tanggal             string  `json:"tanggal"`
	Lokasi              *string `json:"lokasi"`
	Keterangan          *string `json:"keterangan"`
	Tindakan            *string `json:"tindakan"`
	PelaporID           int64   `json:"pelapor_id"`
	PelaporNama         string  `json:"pelapor_nama,omitempty"`
	CreatedAt           time.Time `json:"created_at"`
}

type AuditPoin struct {
	ID                    int64     `json:"id"`
	PelanggaranSiswaID    int64     `json:"pelanggaran_siswa_id"`
	PoinDefault           int       `json:"poin_default"`
	PoinFinal             int       `json:"poin_final"`
	Alasan                string    `json:"alasan"`
	ChangedBy             int64     `json:"changed_by"`
	ChangedAt             time.Time `json:"changed_at"`
}

type DapodikSyncLog struct {
	ID              int64     `json:"id"`
	JumlahBaru      int       `json:"jumlah_baru"`
	JumlahDiperbarui int      `json:"jumlah_diperbarui"`
	JumlahGagal     int       `json:"jumlah_gagal"`
	DilakukanOleh   int64     `json:"dilakukan_oleh"`
	CreatedAt       time.Time `json:"created_at"`
}

type APIResponse struct {
	Success bool   `json:"success"`
	Message string `json:"message,omitempty"`
	Data    any    `json:"data,omitempty"`
	Error   string `json:"error,omitempty"`
}

type PaginatedData struct {
	Rows  any `json:"rows"`
	Total int `json:"total"`
	Page  int `json:"page"`
	Size  int `json:"size"`
}

type LoginRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

type LoginResponse struct {
	Token string `json:"token"`
	User  User   `json:"user"`
}
