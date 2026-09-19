package handler

import (
	"archive/zip"
	"bytes"
	"encoding/csv"
	"encoding/xml"
	"io"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"kesiswaan/internal/helper"
)

// ImportExcel: terima .csv / .xls (HTML-disguised) / .xlsx
// FormData field "file", optional query ?dry_run=1 (preview tanpa simpan) dan ?auto_kelas=1
func (h *SiswaHandler) ImportExcel(w http.ResponseWriter, r *http.Request) {
	_ = r.ParseMultipartForm(10 << 20) // 10MB
	file, hdr, err := r.FormFile("file")
	if err != nil {
		helper.Error(w, 400, "file wajib (field name=file) — .csv/.xls/.xlsx max 10MB")
		return
	}
	defer file.Close()

	dryRun := r.URL.Query().Get("dry_run") == "1"
	autoKelas := r.URL.Query().Get("auto_kelas") == "1"

	buf, err := io.ReadAll(file)
	if err != nil || len(buf) == 0 {
		helper.Error(w, 400, "file kosong / gagal baca")
		return
	}
	filename := ""
	if hdr != nil {
		filename = strings.ToLower(hdr.Filename)
	}
	// detect by content + extension
	isHTML := bytes.Contains(bytes.ToLower(buf[:minInt(len(buf), 2048)]), []byte("<table")) ||
		bytes.Contains(bytes.ToLower(buf[:minInt(len(buf), 2048)]), []byte("<html"))
	isCSV := strings.HasSuffix(filename, ".csv")

	var rows [][]string
	var parseErr string

	switch {
	case isCSV:
		rows, parseErr = parseCSVBytes(buf)
	case isHTML:
		rows, parseErr = parseHTMLTable(buf)
	case strings.HasSuffix(filename, ".xlsx") || (len(buf) > 2 && buf[0] == 'P' && buf[1] == 'K'):
		rows, parseErr = parseXLSXBytes(buf)
	default:
		// try HTML first, then XLSX (zip), then CSV
		if r2, e2 := parseHTMLTable(buf); e2 == "" && len(r2) > 1 {
			rows = r2
		} else if r2, e2 := parseXLSXBytes(buf); e2 == "" && len(r2) > 1 {
			rows = r2
		} else if r2, e2 := parseCSVBytes(buf); e2 == "" {
			rows = r2
		} else {
			helper.Error(w, 400, "format tidak dikenali — upload .csv (delimiter ; atau ,) atau .xlsx")
			return
		}
	}
	if parseErr != "" {
		helper.Error(w, 400, "gagal parse file: "+parseErr)
		return
	}
	if len(rows) < 1 {
		helper.Error(w, 400, "file kosong / tidak ada baris")
		return
	}

	// auto-detect header row
	headerIdx, colMap := detectColumns(rows)
	if headerIdx == -1 {
		helper.Error(w, 400, "header tidak dikenali — pastikan ada kolom NIPD/NIS/NISN, Nama, dan Rombel/Kelas di baris header")
		return
	}
	dataRows := rows[headerIdx+1:]
	// Dapodik has a secondary header row after main header (Data Ayah/Ibu/Wali sub-headers)
	if len(dataRows) > 0 && isSubHeaderRow(dataRows[0]) {
		dataRows = dataRows[1:]
	}

	// collect
	type RowRes struct {
		Line   int    `json:"line"`
		NIPD   string `json:"nipd"`
		Nama   string `json:"nama"`
		Kelas  string `json:"kelas"`
		JK     string `json:"jk"`
		Status string `json:"status"`
		Error  string `json:"error,omitempty"`
	}
	var results []RowRes
	okCount, failCount := 0, 0
	seenInFile := map[string]int{} // nipd -> line

	// preload kelas map
	kelasMap := map[string]int64{} // lower(nama_kelas) -> id
	if rs, err := h.DB.Query(`SELECT id, nama_kelas FROM kelas WHERE deleted_at IS NULL`); err == nil {
		defer rs.Close()
		for rs.Next() {
			var id int64
			var nm string
			rs.Scan(&id, &nm)
			kelasMap[strings.ToLower(strings.TrimSpace(nm))] = id
		}
	}

	for i, rec := range dataRows {
		lineNo := headerIdx + 2 + i // 1-based + header
		// adjust lineNo if we skipped sub-header
		if len(rows) > headerIdx+1 && isSubHeaderRow(rows[headerIdx+1]) {
			lineNo++
		}
		if isEmptyRow(rec) {
			continue
		}
		nipd := cellByMap(rec, colMap, "nipd")
		nama := cellByMap(rec, colMap, "nama")
		kelasNama := cellByMap(rec, colMap, "kelas")
		jkRaw := cellByMap(rec, colMap, "jk")
		tempatLahir := cellByMap(rec, colMap, "tempat_lahir")
		tglLahir := cellByMap(rec, colMap, "tanggal_lahir")
		namaOrtu := cellByMap(rec, colMap, "nama_ortu")

		nipd = strings.TrimSpace(nipd)
		nama = strings.TrimSpace(nama)
		kelasNama = strings.TrimSpace(kelasNama)
		jk := normalizeJK(jkRaw)
		tglLahir = normalizeDate(tglLahir)

		rr := RowRes{Line: lineNo, NIPD: nipd, Nama: nama, Kelas: kelasNama, JK: jk}

		if nipd == "" {
			rr.Status = "gagal"; rr.Error = "NIPD/NISN kosong"
			results = append(results, rr); failCount++; continue
		}
		if len(nipd) > 20 {
			rr.Status = "gagal"; rr.Error = "NIPD max 20 karakter"
			results = append(results, rr); failCount++; continue
		}
		if nama == "" {
			rr.Status = "gagal"; rr.Error = "Nama kosong"
			results = append(results, rr); failCount++; continue
		}
		if prev, dup := seenInFile[nipd]; dup {
			rr.Status = "gagal"; rr.Error = "NIPD duplikat di file (baris "+strconv.Itoa(prev)+")"
			results = append(results, rr); failCount++; continue
		}
		seenInFile[nipd] = lineNo

		var exists int
		h.DB.QueryRow(`SELECT COUNT(*) FROM siswa WHERE nipd=? AND deleted_at IS NULL`, nipd).Scan(&exists)
		if exists > 0 {
			rr.Status = "gagal"; rr.Error = "NIPD duplikat di DB"
			results = append(results, rr); failCount++; continue
		}

		// resolve kelas
		var kelasID *int64
		if kelasNama != "" {
			key := strings.ToLower(kelasNama)
			if id, ok := kelasMap[key]; ok {
				kelasID = &id
			} else {
				if autoKelas {
					tingkat := detectTingkat(kelasNama)
					res, err := h.DB.Exec(`INSERT INTO kelas(nama_kelas, tingkat) VALUES(?,?)`, kelasNama, tingkat)
					if err == nil {
						if nid, e := res.LastInsertId(); e == nil {
							kelasMap[key] = nid
							kelasID = &nid
						}
					}
				}
				if kelasID == nil {
					rr.Status = "gagal"; rr.Error = "kelas tidak ditemukan: " + kelasNama
					results = append(results, rr); failCount++; continue
				}
			}
		}

		if dryRun {
			rr.Status = "ok"
			results = append(results, rr); okCount++; continue
		}

		// insert — optional fields only if present in colMap
		var tempatPtr, tglPtr, ortuPtr *string
		if _, ok := colMap["tempat_lahir"]; ok && tempatLahir != "" {
			tempatPtr = &tempatLahir
		}
		if _, ok := colMap["tanggal_lahir"]; ok && tglLahir != "" {
			tglPtr = &tglLahir
		}
		if _, ok := colMap["nama_ortu"]; ok && namaOrtu != "" {
			namaOrtu = strings.TrimSpace(namaOrtu)
			if namaOrtu != "" {
				ortuPtr = &namaOrtu
			}
		}
		_, err := h.DB.Exec(`INSERT INTO siswa(nipd,nama,jenis_kelamin,kelas_id,tempat_lahir,tanggal_lahir,nama_ortu,status) VALUES(?,?,?,?,?,?,?,?)`,
			nipd, nama, jk, kelasID, tempatPtr, tglPtr, ortuPtr, "Aktif")
		if err != nil {
			rr.Status = "gagal"; rr.Error = err.Error()
			results = append(results, rr); failCount++; continue
		}
		rr.Status = "ok"
		results = append(results, rr)
		okCount++
	}

	if results == nil {
		results = []RowRes{}
	}
	helper.Success(w, map[string]any{"berhasil": okCount, "gagal": failCount, "rincian": results, "dry_run": dryRun})
}

