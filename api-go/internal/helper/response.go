package helper

import (
	"encoding/json"
	"net/http"

	"kesiswaan/internal/model"
)

func JSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(v)
}

func Success(w http.ResponseWriter, data any) {
	JSON(w, 200, model.APIResponse{Success: true, Data: data})
}

func Created(w http.ResponseWriter, data any) {
	JSON(w, 201, model.APIResponse{Success: true, Data: data})
}

func Error(w http.ResponseWriter, status int, msg string) {
	JSON(w, status, model.APIResponse{Success: false, Error: msg})
}

func Message(w http.ResponseWriter, msg string, data any) {
	JSON(w, 200, model.APIResponse{Success: true, Message: msg, Data: data})
}
