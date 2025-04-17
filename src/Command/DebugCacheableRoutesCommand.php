<?php declare(strict_types=1);

namespace SwagStoreAPICache\Command;

use SwagStoreAPICache\Listener\StoreAPIResponseListener;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugCacheableRoutesCommand extends Command
{
    protected static $defaultName = 'swag:store-api-cache:debug:routes';

    public function __construct(
        private readonly StoreAPIResponseListener $storeAPIResponseListener
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Lists all cacheable Store API routes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routes = $this->storeAPIResponseListener->getCacheableStoreApiRoutes();

        $table = new Table($output);
        $table->setHeaders(['Route Name']);
        
        foreach ($routes as $route) {
            $table->addRow([$route]);
        }

        $table->render();

        return Command::SUCCESS;
    }
} 