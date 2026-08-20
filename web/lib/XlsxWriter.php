<?php
// ============================================================
// XlsxWriter.php — Generate XLSX tanpa library eksternal
// Menggunakan ZipArchive (built-in PHP) + XML
// ============================================================

class XlsxWriter {
    private $sheets  = [];
    private $styles  = [];
    private $sharedStrings = [];
    private $ssIndex = [];

    // Warna preset
    const NAVY   = '1A2744';
    const ORANGE = 'E8560A';
    const GREEN  = '16A34A';
    const AMBER  = 'D97706';
    const RED    = 'DC2626';
    const SLATE  = '6B7280';
    const LIGHT  = 'F7F7F5';
    const WHITE  = 'FFFFFF';

    public function addSheet($name) {
        $idx = count($this->sheets);
        $this->sheets[$idx] = ['name' => $name, 'rows' => [], 'colWidths' => []];
        return $idx;
    }

    public function setColWidths($sheetIdx, $widths) {
        $this->sheets[$sheetIdx]['colWidths'] = $widths;
    }

    // Style: ['bold'=>true,'bg'=>'hex','fg'=>'hex','sz'=>11,'align'=>'center','wrap'=>true,'numFmt'=>'0.0%','border'=>true]
    public function addRow($sheetIdx, $cells) {
        // cells: array of ['v'=>value, 's'=>[style]]
        $this->sheets[$sheetIdx]['rows'][] = $cells;
    }

    private function esc($s) {
        return htmlspecialchars((string)$s, ENT_XML1, 'UTF-8');
    }

    private function styleKey($s) {
        return json_encode($s);
    }

    private function getStyleIdx($s) {
        $key = $this->styleKey($s);
        if (!isset($this->styles[$key])) {
            $this->styles[$key] = count($this->styles);
        }
        return $this->styles[$key];
    }

    private function getStrIdx($s) {
        $s = (string)$s;
        if (!isset($this->ssIndex[$s])) {
            $this->ssIndex[$s] = count($this->sharedStrings);
            $this->sharedStrings[] = $s;
        }
        return $this->ssIndex[$s];
    }