// --- helpers ---

func minInt(a, b int) int { if a < b { return a }; return b }

func isEmptyRow(r []string) bool {
	for _, c := range r {
		if strings.TrimSpace(c) != "" {
			return false
		}
	}
	return true
}

func isSubHeaderRow(r []string) bool {
	for _, c := range r {
		lc := strings.ToLower(strings.TrimSpace(c))
		if lc == "tahun lahir" || lc == "jenjang pendidikan" || lc == "penghasilan" {
			return true
		}
	}
	// also detect Dapodik secondary row that has "Nama" at Data Ayah offset but empty at col 1
	if len(r) > 24 && strings.TrimSpace(r[24]) == "Nama" && strings.TrimSpace(r[0]) == "" {
		return true
	}
	return false
}

func cellByMap(rec []string, m map[string]int, key string) string {
	idx, ok := m[key]
	if !ok || idx < 0 || idx >= len(rec) {
		return ""
	}
	return strings.TrimSpace(rec[idx])
}

var htmlTagRe = regexp.MustCompile(`(?is)<[^>]*>`)
var htmlEntityRe = strings.NewReplacer("&nbsp;", " ", "&amp;", "&", "&lt;", "<", "&gt;", ">", "&quot;", `"`, "&#39;", "'")

func stripHTML(s string) string {
	s = htmlTagRe.ReplaceAllString(s, "")
	s = htmlEntityRe.Replace(s)
	return strings.TrimSpace(s)
}

