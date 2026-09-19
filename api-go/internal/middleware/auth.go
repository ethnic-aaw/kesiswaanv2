package middleware

import (
	"context"
	"net/http"
	"strings"

	"github.com/golang-jwt/jwt/v5"
	"kesiswaan/internal/helper"
)

type ctxKey string

const UserKey ctxKey = "user"

type Claims struct {
	UserID   int64  `json:"uid"`
	Username string `json:"username"`
	Role     string `json:"role"`
	jwt.RegisteredClaims
}

func Auth(secret string) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			tokenStr := ""
			if h := r.Header.Get("Authorization"); strings.HasPrefix(h, "Bearer ") {
				tokenStr = strings.TrimPrefix(h, "Bearer ")
			} else if c, err := r.Cookie("token"); err == nil {
				tokenStr = c.Value
			}
			if tokenStr == "" {
				helper.Error(w, 401, "unauthorized")
				return
			}
			claims := &Claims{}
			_, err := jwt.ParseWithClaims(tokenStr, claims, func(t *jwt.Token) (any, error) {
				return []byte(secret), nil
			})
			if err != nil {
				helper.Error(w, 401, "invalid token")
				return
			}
			ctx := context.WithValue(r.Context(), UserKey, claims)
			next.ServeHTTP(w, r.WithContext(ctx))
		})
	}
}

func CurrentUser(r *http.Request) *Claims {
	if v := r.Context().Value(UserKey); v != nil {
		return v.(*Claims)
	}
	return nil
}

func RequireRole(roles ...string) func(http.Handler) http.Handler {
	allow := map[string]bool{}
	for _, r := range roles {
		allow[r] = true
	}
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			u := CurrentUser(r)
			if u == nil || !allow[u.Role] {
				helper.Error(w, 403, "forbidden")
				return
			}
			next.ServeHTTP(w, r)
		})
	}
}
