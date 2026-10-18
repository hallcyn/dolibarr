<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
require_once __DIR__.'/totalscanvas.lib.php';
require_once __DIR__.'/textcanvas.lib.php';
trait IndyDocumentStyle
{
    private function textColor($zone, $background)
    {
        return getDolGlobalInt('INDYINVOICE_'.$zone.'_TEXT_AUTO', 1)
            ? $this->contrast($this->rgb($background, 'FCEBD8'))
            : $this->rgb('INDYINVOICE_'.$zone.'_TEXT_COLOR', '27333E');
    }
    private function contrast($rgb)
    {
        return IndyTotalsCanvas::contrast($rgb);
    }

    private function rgb($name, $fallback)
    {
        $hex = ltrim(getDolGlobalString($name, $fallback), '#');
        if (!preg_match('/^[a-f0-9]{6}$/i', $hex)) {
            $hex = $fallback;
        }
        return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $langs;
        if (!is_object($outputlangs)) $outputlangs = $langs;
        $outputlangs->loadLangs(array('main', 'bills', 'products', 'dict', 'companies', 'compta', 'orders', 'banks'));
        $font = getDolGlobalString('INDYINVOICE_FONT', 'indyinter');
        if (!in_array($font, array('indyinter', 'helvetica', 'dejavusans', 'times', 'dejavuserif'), true)) {
            $font = 'dejavusans';
        }
        if ($font === 'indyinter' && !is_readable(DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/fonts/indyinter.php')) {
            $font = 'dejavusans';
        }
        // Scope appearance overrides to this generation. Never write global
        // PDF settings or recalculate the invoice to get a particular design.
        $settings = array(
            'MAIN_PDF_FORCE_FONT' => $font,
            'MAIN_PDF_FORCE_FONT_SIZE' => max(9, min(12, getDolGlobalInt('INDYINVOICE_FONT_SIZE', 11))),
            'MAIN_PDF_NO_SENDER_FRAME' => 1,
            'MAIN_DOCUMENTS_LOGO_HEIGHT' => max(8, min(24, getDolGlobalInt('INDYINVOICE_LOGO_HEIGHT', 18))),
            // Native placement 2 displays this mention below payment terms,
            // leaving the public note above the table unobstructed.
            'INVOICE_CATEGORY_OF_OPERATION' => getDolGlobalInt('INVOICE_CATEGORY_OF_OPERATION') === 1 ? 2 : getDolGlobalInt('INVOICE_CATEGORY_OF_OPERATION'),
            'MAIN_PDF_NO_RECIPENT_FRAME' => 1,
            'MAIN_PDF_TITLE_BACKGROUND_COLOR' => implode(',', $this->rgb('INDYINVOICE_TABLE_COLOR', 'FCEBD8')),
            'PDF_FOOTER_TEXT_COLOR' => implode(',', $this->rgb('INDYINVOICE_FOOTER_TEXT_COLOR', '5B6770')),
            'MAIN_USE_AUTOWRAP_ON_FREETEXT' => 1,
            'MAIN_PDF_FREETEXT_HEIGHT' => getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5),
        );
        $freeTextKey = $this->name === 'indyorder' ? 'ORDER_FREE_TEXT' : 'INVOICE_FREE_TEXT';
        if ($freeTextKey === 'ORDER_FREE_TEXT' && !getDolGlobalString($freeTextKey)
            && getDolGlobalInt('INDYINVOICE_USE_INVOICE_TEXT_FOR_ORDERS')) {
            $settings[$freeTextKey] = getDolGlobalString('INVOICE_FREE_TEXT');
        }
        $saved = array();
        foreach ($settings as $key => $value) {
            $saved[$key] = array(property_exists($conf->global, $key), $conf->global->$key ?? null);
            $conf->global->$key = $value;
        }
        $note = $object->note_public;
        try {
            // Measure the native footer at the real page width before the
            // native paginator decides where to place lines and totals.
            $probe = pdf_getInstance($this->format);
            $probe->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
            $probe->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs));
            $probe->AddPage();
            $footer = $this->_pagefoot($probe, $object, $outputlangs);
            $base = $this->marge_basse + (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 22 : 12);
            $extra = 0;
            $account = $this->indyBankAccount($object);
            if ($account && ($this->indyExtraBank($object) || empty($object->mode_reglement_code) || $object->mode_reglement_code === 'VIR')) {
                $canvas = new IndyTextCanvas($probe, $this->rgb('INDYINVOICE_TEXT_COLOR', '27333E'));
                $bankHeight = pdf_bank($canvas, $outputlangs, $this->marge_gauche, 0, $account, 0, pdf_getPDFFontSize($outputlangs));
                $extra = max(0, $bankHeight + 30 - 50);
            }
            $conf->global->MAIN_PDF_FREETEXT_HEIGHT = max($settings['MAIN_PDF_FREETEXT_HEIGHT'], ceil($footer - $base + 5)) + ceil($extra);
            if ($note !== '') {
                $object->note_public = '<span style="color:#'.implode('', array_map(function ($v) { return sprintf('%02X', $v); }, $this->rgb('INDYINVOICE_TEXT_COLOR', '27333E'))).'">'.$note.'</span>';
            }
            return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
        } finally {
            $object->note_public = $note;
            foreach ($saved as $key => $state) {
                if ($state[0]) {
                    $conf->global->$key = $state[1];
                } else {
                    unset($conf->global->$key);
                }
            }
        }
    }

    protected function indyPageHead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis, $native)
    {
        global $conf;
        $photo = DOL_DATA_ROOT.'/indyinvoice/banner-'.((int) $conf->entity).'.png';
        $strip = getDolGlobalInt('INDYINVOICE_USE_BANNER_IMAGE') && is_readable($photo) ? 20 : 0;
        $height = max(0, min(35, getDolGlobalInt('INDYINVOICE_BANNER_HEIGHT', 32)));
        // Use the same full-page paper colour as the live preview.
        $paper = $this->rgb('INDYINVOICE_PAPER_COLOR', 'FFFFFF');
        require_once __DIR__.'/pagecanvas.lib.php';
        IndyPageCanvas::paint($pdf, $paper);
        if ($strip) {
            $pdf->Image($photo, 0, 0, $this->page_largeur, 20, 'PNG');
        }
        if ($height > 0) {
            $pdf->SetFillColor(...$this->rgb('INDYINVOICE_BANNER_COLOR', 'FCEBD8'));
            $pdf->Rect(0, $strip, $this->page_largeur, $height, 'F');
        }
        $margin = $this->marge_haute;
        $this->marge_haute += $strip;
        try {
            $canvas = new IndyTextCanvas($pdf, $this->rgb('INDYINVOICE_TEXT_COLOR', '27333E'), array(
                'top' => $strip, 'bottom' => $strip + $height,
                'color' => $this->textColor('HEADER', 'INDYINVOICE_BANNER_COLOR'),
                'background' => $height > 0 ? $this->rgb('INDYINVOICE_BANNER_COLOR', 'FCEBD8') : null,
                'width' => $this->page_largeur,
            ));
            $result = $native($canvas, $object, $showaddress, $outputlangs, $outputlangsbis);
        } finally {
            $this->marge_haute = $margin;
        }
        $result['top_shift'] += $strip;
        return $result;
    }

    public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        parent::defineColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);
        if (getDolGlobalString('INDYINVOICE_TABLE_STYLE', 'light') === 'light') {
            foreach ($this->cols as &$column) {
                $column['border-left'] = false;
            }
            unset($column);
        }
        foreach ($this->cols as &$column) {
            $column['title']['padding'] = array(2, 1, 2, 1);
        }
        unset($column);
        if (isset($this->cols['discount'])) $this->cols['discount']['width'] = max(16, $this->cols['discount']['width']);
        foreach (array('unit' => 42, 'qty' => 44, 'subprice' => 46, 'vat' => 48, 'discount' => 60) as $key => $rank) {
            if (isset($this->cols[$key])) $this->cols[$key]['rank'] = $rank;
        }
    }

    public function pdfTabTitles(&$pdf, $tab_top, $tab_height, $outputlangs, $hidetop = 0)
    {
        if (!$hidetop) $pdf->SetTextColor(...$this->textColor('TABLE', 'INDYINVOICE_TABLE_COLOR'));
        $result = parent::pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);
        $pdf->SetTextColor(0, 0, 0);
        return $result;
    }

    protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $object = '', $outputlangsbis = null)
    {
        if (getDolGlobalString('INDYINVOICE_TABLE_STYLE', 'light') === 'grid'
            || is_object($outputlangsbis)
            || (is_object($object) && !empty($object->multicurrency_code) && $object->multicurrency_code !== getDolCurrency())) {
            parent::_tableau($pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop, $hidebottom, $object, $outputlangsbis);
            return;
        }
        $size = pdf_getPDFFontSize($outputlangs);
        $width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
        $pdf->SetDrawColor(215, 225, 233);
        $pdf->SetTextColor(39, 51, 62);
        $pdf->SetFont('', 'B', $size - 1);
        if (!$hidetop) {
            $this->tabTitleHeight = max(8, $this->tabTitleHeight);
            $pdf->RoundedRect($this->marge_gauche, $tab_top, $width, $this->tabTitleHeight, $this->corner_radius, '1234', 'F', array(), $this->rgb('INDYINVOICE_TABLE_COLOR', 'FCEBD8'));
            // Keep the native currency/category labels and plugin title hooks.
            $currency = is_object($object) ? $object->multicurrency_code : $object;
            $currency = $currency ?: getDolCurrency();
            $pdf->SetFont('', '', $size - 3);
            $pdf->SetXY($this->marge_gauche, $tab_top - 5);
            $pdf->Cell($width, 3, $outputlangs->transnoentities('AmountInCurrency', $outputlangs->transnoentities('Currency'.$currency)), 0, 0, 'R');
            $pdf->SetFont('', 'B', $size - 1);
        }
        $pdf->SetTextColor(...$this->textColor('TABLE', 'INDYINVOICE_TABLE_COLOR'));
        $this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);
        if (!$hidetop) {
            $pdf->Line($this->marge_gauche, $tab_top + $this->tabTitleHeight, $this->page_largeur - $this->marge_droite, $tab_top + $this->tabTitleHeight);
        }
        $pdf->SetFont('', '', $size - 1);
        $pdf->SetTextColor(0, 0, 0);
    }

    private function totalsPosition($pdf, $nativePosition)
    {
        $last = $this->getMaxAfterColsLinePositionsData();
        if (getDolGlobalString('INDYINVOICE_TABLE_STYLE', 'light') === 'light'
            && $last && $last['page'] === $pdf->getPage()) {
            // Move up only, into space that the native paginator has already
            // reserved. Long invoices retain its original page-break decisions.
            return min($nativePosition, max(($this->tab_top_newpage ?? ($this->marge_haute + 32)) + $this->tabTitleHeight + 10, $last['y'] + 12));
        }
        return $nativePosition;
    }

    protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis = null)
    {
        $posy = $this->totalsPosition($pdf, $posy);
        $pdf->SetDrawColor(...$this->rgb('INDYINVOICE_ACCENT_COLOR', 'D82B68'));
        $pdf->SetLineWidth(0.6);
        $left = $this->page_largeur < 210 ? 105 : 120;
        $pdf->Line($left, $posy - 2, $this->page_largeur - $this->marge_droite, $posy - 2);
        $pdf->SetLineWidth(0.2);
        if (getDolGlobalString('INDYINVOICE_TABLE_STYLE', 'light') === 'light') {
            $canvas = new IndyTotalsCanvas($pdf, $this->rgb('INDYINVOICE_ACCENT_COLOR', 'D82B68'), $this->rgb('INDYINVOICE_TEXT_COLOR', '27333E'),
                getDolGlobalInt('INDYINVOICE_TOTAL_TEXT_AUTO', 1) ? null : $this->rgb('INDYINVOICE_TOTAL_TEXT_COLOR', 'FFFFFF'), $this->rgb('INDYINVOICE_PAPER_COLOR', 'FFFFFF'));
            return parent::drawTotalTable($canvas, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);
        }
        $canvas = new IndyTotalsCanvas($pdf, $this->rgb('INDYINVOICE_ACCENT_COLOR', 'D82B68'), $this->rgb('INDYINVOICE_TEXT_COLOR', '27333E'), null, $this->rgb('INDYINVOICE_PAPER_COLOR', 'FFFFFF'), false);
        return parent::drawTotalTable($canvas, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);
    }

    public function printStdColumnContent($pdf, &$curY, $colKey, $columnText = '')
    {
        $canvas = new IndyTextCanvas($pdf, $this->rgb('INDYINVOICE_TEXT_COLOR', '27333E'));
        return parent::printStdColumnContent($canvas, $curY, $colKey, $columnText);
    }

    public function printColDescContent($pdf, &$curY, $colKey, $object, $i, $outputlangs, $hideref = 0, $hidedesc = 0, $issupplierline = 0)
    {
        $canvas = new IndyTextCanvas($pdf, $this->rgb('INDYINVOICE_TEXT_COLOR', '27333E'));
        return parent::printColDescContent($canvas, $curY, $colKey, $object, $i, $outputlangs, $hideref, $hidedesc, $issupplierline);
    }

    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0, $heightforqrinvoice = 0)
    {
        require_once __DIR__.'/pagecanvas.lib.php';
        IndyPageCanvas::paint($pdf, $this->rgb('INDYINVOICE_PAPER_COLOR', 'FFFFFF'));
        $canvas = new IndyTextCanvas($pdf, null, null, true);
        if ($this->name === 'indyorder') return parent::_pagefoot($canvas, $object, $outputlangs, $hidefreetext);
        return parent::_pagefoot($canvas, $object, $outputlangs, $hidefreetext, $heightforqrinvoice);
    }

    private function indyBankAccount($object)
    {
        if ($this->name === 'indy' && (int) $object->type === 2) return null;
        $id = ($object->fk_bank ?? 0) > 0 ? $object->fk_bank : (($object->fk_account ?? 0) > 0 ? $object->fk_account : getDolGlobalInt('FACTURE_RIB_NUMBER'));
        if ($id <= 0) return null;
        require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
        $account = new Account($this->db);
        return $account->fetch((int) $id) > 0 ? $account : null;
    }

    private function indyExtraBank($object)
    {
        return getDolGlobalInt('INDYINVOICE_SHOW_BANK_DETAILS') && !empty($object->mode_reglement_code) && $object->mode_reglement_code !== 'VIR';
    }

    protected function indyInfoTable(&$pdf, $object, $posy, $outputlangs, $native)
    {
        $canvas = new IndyTextCanvas($pdf, $this->rgb('INDYINVOICE_TEXT_COLOR', '27333E'));
        $end = $native($canvas, $object, $this->totalsPosition($pdf, $posy), $outputlangs);
        if ($this->indyExtraBank($object) && ($account = $this->indyBankAccount($object))) {
            $end = pdf_bank($canvas, $outputlangs, $this->marge_gauche, $end + 3, $account, 0, pdf_getPDFFontSize($outputlangs)) + 2;
        }
        return $end;
    }
}