func parseHTMLTable(buf []byte) ([][]string, string) {
	s := string(buf)
	trRe := regexp.MustCompile(`(?is)<tr[^>]*>(.*?)</tr>`)
	tdRe := regexp.MustCompile(`(?is)<t[dh][^>]*>(.*?)</t[dh]>`)
	trs := trRe.FindAllStringSubmatch(s, -1)
	if len(trs) == 0 {
		return nil, "tidak ada <tr> di file HTML"
	}
	var rows [][]string
	for _, m := range trs {
		inner := m[1]
		tds := tdRe.FindAllStringSubmatch(inner, -1)
		if len(tds) == 0 {
			continue
		}
		var row []string
		for _, t := range tds {
			row = append(row, stripHTML(t[1]))
		}
		rows = append(rows, row)
	}
	filtered := rows[:0]
	for _, r := range rows {
		nonEmpty := 0
		for _, c := range r {
			if strings.TrimSpace(c) != "" {
				nonEmpty++
			}
		}
		if nonEmpty >= 2 {
			filtered = append(filtered, r)
		} else if len(r) == 1 && strings.TrimSpace(r[0]) != "" {
			continue
		} else {
			filtered = append(filtered, r)
		}
	}
	if len(filtered) == 0 {
		filtered = rows
	}
	return filtered, ""
}

