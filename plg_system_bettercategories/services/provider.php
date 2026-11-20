<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\SubscriberInterface;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;

return new class () implements ServiceProviderInterface {
    /** The errors that end a request with "500 Whoops" (any extension's), kept for the Diagnostics page. */
    public const ERRORS_FILE = 'plg_system_bettercategories_errors.php';

    public function register(Container $container): void
    {
        $this->logSiteErrors();

        // Safe mode (1.9.1): an empty file named "safe-mode" in this plugin's folder (made with the hosting's
        // file manager or FTP, or with the button of the Diagnostics page) stops the plugin on the site and in the
        // administrator without loading its code; its settings stay. Removing the file turns the plugin back on.
        if (is_file(\dirname(__DIR__) . '/safe-mode')) {
            $container->set(
                PluginInterface::class,
                function () {
                    return new class ((array) PluginHelper::getPlugin('system', 'bettercategories')) extends CMSPlugin implements SubscriberInterface {
                        public static function getSubscribedEvents(): array
                        {
                            return [];
                        }
                    };
                }
            );

            return;
        }

        $container->set(
            PluginInterface::class,
            $container->lazy(BetterCategories::class, function (Container $container) {
                $plugin = new BetterCategories((array) PluginHelper::getPlugin('system', 'bettercategories'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }

    /**
     * Joomla shows "500 Whoops" for an uncaught error and, unless "Log Almost Everything" is on, writes it
     * nowhere: on a hosting without access to the server's logs the cause was lost (1.9.1). Joomla's error
     * handler logs every such error in the category "error"; this keeps them in a file of their own in
     * Joomla's log folder — the message, the file and line, and the stack trace without the values of the
     * arguments — for the Diagnostics page of Better Categories. Personal data stays out: values quoted in
     * the messages (a duplicate e-mail, a name) and the query of a database error are masked; only names of
     * code and files are kept. Errors of the visitor's request (4xx: a page not found, no access) are left
     * out. Registered here, so it works in safe mode too; the file is kept under 512 KB (the older half is
     * dropped, under the same lock as the writing, so lines of parallel requests are not lost).
     */
    private function logSiteErrors(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            Log::addLogger(['logger' => 'callback', 'callback' => static function (LogEntry $entry): void {
                try {
                    $app  = Factory::getApplication();
                    $dir  = rtrim((string) $app->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\');
                    $file = $dir . '/' . self::ERRORS_FILE;
                    // the error itself is the argument of Joomla's ExceptionHandler::logException() that is logging it
                    $error = null;
                    foreach (debug_backtrace(0, 12) as $frame) {
                        if (($frame['function'] ?? '') === 'logException' && ($frame['args'][0] ?? null) instanceof \Throwable) {
                            $error = $frame['args'][0];
                            break;
                        }
                    }
                    // a page or an item not found (404), no access (403) and the like are the visitor's, not the site's
                    $code = $error ? (int) $error->getCode() : 0;
                    if ($code >= 400 && $code < 500) {
                        return;
                    }
                    $short = static fn (string $path): string => str_replace([JPATH_ROOT . DIRECTORY_SEPARATOR, JPATH_ROOT], '', $path);
                    // a message may quote the value that failed ("Duplicate entry 'jan@example.com'", a database
                    // error with its query): quoted values are masked unless they name code or a file
                    $mask = static function (string $message): string {
                        $message = (string) preg_replace('/\bSQL\s*=.*$/s', 'SQL=…', $message);
                        $message = (string) preg_replace_callback('/([\'"])(.*?)\1/s', static function (array $m): string {
                            $keep = str_contains($m[2], '\\') || preg_match('#^[\w./-]+\.(php|ini|xml|json|js|css)$#', $m[2])
                                || (mb_strlen($m[2]) <= 2 && !preg_match('/[\p{L}\p{N}]/u', $m[2]));

                            return $keep ? $m[0] : $m[1] . '…' . $m[1];
                        }, $message);

                        return (string) preg_replace('/[^\s@\'"<>()]+@[^\s@\'"<>()]+\.[a-z]{2,}/i', '…@…', $message);
                    };
                    if ($error) {
                        // the trace without the values of the arguments (passwords, personal data)
                        $text = '';
                        for ($e = $error, $depth = 0; $e && $depth < 3; $e = $e->getPrevious(), $depth++) {
                            $text .= ($depth ? ' | Previous: ' : '') . \get_class($e) . ($e->getCode() ? ' (' . $e->getCode() . ')' : '') . ': ' . $mask($e->getMessage())
                                . ' @ ' . $short($e->getFile()) . ':' . $e->getLine();
                        }
                        foreach (\array_slice($error->getTrace(), 0, 20) as $i => $f) {
                            $text .= ' | #' . $i . ' ' . (isset($f['file']) ? $short($f['file']) . '(' . ($f['line'] ?? 0) . '): ' : '')
                                . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
                        }
                    } else {
                        $text = (string) preg_replace('/^(#\d+ [^\n]*?: [^\n(]*)\(.*\)$/m', '$1(…)', str_replace('Stack trace: #', "Stack trace:\n#", $short((string) $entry->message)));
                        $text = $mask($text);
                    }
                    $text  = str_replace(["\r", "\n", "\t"], ['', ' | ', ' '], mb_substr($short($text), 0, 6000));
                    // where: the path and the parts of the address that name the page (no other query values: they may be personal)
                    $input = $app->getInput();
                    $where = ($app->isClient('administrator') ? 'admin ' : 'site ') . strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');
                    foreach (['option', 'view', 'layout', 'task', 'page'] as $key) {
                        $value = $input->getCmd($key, '');
                        $where .= $value !== '' ? ' ' . $key . '=' . $value : '';
                    }
                    $line  = gmdate('Y-m-d\TH:i:s\Z') . "\t" . mb_substr($where, 0, 200) . "\t" . $text . "\n";
                    // one lock for the trimming and the writing: a line of a parallel request is not lost
                    $fh = @fopen($file, 'c+');
                    if (!$fh) {
                        return;
                    }
                    try {
                        if (!flock($fh, LOCK_EX)) {
                            return;
                        }
                        clearstatcache(true, $file);
                        $size = (int) (fstat($fh)['size'] ?? 0);
                        if ($size > 524288) {
                            // the newer half, from the start of a line
                            fseek($fh, (int) ($size / 2));
                            $rest = (string) stream_get_contents($fh);
                            $nl   = strpos($rest, "\n");
                            $rest = $nl === false ? '' : substr($rest, $nl + 1);
                            ftruncate($fh, 0);
                            rewind($fh);
                            fwrite($fh, "<?php die; ?>\n" . $rest);
                        } elseif ($size === 0) {
                            // the file is .php: it starts with die, so its content is never sent to a browser
                            fwrite($fh, "<?php die; ?>\n");
                        }
                        fseek($fh, 0, SEEK_END);
                        fwrite($fh, $line);
                        fflush($fh);
                        flock($fh, LOCK_UN);
                    } finally {
                        fclose($fh);
                    }
                } catch (\Throwable $ignored) {
                }
            }], Log::CRITICAL | Log::ALERT | Log::EMERGENCY, ['error']);
        } catch (\Throwable $ignored) {
        }
    }
};
