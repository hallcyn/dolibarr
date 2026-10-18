<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
/** A presentation-only view of the original TCPDF instance. */
class IndyTextCanvas
{
    private $pdf;
    private $color;
    private $band;
    private $wrap;
    private $requested = array(0, 0, 0);

    public function __construct($pdf, $color = null, $band = null, $wrap = false)
    { $this->pdf = $pdf; $this->color = $color; $this->band = $band; $this->wrap = $wrap; }
    public function __get($name) { return $this->pdf->$name; }
    public function nativePdf() { return method_exists($this->pdf, 'nativePdf') ? $this->pdf->nativePdf() : $this->pdf; }
    public function __set($name, $value) { $this->pdf->$name = $value; }
    public function __call($name, $args)
    {
        $method = strtolower($name);
        if ($method === 'settextcolor') $this->requested = $args;
        if ($this->wrap && $method === 'getstringheight' && $args[0] >= 200) {
            // Native plain-text footers otherwise measure an unwrapped 20000mm
            // line and then render it inside the much narrower actual margins.
            $m = $this->pdf->getMargins();
            $width = $this->pdf->getPageWidth() - $m['left'] - $m['right'];
            // Match the native footer's 3mm minimum line height as well as
            // its font metrics. getStringHeight alone can underestimate a
            // long paragraph with the Unicode font and no legal footer.
            $this->pdf->startTransaction();
            $this->pdf->SetXY($m['left'], 0);
            $this->pdf->MultiCell($width, 3, $args[1], 0, 'C', false);
            $height = $this->pdf->GetY();
            $this->pdf->rollbackTransaction(true);
            return $height + 2;
        }
        if ($this->wrap && $method === 'multicell' && $args[0] <= 28
            && $args[2] === $this->pdf->PageNo().' / '.$this->pdf->getAliasNbPages()) {
            // TCPDF's unresolved page-count alias is wider than its final text.
            // Give it room before replacement, retaining the same right edge.
            $this->pdf->SetX($this->pdf->GetX() - 10);
            $args[0] += 10;
        }
        if ($this->wrap && $method === 'writehtmlcell' && $args[0] == 0 && $args[2] == 0 && $args[3] == 0) {
            // Native HTML height probes start at x=0, while the actual
            // footer starts at the left margin. Measure the same width.
            $m = $this->pdf->getMargins();
            $args[0] = $this->pdf->getPageWidth() - $m['left'] - $m['right'];
            $args[2] = $m['left'];
            $result = $this->pdf->$name(...$args);
            $this->pdf->SetY($this->pdf->GetY() + 2);
            return $result;
        }
        if ($this->color !== null && in_array($method, array('cell', 'multicell', 'writehtmlcell', 'writehtml', 'text'), true)) {
            $y = $this->pdf->GetY();
            if ($method === 'multicell' && isset($args[8]) && is_numeric($args[8])) $y = $args[8];
            if ($method === 'writehtmlcell' && isset($args[3]) && is_numeric($args[3])) $y = $args[3];
            if ($method === 'text' && isset($args[1])) $y = $args[1];
            $color = $this->color;
            // Keep native warnings red outside the decorative header.
            if (count($this->requested) >= 3 && $this->requested[0] >= 128 && $this->requested[1] == 0 && $this->requested[2] == 0) $color = $this->requested;
            if ($this->band && $y >= $this->band['top'] && $y < $this->band['bottom']) {
                $color = $this->band['color'];
                // A native reference can straddle the configured band edge.
                // Extend its background before drawing, keeping the whole line
                // legible instead of printing light text over white paper.
                if ($method === 'multicell' && !empty($this->band['background'])) {
                    $height = max((float)$args[1], $this->pdf->getStringHeight($args[0], $args[2]));
                    $bottom = $y + $height;
                    if ($bottom > $this->band['bottom']) {
                        $this->pdf->SetFillColor(...$this->band['background']);
                        $this->pdf->Rect(0, $this->band['bottom'], $this->band['width'], $bottom - $this->band['bottom'], 'F');
                        $this->band['bottom'] = $bottom;
                    }
                }
            }
            $this->pdf->SetTextColor(...$color);
        }
        return $this->pdf->$name(...$args);
    }
}