func parseCSVBytes(buf []byte) ([][]string, string) {
	buf = bytes.TrimPrefix(buf, []byte{0xEF, 0xBB, 0xBF})
	// auto-detect delimiter: Dapodik uses ';', generic uses ','
	// try ';' first if it appears more often in header sample
	sample := string(buf[:minInt(len(buf), 4096)])
	semiCount := strings.Count(sample, ";")
	commaCount := strings.Count(sample, ",")
	// prefer ';' when clearly dominant, else try both and pick parsable that yields header
	delims := []rune{',', ';'}
	if semiCount > commaCount {
		delims = []rune{';', ','}
	}
	var lastErr string
	for _, delim := range delims {
		r := csv.NewReader(bytes.NewReader(buf))
		r.Comma = delim
		r.FieldsPerRecord = -1
		r.LazyQuotes = true
		r.TrimLeadingSpace = true
		rows, err := r.ReadAll()
		if err != nil {
			lastErr = err.Error()
			continue
		}
		if len(rows) == 0 {
			continue
		}
		// heuristic: valid parse should have header row with at least 2 of nipd/nama/kelas
		if idx, _ := detectColumns(rows); idx != -1 {
			return rows, ""
		}
		// fallback: if no header detected but rows look reasonable (multiple cols), keep it as candidate
		if len(rows[0]) >= 4 {
			// check if first rows contain "Nama" etc with this delim
			return rows, ""
		}
		lastErr = "header tidak dikenali dengan delimiter " + string(delim)
	}
	if lastErr == "" {
		lastErr = "gagal parse CSV"
	}
	return nil, lastErr
}

// parseXLSXBytes: stdlib zip+xml — no external deps
func parseXLSXBytes(buf []byte) ([][]string, string) {
	zr, err := zip.NewReader(bytes.NewReader(buf), int64(len(buf)))
	if err != nil {
		return nil, "bukan file xlsx/zip valid: " + err.Error()
	}
	var sharedStrings []string
	var sheetData []byte
	for _, f := range zr.File {
		switch f.Name {
		case "xl/sharedStrings.xml":
			rc, _ := f.Open()
			b, _ := io.ReadAll(rc)
			rc.Close()
			sharedStrings = parseSharedStrings(b)
		case "xl/worksheets/sheet1.xml":
			rc, _ := f.Open()
			b, _ := io.ReadAll(rc)
			rc.Close()
			sheetData = b
		}
	}
	if sheetData == nil {
		// try any sheet
		for _, f := range zr.File {
			if strings.HasPrefix(f.Name, "xl/worksheets/sheet") && strings.HasSuffix(f.Name, ".xml") {
				rc, _ := f.Open()
				b, _ := io.ReadAll(rc)
				rc.Close()
				sheetData = b
				break
			}
		}
	}
	if sheetData == nil {
		return nil, "sheet tidak ditemukan di xlsx"
	}
	rows, err := parseSheetXML(sheetData, sharedStrings)
	if err != nil {
		return nil, err.Error()
	}
	return rows, ""
}

func parseSharedStrings(b []byte) []string {
	if len(b) == 0 {
		return nil
	}
	dec := xml.NewDecoder(bytes.NewReader(b))
	var out []string
	var inSI bool
	var inT bool
	var cur strings.Builder
	for {
		tok, err := dec.Token()
		if err != nil {
			break
		}
		switch t := tok.(type) {
		case xml.StartElement:
			switch t.Name.Local {
			case "si":
				inSI = true
				cur.Reset()
			case "t":
				if inSI {
					inT = true
				}
			}
		case xml.EndElement:
			switch t.Name.Local {
			case "si":
				out = append(out, cur.String())
				inSI = false
			case "t":
				inT = false
			}
		case xml.CharData:
			if inSI && inT {
				cur.Write([]byte(t))
			}
		}
	}
	return out
}

