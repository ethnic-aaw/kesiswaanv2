Add-Type -AssemblyName Microsoft.Office.Interop.Excel
$excel = New-Object -ComObject Excel.Application
$workbook = $excel.Workbooks.Open('C:\xampp\htdocs\kesiswaanv2\daftar_pd-SMKN 1 LEUWIMUNDING-2026-09-16 07_45_04.xls')
foreach ($sheet in $workbook.Sheets) {
    Write-Host "Sheet: $($sheet.Name)"
    $used = $sheet.UsedRange
    Write-Host "Rows: $($used.Rows.Count), Cols: $($used.Columns.Count)"
    for ($r = 1; $r -le [math]::Min($used.Rows.Count, 10); $r++) {
        $vals = @()
        for ($c = 1; $c -le $used.Columns.Count; $c++) {
            $cell = $used.Item($r, $c)
            $vals += $cell.Text
        }
        Write-Host "Row $r: $($vals -join ', ')"
    }
}
$workbook.Close($false)
$excel.Quit()
