<?php

namespace App\Command;

use App\Queue\EncryptionQueueInterface;
use App\Service\EncryptionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ProcessEncryptionQueueCommand extends Command
{
    protected static $defaultName = 'app:process-encryption-queue';
    protected static $defaultDescription = 'Process pending encryption and decryption jobs from the local queue.';

    public function __construct(
        private EncryptionService $encryptionService,
        private EncryptionQueueInterface $queue
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('once', null, InputOption::VALUE_NONE, 'Process existing queued jobs once and exit.')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of jobs to process per run', 50)
            ->addOption('delay', null, InputOption::VALUE_OPTIONAL, 'Polling delay in seconds between runs', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(0, (int) $input->getOption('limit'));
        $delay = max(0, (int) $input->getOption('delay'));
        $runOnce = (bool) $input->getOption('once');
        $processed = 0;

        do {
            $jobs = $this->queue->fetchPendingJobs();
            if (empty($jobs)) {
                $io->comment('No pending encryption jobs found.');
                if ($runOnce) {
                    break;
                }
                sleep($delay);
                continue;
            }

            foreach ($jobs as $jobEntry) {
                $job = $jobEntry['job'];
                $jobFile = $jobEntry['file'];

                try {
                    $this->encryptionService->processJob($job);
                    $this->queue->acknowledge($jobFile);
                    $io->success(sprintf('Processed %s job: %s -> %s', $job->getAction(), $job->getSourcePath(), $job->getDestinationPath()));
                } catch (\Throwable $exception) {
                    $io->error(sprintf('Failed to process job %s: %s', $jobFile, $exception->getMessage()));
                }

                $processed++;
                if ($limit > 0 && $processed >= $limit) {
                    break 2;
                }
            }

            if ($runOnce) {
                break;
            }

            if ($delay > 0) {
                sleep($delay);
            }
        } while (true);

        $io->success(sprintf('Processed %d job(s).', $processed));

        return Command::SUCCESS;
    }
}
