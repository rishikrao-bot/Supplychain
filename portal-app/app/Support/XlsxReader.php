<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal .xlsx reader (values only) using PHP's zip and XML support, so no extra libraries are needed
 * on the server. Formulas are read as the value Excel last calculated.
 */
class XlsxReader
{
    private ZipArchive $zip;

    /** @var array<int, string> */
    private array $strings = [];

    /** @var array<string, array{path: string, state: string}> */
    private array $sheets = [];

    public function __construct(string $file)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($file) !== true) {
            throw new RuntimeException("Can't open {$file} as an Excel file.");
        }
        if (($xml = $this->xml('xl/sharedStrings.xml')) !== null) {
            foreach ($xml->si as $si) {
                $this->strings[] = $this->text($si);
            }
        }
        $rels = [];
        foreach ($this->xml('xl/_rels/workbook.xml.rels')?->Relationship ?? [] as $rel) {
            $target = ltrim((string) $rel['Target'], '/');
            $rels[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }
        $wb = $this->xml('xl/workbook.xml') ?? throw new RuntimeException('Not an Excel workbook.');
        foreach ($wb->sheets->sheet as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($rels[$rid])) {
                $this->sheets[(string) $sheet['name']] = ['path' => $rels[$rid], 'state' => (string) ($sheet['state'] ?? 'visible') ?: 'visible'];
            }
        }
    }

    /** @return array<string, string> sheet name => visible | hidden | veryHidden, in workbook order */
    public function sheets(): array
    {
        return array_map(fn ($s) => $s['state'], $this->sheets);
    }

    /**
     * Cell values of one sheet: [row number][column number] => string|float. Empty cells are left out.
     * Rows/columns beyond the limits are skipped (these sheets claim a million rows).
     */
    public function rows(string $sheet, int $maxRow = 2000, int $maxCol = 200): array
    {
        $path = $this->sheets[$sheet]['path'] ?? throw new RuntimeException("No sheet called {$sheet}.");
        $xml = $this->xml($path) ?? throw new RuntimeException("Sheet {$sheet} is missing.");
        $out = [];
        foreach ($xml->sheetData->row as $row) {
            $r = (int) $row['r'];
            if ($r > $maxRow) {
                break;
            }
            foreach ($row->c as $c) {
                [$col, $rr] = self::ref((string) $c['r']);
                if ($col > $maxCol) {
                    continue;
                }
                $type = (string) $c['t'];
                $v = $type === 'inlineStr' ? $this->text($c->is) : (isset($c->v) ? (string) $c->v : null);
                if ($v === null || $v === '') {
                    continue;
                }
                $out[$rr][$col] = match ($type) {
                    's' => $this->strings[(int) $v] ?? '',
                    'str', 'inlineStr', 'e' => $v,
                    'b' => $v === '1' ? 'TRUE' : 'FALSE',
                    default => is_numeric($v) ? (float) $v : $v,
                };
            }
        }

        return $out;
    }

    /** "AB12" => [28, 12] */
    public static function ref(string $ref): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
        $col = 0;
        foreach (str_split($m[1]) as $ch) {
            $col = $col * 26 + (ord($ch) - 64);
        }

        return [$col, (int) $m[2]];
    }

    /** Excel date serial => Y-m-d */
    public static function date(float $serial): string
    {
        return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
    }

    private function xml(string $path): ?SimpleXMLElement
    {
        $data = $this->zip->getFromName($path);
        if ($data === false) {
            return null;
        }

        return simplexml_load_string($data, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT) ?: null;
    }

    private function text(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $s = '';
        foreach ($node->r as $run) {
            $s .= (string) $run->t;
        }

        return $s;
    }
}