func parseSheetXML(b []byte, shared []string) ([][]string, error) {
	dec := xml.NewDecoder(bytes.NewReader(b))
	var rows [][]string
	var curRow []string
	var curRowNum int
	var inRow bool
	var curCellCol int
	var curCellType string
	var curVal string
	var inV, inT bool
	var inlineT strings.Builder
	var inInlineStr bool

	flushRow := func() {
		if !inRow {
			return
		}
		// trim trailing empty cells but keep at least header width
		rows = append(rows, curRow)
	}

	for {
		tok, err := dec.Token()
		if err != nil {
			if err == io.EOF {
				break
			}
			return nil, err
		}
		switch t := tok.(type) {
		case xml.StartElement:
			switch t.Name.Local {
			case "row":
				// flush previous
				if inRow {
					flushRow()
				}
				inRow = true
				curRow = nil
				curRowNum++
				// pre-size hint is not needed; we expand on demand
				_ = curRowNum
			case "c":
				if !inRow {
					continue
				}
				curCellType = ""
				curCellCol = -1
				curVal = ""
				inV = false
				inT = false
				inInlineStr = false
				inlineT.Reset()
				var rAttr string
				for _, a := range t.Attr {
					if a.Name.Local == "r" {
						rAttr = a.Value
					}
					if a.Name.Local == "t" {
						curCellType = a.Value
					}
				}
				if rAttr != "" {
					curCellCol = colLettersToIndex(rAttr)
				} else {
					// sequential
					if curRow == nil {
						curCellCol = 0
					} else {
						curCellCol = len(curRow)
					}
				}
				// ensure curRow length
				if curCellCol >= 0 {
					for len(curRow) <= curCellCol {
						curRow = append(curRow, "")
					}
				}
			case "v":
				inV = true
			case "t":
				// could be sharedString t or inlineStr t — distinguish by curCellType
				if curCellType == "inlineStr" || inInlineStr {
					inT = true
				} else if inInlineStr {
					inT = true
				} else {
					// shared string inline? not inside inlineStr, but charData of si already handled
					// sheet's <t> inside <is> for inlineStr
					if inRow && curCellType == "inlineStr" {
						inT = true
					}
				}
				if inInlineStr {
					inT = true
				}
			case "is":
				inInlineStr = true
			}
		case xml.EndElement:
			switch t.Name.Local {
			case "row":
				flushRow()
				inRow = false
			case "c":
				// commit cell value to curRow[curCellCol]
				var val string
				if curCellType == "s" {
					// shared string
					if idx, err := strconv.Atoi(strings.TrimSpace(curVal)); err == nil && idx >= 0 && idx < len(shared) {
						val = shared[idx]
					} else {
						val = curVal
					}
				} else if curCellType == "inlineStr" {
					val = inlineT.String()
				} else {
					val = strings.TrimSpace(curVal)
					if inlineT.Len() > 0 {
						val = inlineT.String()
					}
				}
				if curCellCol >= 0 && curCellCol < len(curRow) {
					curRow[curCellCol] = val
				} else if curCellCol >= 0 {
					for len(curRow) <= curCellCol {
						curRow = append(curRow, "")
					}
					curRow[curCellCol] = val
				}
				curVal = ""
				inlineT.Reset()
				inV = false
				inT = false
				inInlineStr = false
			case "v":
				inV = false
			case "t":
				inT = false
			case "is":
				inInlineStr = false
			}
		case xml.CharData:
			if inV {
				curVal += string(t)
			} else if inT {
				inlineT.Write([]byte(t))
			}
		}
	}
	// flush last row if still open
	if inRow {
		flushRow()
	}
	// normalize: pad all rows to max width, trim empty trailing rows (title etc kept for header detection)
	maxW := 0
	for _, r := range rows {
		if len(r) > maxW {
			maxW = len(r)
		}
	}
	for i := range rows {
		for len(rows[i]) < maxW {
			rows[i] = append(rows[i], "")
		}
	}
	return rows, nil
}

func colLettersToIndex(cellRef string) int {
	// extract leading letters A-Z
	letters := ""
	for _, ch := range cellRef {
		if ch >= 'A' && ch <= 'Z' {
			letters += string(ch)
		} else if ch >= 'a' && ch <= 'z' {
			letters += string(ch - 'a' + 'A')
		} else {
			break
		}
	}
	if letters == "" {
		return -1
	}
	idx := 0
	for _, ch := range letters {
		idx = idx*26 + int(ch-'A'+1)
	}
	return idx - 1 // 0-based
}

