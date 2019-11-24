<?php

/**
 * 
 */
class ProcmailFilterBuilder
{
    private $rule;

    function __construct()
    {
        $this->resetRule();
    }

    public function resetRule()
    {
        $this->rule = ":0:";
        return $this;
    }

    public function build()
    {
        $out = $this->rule;
        return $out;
    }
}
