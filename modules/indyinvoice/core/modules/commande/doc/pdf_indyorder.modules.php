<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/doc/pdf_eratosthene.modules.php';
require_once __DIR__.'/../../../../lib/pdfstyle.lib.php';
class pdf_indyorder extends pdf_eratosthene
{
    use IndyDocumentStyle;
    public function __construct($db)
    {
        parent::__construct($db);
        $this->name = 'indyorder';
        $this->description = 'Indy — commande personnalisable';
        $this->version = '1.1.0';
        $this->corner_radius = 1.6;
    }
    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null, $titlekey = 'PdfOrderTitle')
    {
        return $this->indyPageHead($pdf, $object, $showaddress, $outputlangs, $outputlangsbis,
            function (&$pdf, $object, $showaddress, $langs, $bis) use ($titlekey) { return parent::_pagehead($pdf, $object, $showaddress, $langs, $bis, $titlekey); });
    }
    protected function drawInfoTable(&$pdf, $object, $posy, $outputlangs)
    {
        return parent::drawInfoTable($pdf, $object, $this->totalsPosition($pdf, $posy), $outputlangs);
    }
}
