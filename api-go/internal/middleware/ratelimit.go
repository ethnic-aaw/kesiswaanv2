package middleware

import (
	"net/http"
	"sync"
	"time"

	"kesiswaan/internal/helper"
)

type attempt struct {
	count int
	reset time.Time
}

var (
	mu       sync.Mutex
	attempts = map[string]*attempt{}
)

func RateLimitLogin(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// only limit POST /api/auth/login — caller wraps only that route, so just check here
		// need username from body? we peek via query param fallback: limit by IP if no username
		// simpler: limit by IP + username combined after parsing — do generic IP limit
		ip := r.RemoteAddr
		// use X-Forwarded-For if present
		if fwd := r.Header.Get("X-Forwarded-For"); fwd != "" {
			ip = fwd
		}
		mu.Lock()
		a := attempts[ip]
		now := time.Now()
		if a == nil || now.After(a.reset) {
			a = &attempt{count: 0, reset: now.Add(15 * time.Minute)}
			attempts[ip] = a
		}
		if a.count >= 20 { // global IP limit 20/15m
			mu.Unlock()
			helper.Error(w, 429, "terlalu banyak percobaan, coba lagi 15 menit")
			return
		}
		a.count++
		mu.Unlock()
		next.ServeHTTP(w, r)
	})
}

// Per-username limit checked inside auth handler (needs DB username)
var userAttempts = map[string]*attempt{}
var userMu sync.Mutex

func CheckUserRateLimit(username string) bool {
	userMu.Lock()
	defer userMu.Unlock()
	now := time.Now()
	a := userAttempts[username]
	if a == nil || now.After(a.reset) {
		userAttempts[username] = &attempt{count: 1, reset: now.Add(15 * time.Minute)}
		return true
	}
	if a.count >= 5 {
		return false
	}
	a.count++
	return true
}

func ResetUserRateLimit(username string) {
	userMu.Lock()
	delete(userAttempts, username)
	userMu.Unlock()
}
