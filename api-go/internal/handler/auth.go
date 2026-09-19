package handler

import (
	"database/sql"
	"encoding/json"
	"net/http"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"golang.org/x/crypto/bcrypt"
	"kesiswaan/internal/helper"
	"kesiswaan/internal/middleware"
)

type AuthHandler struct {
	DB        *sql.DB
	JWTSecret string
	JWTExpiry time.Duration
}

func (h *AuthHandler) Login(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Username string `json:"username"`
		Password string `json:"password"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		helper.Error(w, 400, "payload tidak valid")
		return
	}
	if req.Username == "" || req.Password == "" {
		helper.Error(w, 400, "username dan password wajib")
		return
	}
	if !middleware.CheckUserRateLimit(req.Username) {
		helper.Error(w, 429, "terlalu banyak percobaan, coba lagi 15 menit")
		return
	}
	var id int64
	var username, nama, role, status, hash string
	err := h.DB.QueryRow(`SELECT id, username, nama, role, status, password_hash FROM users WHERE username=? AND deleted_at IS NULL`, req.Username).Scan(&id, &username, &nama, &role, &status, &hash)
	if err != nil {
		helper.Error(w, 401, "username atau password salah")
		return
	}
	if status == "Nonaktif" {
		helper.Error(w, 401, "akun nonaktif")
		return
	}
	if err := bcrypt.CompareHashAndPassword([]byte(hash), []byte(req.Password)); err != nil {
		helper.Error(w, 401, "username atau password salah")
		return
	}
	middleware.ResetUserRateLimit(req.Username)

	claims := middleware.Claims{
		UserID:   id,
		Username: username,
		Role:     role,
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(h.JWTExpiry)),
			IssuedAt:  jwt.NewNumericDate(time.Now()),
		},
	}
	token, err := jwt.NewWithClaims(jwt.SigningMethodHS256, claims).SignedString([]byte(h.JWTSecret))
	if err != nil {
		helper.Error(w, 500, "gagal buat token")
		return
	}
	// set cookie too
	http.SetCookie(w, &http.Cookie{
		Name:     "token",
		Value:    token,
		Path:     "/",
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
		Expires:  time.Now().Add(h.JWTExpiry),
	})
	helper.Success(w, map[string]any{
		"token": token,
		"user":  map[string]any{"id": id, "username": username, "nama": nama, "role": role},
	})
}

func (h *AuthHandler) Me(w http.ResponseWriter, r *http.Request) {
	u := middleware.CurrentUser(r)
	helper.Success(w, u)
}
