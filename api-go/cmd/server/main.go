package main

import (
	"database/sql"
	"log"
	"net/http"
	"os"
	"strings"

	_ "github.com/go-sql-driver/mysql"
	"golang.org/x/crypto/bcrypt"
	"kesiswaan/internal/config"
	"kesiswaan/internal/handler"
	"kesiswaan/internal/middleware"
)

func main() {
	cfg := config.Load()
	// optional .env file
	if _, err := os.Stat(".env"); err == nil {
		b, _ := os.ReadFile(".env")
		for _, line := range strings.Split(string(b), "\n") {
			line = strings.TrimSpace(line)
			if line == "" || strings.HasPrefix(line, "#") {
				continue
			}
			kv := strings.SplitN(line, "=", 2)
			if len(kv) == 2 {
				k := strings.TrimSpace(kv[0]); v := strings.TrimSpace(kv[1])
				if os.Getenv(k) == "" {
					os.Setenv(k, v)
					// reload cfg values if relevant
					if k == "DB_DSN" {
						cfg.DBDSN = v
					}
					if k == "DAPODIK_DSN" {
						cfg.DapodikDSN = v
					}
					if k == "JWT_SECRET" {
						cfg.JWTSecret = v
					}
					if k == "PORT" {
						cfg.Port = v
					}
				}
			}
		}
	}

	db, err := sql.Open("mysql", cfg.DBDSN)
	if err != nil {
		log.Fatal("db open:", err)
	}
	defer db.Close()
	if err := db.Ping(); err != nil {
		log.Println("warning: db ping failed:", err, "— server tetap jalan, /health akan 500 sampai DB siap")
	} else {
		runMigrations(db)
		seedAdmin(db)
	}

	mux := http.NewServeMux()

	// health
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, r *http.Request) {
		if err := db.Ping(); err != nil {
			w.WriteHeader(500)
			w.Write([]byte(`{"success":false,"error":"db down"}`))
			return
		}
		w.Header().Set("Content-Type", "application/json")
		w.Write([]byte(`{"success":true,"data":"ok"}`))
	})

	authH := &handler.AuthHandler{DB: db, JWTSecret: cfg.JWTSecret, JWTExpiry: cfg.JWTExpiry}
	settingsH := &handler.SettingsHandler{DB: db}
	dashH := &handler.DashboardHandler{DB: db}
	kelasH := &handler.KelasHandler{DB: db}
	userH := &handler.UserHandler{DB: db}
	siswaH := &handler.SiswaHandler{DB: db, UploadDir: cfg.UploadDir}
	pelH := &handler.PelanggaranHandler{DB: db}
	dapodikH := &handler.DapodikHandler{DB: db, DapodikDSN: cfg.DapodikDSN}

	// public
	mux.Handle("POST /api/auth/login", middleware.RateLimitLogin(http.HandlerFunc(authH.Login)))

	// auth wrapper
	authMw := middleware.Auth(cfg.JWTSecret)
	adminOnly := middleware.RequireRole("Admin")

	// protected routes
	mux.Handle("GET /api/auth/me", authMw(http.HandlerFunc(authH.Me)))
	mux.Handle("GET /api/dashboard", authMw(http.HandlerFunc(dashH.Get)))

	mux.Handle("GET /api/settings/threshold_poin_kritis", authMw(http.HandlerFunc(settingsH.Get)))
	mux.Handle("PUT /api/settings/threshold_poin_kritis", authMw(adminOnly(http.HandlerFunc(settingsH.Put))))

	// kelas
	mux.Handle("GET /api/kelas", authMw(http.HandlerFunc(kelasH.List)))
	mux.Handle("POST /api/kelas", authMw(adminOnly(http.HandlerFunc(kelasH.Create))))
	mux.Handle("PUT /api/kelas/{id}", authMw(adminOnly(http.HandlerFunc(kelasH.Update))))
	mux.Handle("DELETE /api/kelas/{id}", authMw(adminOnly(http.HandlerFunc(kelasH.Delete))))

	// users
	mux.Handle("GET /api/users", authMw(http.HandlerFunc(userH.List)))
	mux.Handle("POST /api/users", authMw(adminOnly(http.HandlerFunc(userH.Create))))
	mux.Handle("PUT /api/users/{id}", authMw(adminOnly(http.HandlerFunc(userH.Update))))
	mux.Handle("DELETE /api/users/{id}", authMw(adminOnly(http.HandlerFunc(userH.Delete))))
	mux.Handle("POST /api/users/{id}/reset-password", authMw(adminOnly(http.HandlerFunc(userH.ResetPassword))))

	// siswa
	mux.Handle("GET /api/siswa", authMw(http.HandlerFunc(siswaH.List)))
	mux.Handle("POST /api/siswa", authMw(adminOnly(http.HandlerFunc(siswaH.Create))))
	mux.Handle("GET /api/siswa/{id}", authMw(http.HandlerFunc(siswaH.Get)))
	mux.Handle("PUT /api/siswa/{id}", authMw(adminOnly(http.HandlerFunc(siswaH.Update))))
	mux.Handle("DELETE /api/siswa/{id}", authMw(adminOnly(http.HandlerFunc(siswaH.Delete))))
	mux.Handle("POST /api/siswa/{id}/foto", authMw(adminOnly(http.HandlerFunc(siswaH.UploadFoto))))
	mux.Handle("POST /api/siswa/import", authMw(adminOnly(http.HandlerFunc(siswaH.ImportCSV))))
	mux.Handle("POST /api/siswa/import-excel", authMw(adminOnly(http.HandlerFunc(siswaH.ImportExcel))))

	// pelanggaran jenis
	mux.Handle("GET /api/pelanggaran-jenis", authMw(http.HandlerFunc(pelH.ListJenis)))
	mux.Handle("POST /api/pelanggaran-jenis", authMw(adminOnly(http.HandlerFunc(pelH.CreateJenis))))
	mux.Handle("PUT /api/pelanggaran-jenis/{id}", authMw(adminOnly(http.HandlerFunc(pelH.UpdateJenis))))
	mux.Handle("DELETE /api/pelanggaran-jenis/{id}", authMw(adminOnly(http.HandlerFunc(pelH.DeleteJenis))))

	// pelanggaran siswa
	mux.Handle("GET /api/pelanggaran", authMw(http.HandlerFunc(pelH.List)))
	mux.Handle("POST /api/pelanggaran", authMw(http.HandlerFunc(pelH.Create)))

	// dapodik
	mux.Handle("POST /api/siswa/sync-dapodik", authMw(adminOnly(http.HandlerFunc(dapodikH.Sync))))
	mux.Handle("GET /api/siswa/sync-dapodik/logs", authMw(http.HandlerFunc(dapodikH.Logs)))

	// CORS + log wrapper
	h := cors(mux)

	addr := ":" + cfg.Port
	log.Println("kesiswaan Go API listening on", addr, " uploadDir=", cfg.UploadDir)
	log.Fatal(http.ListenAndServe(addr, h))
}

