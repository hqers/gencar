<?php
// ============================================================
// XlsxReader.php — Baca file XLSX tanpa library eksternal
// Pasangan baca dari XlsxWriter.php (pakai ZipArchive + SimpleXML bawaan PHP)
// ============================================================

class XlsxReader {
    private $zip;
    private $sharedStrings = [];
    private $sheets        = []; // name => path (xl/worksheets/sheetN.xml)

    public function __construct($path) {
        $this->zip = new ZipArchive();
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Tidak bisa membuka file XLSX: $path");
        }
        $this->loadSharedStrings();
        $this->loadSheetList();
    }

    public function __destruct() {
        if ($this->zip) { $this->zip->close(); }
    }

    /** Daftar nama sheet sesuai urutan di workbook */
    public function sheetNames() {
        return array_keys($this->sheets);
    }

    private function loadSharedStrings() {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) { return; }
        $sx = simplexml_load_string($xml);
        if (!$sx) { return; }
        foreach ($sx->si as $si) {
            // <si> bisa berisi <t> langsung atau beberapa <r><t>
            if (isset($si->t)) {
                $this->sharedStrings[] = (string)$si->t;
            } else {
                $text = '';
                foreach ($si->r as $r) { $text .= (string)$r->t; }
                $this->sharedStrings[] = $text;
            }
        }
    }

    private function loadSheetList() {
        $wbXml = $this->zip->getFromName('xl/workbook.xml');
        $relXml = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wbXml === false || $relXml === false) {
            throw new RuntimeException('File XLSX tidak valid (workbook.xml/rels tidak ditemukan).');
        }
        $wb  = simplexml_load_string($wbXml);
        $rel = simplexml_load_string($relXml);

        // Map r:id -> target path
        $ns = $rel->getNamespaces(true);
        $ridToTarget = [];
        foreach ($rel->Relationship as $r) {
            $attrs = $r->attributes();
            $ridToTarget[(string)$attrs['Id']] = (string)$attrs['Target'];
        }

        $wbNs = $wb->getNamespaces(true);
        $rNs  = $wbNs['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        foreach ($wb->sheets->sheet as $sheet) {
            $attrs = $sheet->attributes();
            $name  = (string)$attrs['name'];
            $attrsR = $sheet->attributes($rNs);
            $rid   = (string)$attrsR['id'];
            $target = $ridToTarget[$rid] ?? null;
            if ($target) {
                // Target biasanya relatif ke xl/, mis. "worksheets/sheet1.xml"
                $path = 'xl/' . ltrim($target, '/');
                $this->sheets[$name] = $path;
            }
        }
    }

    /** Konversi ref sel "C5" -> [colIndex(0-based), rowIndex(1-based)] */
    private function refToCol($ref) {
        if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) { return [0, 1]; }
        $colStr = $m[1];
        $col = 0;
        for ($i = 0; $i < strlen($colStr); $i++) {
            $col = $col * 26 + (ord($colStr[$i]) - 64);
        }
        return [$col - 1, (int)$m[2]];
    }

    /**
     * Baca semua baris dari satu sheet.
     * Return: array asosiatif [rowNum => [colIndex => value]], 1-based row & 0-based col.
     */
    public function readSheet($sheetName) {
        if (!isset($this->sheets[$sheetName])) {
            throw new RuntimeException("Sheet '$sheetName' tidak ditemukan.");
        }
        $xml = $this->zip->getFromName($this->sheets[$sheetName]);
        if ($xml === false) {
            throw new RuntimeException("Gagal membaca konten sheet '$sheetName'.");
        }
        $sx = simplexml_load_string($xml);
        $rows = [];
        if (!isset($sx->sheetData->row)) { return $rows; }

        foreach ($sx->sheetData->row as $row) {
            $rAttrs = $row->attributes();
            $rowNum = (int)$rAttrs['r'];
            $cells = [];
            foreach ($row->c as $c) {
                $cAttrs = $c->attributes();
                $ref = (string)$cAttrs['r'];
                [$colIdx, ] = $this->refToCol($ref);
                $type = (string)$cAttrs['t'];

                $value = null;
                if ($type === 's') { // shared string
                    $idx = isset($c->v) ? (int)$c->v : null;
                    $value = $idx !== null ? ($this->sharedStrings[$idx] ?? '') : '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($c->is->t) ? (string)$c->is->t : '';
                } elseif ($type === 'str') { // formula result string
                    $value = isset($c->v) ? (string)$c->v : '';
                } elseif ($type === 'b') { // boolean
                    $value = isset($c->v) ? ((string)$c->v === '1') : null;
                } else { // numeric / kosong
                    $value = isset($c->v) ? (string)$c->v : null;
                    if ($value !== null && is_numeric($value)) {
                        $value = (strpos($value, '.') !== false) ? (float)$value : (int)$value;
                    }
                }
                $cells[$colIdx] = $value;
            }
            $rows[$rowNum] = $cells;
        }
        return $rows;
    }

    /** Baca sheet sebagai array numerik biasa (baris berurutan mulai row 1), kolom dari 0 s/d maxCol */
    public function readSheetAsGrid($sheetName, $maxCol = 20) {
        $raw = $this->readSheet($sheetName);
        if (!$raw) { return []; }
        $maxRow = max(array_keys($raw));
        $grid = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            $line = [];
            for ($c = 0; $c < $maxCol; $c++) {
                $line[$c] = $raw[$r][$c] ?? null;
            }
            $grid[$r] = $line;
        }
        return $grid;
    }
}
