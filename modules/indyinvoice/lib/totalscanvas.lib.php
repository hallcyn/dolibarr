<?php
/* Hallcyn. SPDX-License-Identifier: GPL-3.0-or-later */
/** Paint the native total cells with the chosen palette. No amounts or labels
 * are calculated here. The original TCPDF instance retains signatures, fonts,
 * pagination and output. Used only while the native total table is drawn. */
class IndyTotalsCanvas
{
    private $pdf;
    private $accent;
    private $fill = array(255,255,255);
    private $text = array(39,51,62);
    private $totalText;

    public function __construct($pdf, $accent, $text = array(39,51,62), $totalText = null) { $this->pdf=$pdf; $this->accent=$accent; $this->text=$text; $this->totalText=$totalText; }
    public static function contrast($rgb)
    {
        $luma=0;
        foreach ($rgb as $i=>$v) {
            $c=$v/255;
            $luma+=($c<=0.04045?$c/12.92:pow(($c+0.055)/1.055,2.4))*array(0.2126,0.7152,0.0722)[$i];
        }
        $white=1.05/($luma+0.05);
        $ink=($luma+0.05)/(0.031+0.05);
        if ($white>=4.5 && $white>=$ink) return array(255,255,255);
        return $ink>=4.5?array(39,51,62):array(0,0,0);
    }
    public static function ratio($a, $b)
    {
        $luminance = function ($rgb) {
            $l = 0;
            foreach ($rgb as $i => $v) { $v /= 255; $l += ($v <= 0.04045 ? $v/12.92 : pow(($v+0.055)/1.055, 2.4))*array(0.2126,0.7152,0.0722)[$i]; }
            return $l;
        };
        $x = $luminance($a); $y = $luminance($b);
        return (max($x, $y)+0.05)/(min($x, $y)+0.05);
    }
    public function __get($name) { return $this->pdf->$name; }
    public function __set($name,$value) { $this->pdf->$name=$value; }
    public function __call($name,$args)
    {
        $method=strtolower($name);
        if ($method==='setfillcolor' && count($args)>=3) {
            $grey=max($args[0],$args[1],$args[2])-min($args[0],$args[1],$args[2])<3;
            if ($grey && $args[0]>=200 && $args[0]<=230) $args=$this->accent;
            elseif ($grey && $args[0]>230 && $args[0]<252) $args=array_map(function($c){return (int)round(255*0.94+$c*0.06);},$this->accent);
            $this->fill=array_slice($args,0,3);
        } elseif ($method==='settextcolor') {
            $args=$this->text;
        }
        if (($method==='multicell' && !empty($args[5])) || ($method==='cell' && !empty($args[6]))) {
            $color = $this->fill === $this->accent
                ? ($this->totalText ?? self::contrast($this->fill))
                : (self::ratio($this->text, $this->fill) >= 4.5 ? $this->text : self::contrast($this->fill));
            $this->pdf->SetTextColor(...$color);
            try { return $this->pdf->$name(...$args); }
            finally { $this->pdf->SetTextColor(...$this->text); }
        }
        if (in_array($method, array('cell', 'multicell', 'writehtmlcell', 'writehtml', 'text'), true)) $this->pdf->SetTextColor(...$this->text);
        return $this->pdf->$name(...$args);
    }
}
