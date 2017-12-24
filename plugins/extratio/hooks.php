<?php

require_once('rules.php');

class extratioHooks
{
    public static function OnLabelChanged($prm)
    {
        $mngr = rRatioRulesList::load();
        $mngr->checkLabels(array($prm["hash"]));
    }
}
