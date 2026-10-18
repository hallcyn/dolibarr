<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_sponge.modules.php';
require_once __DIR__.'/../../../../lib/pdfstyle.lib.php';
class pdf_indy extends pdf_sponge
{
    use IndyDocumentStyle;
    public function __construct($db)
    {
        parent::__construct($db);
        $this->name = 'indy';
        $this->description = 'Indy — facture personnalisable';
        $this->version = '1.3.0';
        $this->corner_radius = 1.6;
    }
    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null)
    {
        return $this->indyPageHead($pdf, $object, $showaddress, $outputlangs, $outputlangsbis,
            function (&$pdf, $object, $showaddress, $langs, $bis) { return parent::_pagehead($pdf, $object, $showaddress, $langs, $bis); });
    }
    protected function drawInfoTable(&$pdf, $object, $posy, $outputlangs, $outputlangsbis)
    {
        return $this->indyInfoTable($pdf, $object, $posy, $outputlangs,
            function (&$canvas, $object, $y, $langs) use ($outputlangsbis) { return parent::drawInfoTable($canvas, $object, $y, $langs, $outputlangsbis); });
    }
}
