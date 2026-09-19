package config

import (
	"os"
	"time"
)

type Config struct {
	Port       string
	DBDSN      string
	DapodikDSN string
	JWTSecret  string
	JWTExpiry  time.Duration
	UploadDir  string
}

func Load() Config {
	port := env("PORT", "8080")
	dsn := env("DB_DSN", "root:@tcp(127.0.0.1:3306)/kesiswaan?charset=utf8mb4&parseTime=true&loc=Local")
	dapodik := os.Getenv("DAPODIK_DSN")
	secret := env("JWT_SECRET", "ganti-secret-acak-min-32-karakter-dev-only")
	expStr := env("JWT_EXPIRY", "8h")
	exp, err := time.ParseDuration(expStr)
	if err != nil {
		exp = 8 * time.Hour
	}
	uploadDir := env("UPLOAD_DIR", "../assets/uploads/foto_siswa")
	// allow api-go/ vs root relative
	if _, err := os.Stat(uploadDir); os.IsNotExist(err) {
		alt := "C:/xampp/htdocs/kesiswaanv2/assets/uploads/foto_siswa"
		if _, err2 := os.Stat(alt); err2 == nil {
			uploadDir = alt
		}
	}
	return Config{Port: port, DBDSN: dsn, DapodikDSN: dapodik, JWTSecret: secret, JWTExpiry: exp, UploadDir: uploadDir}
}

func env(k, def string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return def
}