    private function colLetter($n) {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26);
        }
        return $s;
    }

    private function buildStylesXml() {
        // Kumpulkan semua style unik
        $styleList = array_keys($this->styles);
        sort($styleList); // deterministic

        $numFmts = '';
        $numFmtIds = [];
        $customFmtId = 164;
        $builtinFmts = ['General' => 0, '0' => 1, '0.00' => 2, '#,##0' => 3, '#,##0.00' => 4,
                        '0%' => 9, '0.00%' => 10, 'mm/dd/yy' => 14];

        // Kumpulkan custom numFmt
        $usedFmts = [];
        foreach ($styleList as $sk) {
            $s = json_decode($sk, true);
            if (!empty($s['numFmt']) && !isset($builtinFmts[$s['numFmt']])) {
                if (!isset($usedFmts[$s['numFmt']])) {
                    $usedFmts[$s['numFmt']] = $customFmtId++;
                }
            }
        }

        if ($usedFmts) {
            $numFmts = '<numFmts count="'.count($usedFmts).'">';
            foreach ($usedFmts as $fmt => $fid) {
                $numFmts .= '<numFmt numFmtId="'.$fid.'" formatCode="'.htmlspecialchars($fmt, ENT_XML1).'"/>';
            }
            $numFmts .= '</numFmts>';
        }

        $fonts = '<fonts count="'.((count($styleList)+1)).'">'.
            '<font><sz val="10"/><name val="Arial"/><color rgb="FF000000"/></font>';
        $fills = '<fills count="'.((count($styleList)+3)).'">'.
            '<fill><patternFill patternType="none"/></fill>'.
            '<fill><patternFill patternType="gray125"/></fill>';
        $borders = '<borders count="2">'.
            '<border><left/><right/><top/><bottom/><diagonal/></border>'.
            '<border><left style="thin"><color rgb="FFD0D0D0"/></left>'.
            '<right style="thin"><color rgb="FFD0D0D0"/></right>'.
            '<top style="thin"><color rgb="FFD0D0D0"/></top>'.
            '<bottom style="thin"><color rgb="FFD0D0D0"/></bottom>'.
            '<diagonal/></border></borders>';
        $cellXfs = '<cellXfs><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';

        foreach ($styleList as $sk) {
            $s    = json_decode($sk, true);
            $bold = !empty($s['bold']) ? '<b/>' : '';
            $fg   = !empty($s['fg'])   ? $s['fg'] : '000000';
            $sz   = !empty($s['sz'])   ? $s['sz'] : 10;
            $fonts .= '<font>'.$bold.'<sz val="'.$sz.'"/><name val="Arial"/>'.
                      '<color rgb="FF'.ltrim($fg,'#').'"/></font>';

            $bg = !empty($s['bg']) ? $s['bg'] : null;
            if ($bg) {
                $fills .= '<fill><patternFill patternType="solid">'.
                          '<fgColor rgb="FF'.ltrim($bg,'#').'"/>'.
                          '<bgColor indexed="64"/></patternFill></fill>';
            }

            $fontId   = array_search($sk, $styleList) + 1;
            $fillId   = $bg ? ($fontId + 1) : 0;
            $borderId = !empty($s['border']) ? 1 : 0;

            $align = !empty($s['align']) ? $s['align'] : 'left';
            $wrap  = !empty($s['wrap'])  ? '1' : '0';
            $vert  = 'center';

            $numFmt = !empty($s['numFmt']) ? $s['numFmt'] : 'General';
            if (isset($builtinFmts[$numFmt])) {
                $nfId = $builtinFmts[$numFmt];
            } elseif (isset($usedFmts[$numFmt])) {
                $nfId = $usedFmts[$numFmt];
            } else {
                $nfId = 0;
            }

            $applyNum    = $nfId > 0 ? ' applyNumberFormat="1"' : '';
            $applyFont   = ' applyFont="1"';
            $applyFill   = $bg ? ' applyFill="1"' : '';
            $applyBorder = $borderId ? ' applyBorder="1"' : '';
            $applyAlign  = ' applyAlignment="1"';

            $cellXfs .= '<xf numFmtId="'.$nfId.'" fontId="'.$fontId.'" fillId="'.$fillId.
                        '" borderId="'.$borderId.'" xfId="0"'.
                        $applyNum.$applyFont.$applyFill.$applyBorder.$applyAlign.'>'.
                        '<alignment horizontal="'.$align.'" vertical="'.$vert.'" wrapText="'.$wrap.'"/>'.
                        '</xf>';
        }

        $fonts   .= '</fonts>';
        $fills   .= '</fills>';
        $cellXfs .= '</cellXfs>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
               '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
               $numFmts.$fonts.$fills.$borders.$cellXfs.
               '</styleSheet>';
    }

    private function buildSheetXml($sheetIdx) {
        $sheet = $this->sheets[$sheetIdx];
        $xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                 '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Col widths
        if (!empty($sheet['colWidths'])) {
            $xml .= '<cols>';
            foreach ($sheet['colWidths'] as $ci => $w) {
                $xml .= '<col min="'.($ci+1).'" max="'.($ci+1).'" width="'.$w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($sheet['rows'] as $ri => $row) {
            $rowNum = $ri + 1;
            $xml .= '<row r="'.$rowNum.'" ht="18" customHeight="1">';
            foreach ($row as $ci => $cell) {
                $col    = $this->colLetter($ci + 1);
                $ref    = $col . $rowNum;
                $v      = $cell['v'] ?? '';
                $s      = $cell['s'] ?? [];
                $sIdx   = $this->getStyleIdx($s);
                $sAttr  = $sIdx > 0 ? ' s="'.$sIdx.'"' : '';

                if ($v === null || $v === '') {
                    $xml .= '<c r="'.$ref.'"'.$sAttr.'/>';
                } elseif (is_numeric($v) && !isset($s['forceStr'])) {
                    $xml .= '<c r="'.$ref.'"'.$sAttr.'><v>'.$v.'</v></c>';
                } else {
                    $ssIdx = $this->getStrIdx($v);
                    $xml .= '<c r="'.$ref.'" t="s"'.$sAttr.'><v>'.$ssIdx.'</v></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function buildSharedStringsXml() {
        $total = count($this->sharedStrings);
        $xml   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                 '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'.
                 ' count="'.$total.'" uniqueCount="'.$total.'">';
        foreach ($this->sharedStrings as $s) {
            $xml .= '<si><t xml:space="preserve">'.$this->esc($s).'</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }

    public function save($path) {
        // Pre-process: build all style indices
        foreach ($this->sheets as $si => $sheet) {
            foreach ($sheet['rows'] as $row) {
                foreach ($row as $cell) {
                    $s = $cell['s'] ?? [];
                    $this->getStyleIdx($s);
                    $v = $cell['v'] ?? '';
                    if (!is_numeric($v) || isset(($cell['s'] ?? [])['forceStr'])) {
                        $this->getStrIdx($v);
                    }
                }
            }
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create XLSX: $path");
        }

        // [Content_Types].xml
        $sheetTypes = '';
        foreach ($this->sheets as $i => $_) {
            $sheetTypes .= '<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml"'.
                           ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'.
            $sheetTypes.
            '</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '</Relationships>');

        // xl/workbook.xml
        $sheetEls  = '';
        $sheetRels = '';
        foreach ($this->sheets as $i => $sheet) {
            $rId = 'rId' . ($i + 1);
            $sheetEls  .= '<sheet name="'.$this->esc($sheet['name']).'" sheetId="'.($i+1).'" r:id="'.$rId.'"/>';
            $sheetRels .= '<Relationship Id="'.$rId.'" '.
                          'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '.
                          'Target="worksheets/sheet'.($i+1).'.xml"/>';
        }
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '.
            'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheets>'.$sheetEls.'</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            $sheetRels.
            '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'.
            '<Relationship Id="rIdSS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'.
            '</Relationships>');

        // Sheets
        foreach ($this->sheets as $i => $_) {
            $zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml', $this->buildSheetXml($i));
        }

        // Styles & SharedStrings
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->buildSharedStringsXml());

        $zip->close();
    }
}