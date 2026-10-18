<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
/** A page underlay for Dolibarr's bundled TCPDF/TCPDI (24.0.2).
 * Native generators may draw the header after line items on subsequent pages.
 * Prepend the background before all drawing commands, instead of covering that
 * content. This runs before native output/signing; no PDF is reopened or merged.
 */
class IndyPageCanvas extends TCPDF
{
    public static function paint($pdf, $rgb)
    {
        if ($rgb === array(255, 255, 255)) return;
        // Native total/payment tables can create another page while drawing
        // through a presentation canvas. Always paint its original instance.
        if (method_exists($pdf, 'nativePdf')) $pdf = $pdf->nativePdf();
        $page = $pdf->getPage();
        $buffer = $pdf->getPageBuffer($page);
        if (strpos($buffer, '% Indy page background') === 0) return;
        $scale = $pdf->getScaleFactor();
        $underlay = sprintf("%% Indy page background\nq\n%.5F %.5F %.5F rg\n0 0 %.5F %.5F re f\nQ\n",
            $rgb[0]/255, $rgb[1]/255, $rgb[2]/255, $pdf->getPageWidth()*$scale, $pdf->getPageHeight()*$scale);
        $pdf->setPageBuffer($page, $underlay.$buffer);
        // These native offsets must continue pointing at the same content
        // after the insertion, so later table fills and footers stay in order.
        foreach (array('intmrk', 'bordermrk', 'cntmrk', 'footerpos') as $mark) {
            if (isset($pdf->{$mark}[$page])) $pdf->{$mark}[$page] += strlen($underlay);
        }
    }
}
