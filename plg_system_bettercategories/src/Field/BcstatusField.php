<?php

namespace Merserwis\Plugin\System\BetterCategories\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;

/** The latest lines of the plugin's log file, so a problem on the site can be seen without server access. */
class BcstatusField extends FormField
{
    protected $type = 'Bcstatus';

    protected function getInput()
    {
        $file  = rtrim((string) Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/') . '/' . BetterCategories::LOG_FILE;
        $lines = [];
        if (is_file($file) && is_readable($file)) {
            // the end of the file is enough (the log may grow)
            $size = (int) @filesize($file);
            $fh   = @fopen($file, 'rb');
            if ($fh) {
                if ($size > 65536) {
                    fseek($fh, -65536, SEEK_END);
                }
                $text = (string) stream_get_contents($fh);
                fclose($fh);
                foreach (preg_split('/\R/', $text) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '' && $line[0] !== '#' && !str_starts_with($line, '<?php')) {
                        $lines[] = $line;
                    }
                }
            }
        }
        $lines = array_slice($lines, -8);
        $e     = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        if (!$lines) {
            return '<div class="alert alert-success mb-0">' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_STATUS_OK') . '</div>';
        }

        return '<div class="alert alert-warning mb-0"><strong>' . Text::_('PLG_SYSTEM_BETTERCATEGORIES_STATUS_TITLE') . '</strong>'
            . '<p class="small mb-1">' . Text::sprintf('PLG_SYSTEM_BETTERCATEGORIES_STATUS_DESC', $e(str_replace(JPATH_ROOT, '', $file))) . '</p>'
            . '<pre class="small mb-0" style="white-space:pre-wrap;max-height:16em;overflow:auto">' . $e(implode("\n", array_reverse($lines))) . '</pre></div>';
    }

    protected function getLabel()
    {
        return '';
    }
}