func detectColumns(rows [][]string) (int, map[string]int) {
	for idx, r := range rows {
		if idx > 15 {
			break
		}
		lower := make([]string, len(r))
		for i, c := range r {
			lower[i] = strings.ToLower(strings.TrimSpace(c))
		}
		m := map[string]int{}
		for i, c := range lower {
			if c == "nipd" || c == "nis" || c == "nisn" || c == "no induk" || c == "no. induk" || c == "nipd/nisn" || c == "nis/nipd" || strings.Contains(c, "nipd") || strings.Contains(c, "nisn") && !strings.Contains(c, "nama") {
				if _, ok := m["nipd"]; !ok {
					m["nipd"] = i
				}
			}
			if c == "nama" || c == "nama peserta didik" || c == "nama lengkap" || c == "nama siswa" || strings.HasPrefix(c, "nama") {
				if _, ok := m["nama"]; !ok {
					m["nama"] = i
				}
			}
			if c == "kelas" || c == "rombel" || c == "rombongan belajar" || c == "rombel saat ini" || strings.Contains(c, "rombel") || c == "kelas/rombel" {
				if _, ok := m["kelas"]; !ok {
					m["kelas"] = i
				}
			}
			if c == "jk" || c == "j.k." || c == "jenis kelamin" || c == "kelamin" || c == "l/p" || c == "jk (l/p)" {
				if _, ok := m["jk"]; !ok {
					m["jk"] = i
				}
			}
			if strings.Contains(c, "tempat lahir") || c == "tempat_lahir" {
				if _, ok := m["tempat_lahir"]; !ok {
					m["tempat_lahir"] = i
				}
			}
			if strings.Contains(c, "tanggal lahir") || strings.Contains(c, "tgl lahir") || c == "tanggal_lahir" {
				if _, ok := m["tanggal_lahir"]; !ok {
					m["tanggal_lahir"] = i
				}
			}
			if strings.Contains(c, "nama ortu") || strings.Contains(c, "orang tua") || c == "nama_ortu" {
				if _, ok := m["nama_ortu"]; !ok {
					m["nama_ortu"] = i
				}
			}
		}
		score := 0
		if _, ok := m["nipd"]; ok {
			score++
		}
		if _, ok := m["nama"]; ok {
			score++
		}
		if _, ok := m["kelas"]; ok {
			score++
		}
		if score >= 2 {
			return idx, m
		}
	}
	return -1, nil
}

func normalizeJK(s string) string {
	s = strings.TrimSpace(strings.ToLower(s))
	switch s {
	case "p", "perempuan", "pr", "female", "f":
		return "P"
	default:
		return "L"
	}
}

func normalizeDate(s string) string {
	s = strings.TrimSpace(s)
	if s == "" || s == "-" {
		return ""
	}
	if ok, _ := regexp.MatchString(`^\d{4}-\d{2}-\d{2}`, s); ok {
		return s[:10]
	}
	re := regexp.MustCompile(`(\d{1,2})[/-](\d{1,2})[/-](\d{4})`)
	if m := re.FindStringSubmatch(s); m != nil {
		dd, _ := strconv.Atoi(m[1]); mm, _ := strconv.Atoi(m[2]); yyyy := m[3]
		return yyyy + "-" + pad2(mm) + "-" + pad2(dd)
	}
	return s
}
func pad2(n int) string { if n < 10 { return "0"+strconv.Itoa(n) }; return strconv.Itoa(n) }

func detectTingkat(namaKelas string) string {
	u := strings.ToUpper(strings.TrimSpace(namaKelas))
	if strings.HasPrefix(u, "XII") { return "XII" }
	if strings.HasPrefix(u, "XI") { return "XI" }
	if strings.HasPrefix(u, "IX") { return "IX" }
	if strings.HasPrefix(u, "VIII") { return "VIII" }
	if strings.HasPrefix(u, "VII") { return "VII" }
	if strings.HasPrefix(u, "X ") || u == "X" { return "X" }
	return "X"
}