func cors(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		origin := r.Header.Get("Origin")
		if origin == "" {
			origin = "*"
		}
		w.Header().Set("Access-Control-Allow-Origin", origin)
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")
		w.Header().Set("Access-Control-Allow-Credentials", "true")
		if r.Method == "OPTIONS" {
			w.WriteHeader(204)
			return
		}
		next.ServeHTTP(w, r)
	})
}

func runMigrations(db *sql.DB) {
	for _, p := range []string{"migrations/001_init.sql", "./migrations/001_init.sql", "api-go/migrations/001_init.sql"} {
		b, err := os.ReadFile(p)
		if err != nil {
			continue
		}
		// split by ; naive but ok for this file
		stmts := strings.Split(string(b), ";")
		for _, s := range stmts {
			s = strings.TrimSpace(s)
			if s == "" || strings.HasPrefix(s, "--") {
				continue
			}
			if _, err := db.Exec(s); err != nil && !strings.Contains(err.Error(), "Duplicate") && !strings.Contains(err.Error(), "already exists") {
				// log but continue — CHECK constraint may fail on older MySQL
				if strings.Contains(s, "CHECK") {
					continue
				}
				log.Println("migrate stmt error:", err)
			}
		}
		log.Println("migrations applied from", p)
		return
	}
	log.Println("migrations file not found — skip")
}

func seedAdmin(db *sql.DB) {
	var cnt int
	_ = db.QueryRow(`SELECT COUNT(*) FROM users WHERE username='admin'`).Scan(&cnt)
	if cnt > 0 {
		return
	}
	hash, _ := bcrypt.GenerateFromPassword([]byte("admin123"), bcrypt.DefaultCost)
	_, err := db.Exec(`INSERT INTO users(nama,username,password_hash,role,status) VALUES(?,?,?, ?, ?)`, "Admin TU", "admin", string(hash), "Admin", "Aktif")
	if err != nil {
		log.Println("seed admin error:", err)
		return
	}
	log.Println("seed admin admin/admin123 created")
}
