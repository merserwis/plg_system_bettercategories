<?php

/**
 * @package     Merserwis.Module
 * @subpackage  mod_bettercategories
 */

\defined('_JEXEC') or die;

/** @var string $listHtml the list rendered by the Better Categories plugin (styles included) */
if ($listHtml === '') {
    return;
}
?>
<div class="mod-bettercategories"><?php echo $listHtml; ?></div>
