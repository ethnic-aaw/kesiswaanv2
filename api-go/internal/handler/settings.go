package handler

import (
	"database/sql"
	"encoding/json"
	"net/http"

	"kesiswaan/internal/helper"
)

type SettingsHandler struct{ DB *sql.DB }

func (h *SettingsHandler) Get(w http.ResponseWriter, r *http.Request) {
	var val string
	_ = h.DB.QueryRow(`SELECT value FROM settings WHERE key_name='threshold_poin_kritis'`).Scan(&val)
	if val == "" {
		val = "76"
	}
	helper.Success(w, map[string]string{"threshold_poin_kritis": val})
}

func (h *SettingsHandler) Put(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Value *int `json:"value"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Value == nil {
		helper.Error(w, 400, "value wajib angka")
		return
	}
	v := *req.Value
	if v < 1 || v > 100 {
		helper.Error(w, 400, "threshold 1-100")
		return
	}
	_, err := h.DB.Exec(`INSERT INTO settings(key_name,value) VALUES('threshold_poin_kritis',?) ON DUPLICATE KEY UPDATE value=VALUES(value)`, v)
	if err != nil {
		helper.Error(w, 500, err.Error())
		return
	}
	helper.Message(w, "threshold disimpan", map[string]int{"threshold_poin_kritis": v})
}
