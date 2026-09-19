package helper

import (
	"io"
	"os"
	"path/filepath"
)

func WriteFile(fullPath string, src io.Reader) error {
	if err := os.MkdirAll(filepath.Dir(fullPath), 0755); err != nil {
		return err
	}
	f, err := os.Create(fullPath)
	if err != nil {
		return err
	}
	defer f.Close()
	_, err = io.Copy(f, src)
	return err
}
