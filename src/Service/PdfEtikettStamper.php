<?php

namespace App\Service;

use Symfony\Component\Process\Process;

/**
 * Stamps a song's "Etikett" (filing label, e.g. "Rosa 155") onto the top-right of
 * the first page of a PDF — used to overlay the label when serving Noten PDFs,
 * without touching the originals in Dropbox.
 *
 * Pipeline (vector-preserving, handles any PDF version):
 *   1. read the first page's MediaBox via Ghostscript,
 *   2. build a one-page overlay (same size) carrying the label,
 *   3. composite it onto page 1 only with qpdf --overlay.
 *
 * Any failure (missing tools, odd PDF, …) returns the original bytes unchanged.
 */
class PdfEtikettStamper
{
    /** Background colour per Etikett colour name; anything else falls back to light grey. */
    private const COLOURS = [
        'rosa'   => '1 0.70 0.80',
        'blau'   => '0.70 0.85 1',
        'gelb'   => '1 0.92 0.45',
        'grün'   => '0.70 0.90 0.70',
        'gruen'  => '0.70 0.90 0.70',
        'grun'   => '0.70 0.90 0.70',
        'orange' => '1 0.80 0.45',
        'rot'    => '1 0.65 0.65',
        'weiß'   => '1 1 1',
        'weiss'  => '1 1 1',
    ];

    public function stamp(string $pdfBytes, string $label, ?string $colour): string
    {
        $label = trim($label);
        if ($label === '' || $pdfBytes === '') {
            return $pdfBytes;
        }

        $dir  = sys_get_temp_dir();
        $base = $dir . '/etikett_' . bin2hex(random_bytes(8));
        $src  = $base . '_src.pdf';
        $ps   = $base . '_ov.ps';
        $ovl  = $base . '_ov.pdf';
        $out  = $base . '_out.pdf';
        $cleanup = fn() => array_map(fn($f) => @unlink($f), [$src, $ps, $ovl, $out]);

        try {
            file_put_contents($src, $pdfBytes);

            [$w, $h] = $this->firstPageSize($src);
            if ($w === null || $h === null) {
                return $pdfBytes;
            }

            file_put_contents($ps, $this->overlayPostScript($w, $h, $label, $colour));

            // PostScript overlay -> one-page PDF
            $gs = new Process(['gs', '-q', '-dBATCH', '-dNOPAUSE', '-dSAFER', '-sDEVICE=pdfwrite', '-o', $ovl, $ps]);
            $gs->setTimeout(20)->run();
            if (!is_file($ovl) || filesize($ovl) === 0) {
                return $pdfBytes;
            }

            // Composite the overlay onto page 1 only. qpdf exits 3 on warnings but
            // still writes output, so trust the output file rather than the code.
            $qpdf = new Process(['qpdf', $src, '--overlay', $ovl, '--to=1', '--', $out]);
            $qpdf->setTimeout(20)->run();
            if (!is_file($out) || filesize($out) === 0) {
                return $pdfBytes;
            }

            return file_get_contents($out) ?: $pdfBytes;
        } catch (\Throwable) {
            return $pdfBytes;
        } finally {
            $cleanup();
        }
    }

    /** @return array{0: float|null, 1: float|null} width and height of page 1 in points */
    private function firstPageSize(string $pdfPath): array
    {
        $code = '(' . $pdfPath . ')(r) file runpdfbegin 1 pdfgetpage /MediaBox get { 20 string cvs print ( ) print } forall quit';
        $p = new Process(['gs', '-q', '-dNODISPLAY', '-dNOSAFER', '-c', $code]);
        $p->setTimeout(20)->run();

        $nums = preg_split('/\s+/', trim($p->getOutput()), -1, PREG_SPLIT_NO_EMPTY);
        if (count($nums) < 4 || !is_numeric($nums[0])) {
            return [null, null];
        }
        $w = (float) $nums[2] - (float) $nums[0];
        $h = (float) $nums[3] - (float) $nums[1];

        return ($w > 1 && $h > 1) ? [$w, $h] : [null, null];
    }

    private function overlayPostScript(float $w, float $h, string $label, ?string $colour): string
    {
        $bg  = self::COLOURS[mb_strtolower(trim((string) $colour))] ?? '0.90 0.90 0.90';
        $esc = strtr($label, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);

        return <<<PS
            %!PS
            << /PageSize [{$w} {$h}] >> setpagedevice
            /label ({$esc}) def
            /Helvetica-Bold findfont 16 scalefont setfont
            /pad 12 def
            /boxh 30 def
            /boxw label stringwidth pop pad 2 mul add def
            /margin 14 def
            /bx margin def
            /by {$h} margin sub boxh sub def
            {$bg} setrgbcolor
            bx by boxw boxh rectfill
            0 setgray 1 setlinewidth
            bx by boxw boxh rectstroke
            0 setgray
            bx pad add by 10 add moveto label show
            showpage
            PS;
    }
}
