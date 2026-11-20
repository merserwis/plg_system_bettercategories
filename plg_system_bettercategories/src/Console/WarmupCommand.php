<?php

/**
 * @package     Merserwis.Plugin
 * @subpackage  System.bettercategories
 */

namespace Merserwis\Plugin\System\BetterCategories\Console;

\defined('_JEXEC') or die;

use Joomla\Console\Command\AbstractCommand;
use Merserwis\Plugin\System\BetterCategories\Extension\BetterCategories;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * php cli/joomla.php bettercategories:warmup [--time=300] (1.10.0): the lighter copies of the tile images and the
 * index the filters read from the product descriptions, made ahead (see BetterCategories::warmUp()). Run it as
 * the user of the web server (the files it writes are the site's); from cron, e.g. after a large import.
 */
class WarmupCommand extends AbstractCommand
{
    protected static $defaultName = 'bettercategories:warmup';

    public function __construct(private BetterCategories $plugin)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Better Categories for Gridbox: make the lighter copies of the tile images and the index of the filters ahead');
        $this->addOption('time', null, InputOption::VALUE_REQUIRED, 'Seconds at most (what is left is done by the next run or by the visits)', '300');
        $this->setHelp("<info>%command.name%</info> makes the lighter copies (WebP) of the tile images of every store\n"
            . "and the index of the technical parameters and features the filters read from the product descriptions,\n"
            . "so the first visitors do not wait for them. Run it as the user of the web server, e.g.:\n"
            . "  <info>sudo -u www-data php cli/joomla.php %command.name% --time=600</info>");
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $seconds = max(10, min(3600, (int) $input->getOption('time')));
        $io->title('Better Categories for Gridbox: warm-up');
        $result = $this->plugin->warmUp((float) $seconds, static function (string $line) use ($io): void {
            $io->writeln($line);
        });

        $thumbs = $result['thumbs'];
        if (!empty($thumbs['off'])) {
            $io->writeln('thumbnails: off in the settings (or no WebP in PHP)');
        } elseif (isset($thumbs['error'])) {
            $io->warning('thumbnails: ' . $thumbs['error']);
        } else {
            $io->writeln(sprintf('thumbnails: %d of %d ready, %d made now, %d left as they are', $thumbs['ready'], $thumbs['total'], $thumbs['made'] ?? 0, $thumbs['skipped']));
        }
        if (empty($result['filters'])) {
            $io->writeln('filters: off in the settings');
        } elseif (!$result['index']) {
            $io->writeln('filters: no time left for the index');
        }
        if ($result['done']) {
            $io->success('Done.');
        } else {
            $io->note('Not all done within ' . $seconds . ' s: run it again (or with a longer --time).');
        }

        return 0;
    }
}
