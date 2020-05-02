<?php

namespace Softwex\Carriers\Model;


class FpdfJs extends \setasign\Fpdi\Fpdi
{

    /**
     * Javascript string
     *
     * @var string
     */
    protected $javascript;
    /**
     * Current javascript object number
     *
     * @var int
     */
    protected $nJs;

    /**
     * Include Javascript to PDF object
     *
     * @param string $script
     * @param boolean $isUTF8
     */
    public function IncludeJS($script, $isUTF8 = false)
    {
        if (!$isUTF8) {
            $script = utf8_encode($script);
        }
        $this->javascript = $script;
    }

    /**
     * @return $this
     */
    protected function _putjavascript() {
        $this->_newobj();
        $this->nJs = $this->n;
        $this->_put('<<');
        $this->_put('/Names [(EmbeddedJS) '.($this->n+1).' 0 R]');
        $this->_put('>>');
        $this->_put('endobj');
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/S /JavaScript');
        $this->_put('/JS '.$this->_textstring($this->javascript));
        $this->_put('>>');
        $this->_put('endobj');
    }

    /**
     * @return $this
     */
    protected function _putresources() {
        parent::_putresources();
        if (!empty($this->javascript)) {
            $this->_putjavascript();
        }
    }

    /**
     * @return $this
     */
    protected function _putcatalog() {
        parent::_putcatalog();
        if (!empty($this->javascript)) {
            $this->_put('/Names <</JavaScript '.($this->nJs).' 0 R>>');
        }
    }

}
