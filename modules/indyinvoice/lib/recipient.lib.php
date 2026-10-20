<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
/** Provision a native optional third-party field without altering existing values. */
function indyEnsureBillingRecipientField($db)
{
    global $conf;
    require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
    $fields = new ExtraFields($db);
    if ($fields->fetch_name_optionals_label('societe', true) < 0) return -1;
    if (isset($fields->attributes['societe']['type']['destinataire_facturation'])) return 1;
    return $fields->addExtraField('destinataire_facturation', 'IndyBillingRecipient', 'varchar', 100,
        '255', 'societe', 0, 0, '', '', 0, '', '1', 'IndyBillingRecipientHelp', '',
        (string) $conf->entity, 'indyinvoice@indyinvoice', '1', 0, 0);
}

/** Shared recipient wording for PDFs and envelope labels, without modifying the third party. */
function indyBillingRecipient($party)
{
    if (!array_key_exists('options_destinataire_facturation', $party->array_options ?? array())) {
        $party = clone $party;
        $party->fetch_optionals();
    }
    return trim((string) ($party->array_options['options_destinataire_facturation'] ?? ''));
}

/** Native billing and shipping name cells; contact names and addresses remain native. */
class IndyRecipientCanvas
{
    private $pdf;
    private $name;
    private $x;
    private $width;
    private $used = false;
    private $shipping;
    private $shippingWidth;
    private $shippingUsed = false;

    public function __construct($pdf, $name, $x, $width, $shipping = null, $shippingWidth = null)
    { $this->pdf = $pdf; $this->name = $name; $this->x = $x; $this->width = $width; $this->shipping = $shipping; $this->shippingWidth = $shippingWidth; }
    public function __get($name) { return $this->pdf->$name; }
    public function __set($name, $value) { $this->pdf->$name = $value; }
    public function nativePdf() { return method_exists($this->pdf, 'nativePdf') ? $this->pdf->nativePdf() : $this->pdf; }
    public function __call($method, $args)
    {
        // Sponge and Eratosthene both draw this 2mm name cell before the address.
        // Limit the replacement to one cell per header, retaining native wrapping.
        if (!$this->used && strtolower($method) === 'multicell'
            && abs((float) $args[0] - $this->width) < 0.01 && (float) $args[1] === 2.0
            && abs($this->pdf->GetX() - $this->x) < 0.01) {
            if ($this->name !== null) $args[2] = $this->name;
            $this->used = true;
        } elseif ($this->used && !$this->shippingUsed && strtolower($method) === 'multicell'
            && abs((float) $args[0] - $this->shippingWidth) < 0.01 && (float) $args[1] === 2.0
            && abs($this->pdf->GetX() - $this->x) < 0.01) {
            if ($this->shipping !== null) $args[2] = $this->shipping;
            $this->shippingUsed = true;
        }
        return $this->pdf->$method(...$args);
    }
}
