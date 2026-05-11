<?php

/**
 * Minimal XLSX reader for environments without ext-zip/PhpSpreadsheet.
 * Supports shared strings and the first worksheet, enough for tabular imports.
 */
class MinimalXlsxReader {
    private string $data;
    private array $entries = [];

    public function __construct(string $path) {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new RuntimeException('File Excel tidak bisa dibaca.');
        }
        $this->data = $data;
        $this->readCentralDirectory();
    }

    public function sheetRows(string $preferredSheet = 'ALL'): array {
        $sheetPath = $this->resolveSheetPath($preferredSheet);
        $shared = $this->readSharedStrings();
        $xml = $this->entryXml($sheetPath);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];
                $col = preg_replace('/[^A-Z]/', '', $ref);
                if ($col === '') continue;
                $rowData[$col] = $this->cellValue($cell, $shared);
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    private function readCentralDirectory(): void {
        $pos = strrpos($this->data, "PK\x05\x06");
        if ($pos === false) {
            throw new RuntimeException('Format XLSX tidak valid.');
        }

        $eocd = substr($this->data, $pos + 4, 18);
        $end = unpack('vdisk/vstartDisk/ventriesDisk/ventries/Vsize/Voffset/vcomment', $eocd);
        $offset = (int)$end['offset'];
        $entries = (int)$end['entries'];

        for ($i = 0; $i < $entries; $i++) {
            if (substr($this->data, $offset, 4) !== "PK\x01\x02") {
                break;
            }
            $h = unpack(
                'vverMade/vverNeed/vflag/vmethod/vmtime/vmdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen/vcommentLen/vdisk/vintAttr/VextAttr/VlocalOffset',
                substr($this->data, $offset + 4, 42)
            );
            $name = substr($this->data, $offset + 46, $h['nameLen']);
            $this->entries[$name] = [
                'method' => (int)$h['method'],
                'comp' => (int)$h['comp'],
                'offset' => (int)$h['localOffset'],
            ];
            $offset += 46 + (int)$h['nameLen'] + (int)$h['extraLen'] + (int)$h['commentLen'];
        }
    }

    private function entryData(string $name): string {
        if (empty($this->entries[$name])) {
            throw new RuntimeException("Entry XLSX tidak ditemukan: $name");
        }
        $entry = $this->entries[$name];
        $offset = $entry['offset'];
        if (substr($this->data, $offset, 4) !== "PK\x03\x04") {
            throw new RuntimeException('Header ZIP lokal tidak valid.');
        }
        $h = unpack('vver/vflag/vmethod/vmtime/vmdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen', substr($this->data, $offset + 4, 26));
        $start = $offset + 30 + (int)$h['nameLen'] + (int)$h['extraLen'];
        $compressed = substr($this->data, $start, $entry['comp']);

        if ($entry['method'] === 0) return $compressed;
        if ($entry['method'] === 8) {
            $plain = gzinflate($compressed);
            if ($plain === false) {
                throw new RuntimeException('Gagal mengekstrak isi XLSX.');
            }
            return $plain;
        }
        throw new RuntimeException('Metode kompresi XLSX tidak didukung.');
    }

    private function entryXml(string $name): SimpleXMLElement {
        $xml = simplexml_load_string($this->entryData($name));
        if (!$xml) {
            throw new RuntimeException("XML XLSX rusak: $name");
        }
        return $xml;
    }

    private function readSharedStrings(): array {
        if (empty($this->entries['xl/sharedStrings.xml'])) return [];
        $xml = $this->entryXml('xl/sharedStrings.xml');
        $strings = [];
        foreach ($xml->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } else {
                foreach ($si->r as $run) $text .= (string)$run->t;
            }
            $strings[] = $text;
        }
        return $strings;
    }

    private function resolveSheetPath(string $preferredSheet): string {
        $workbook = $this->entryXml('xl/workbook.xml');
        $rels = $this->entryXml('xl/_rels/workbook.xml.rels');
        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string)$rel['Id']] = 'xl/'.ltrim((string)$rel['Target'], '/');
        }

        $ns = $workbook->getNamespaces(true);
        foreach ($workbook->sheets->sheet as $sheet) {
            $attrs = $sheet->attributes($ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string)$attrs['id'];
            if (strcasecmp((string)$sheet['name'], $preferredSheet) === 0 && isset($relMap[$rid])) {
                return $relMap[$rid];
            }
        }

        $first = $workbook->sheets->sheet[0] ?? null;
        if (!$first) {
            throw new RuntimeException('Worksheet tidak ditemukan.');
        }
        $attrs = $first->attributes($ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid = (string)$attrs['id'];
        if (empty($relMap[$rid])) {
            throw new RuntimeException('Relasi worksheet tidak valid.');
        }
        return $relMap[$rid];
    }

    private function cellValue(SimpleXMLElement $cell, array $shared): string {
        $type = (string)$cell['t'];
        if ($type === 's') {
            $idx = (int)($cell->v ?? -1);
            return isset($shared[$idx]) ? trim((string)$shared[$idx]) : '';
        }
        if ($type === 'inlineStr') {
            return trim((string)($cell->is->t ?? ''));
        }
        return trim((string)($cell->v ?? ''));
    }
}
