<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CrmExchangeTokenRepositoryInterface;
use App\Repository\CrmRefreshTokenRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'session:cleanup',
    description: 'Delete refresh sessions older than 90 days, expired CRM exchange tokens, and expired CRM refresh tokens',
)]
final class SessionCleanupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CrmExchangeTokenRepositoryInterface $crmTokenRepository,
        private readonly CrmRefreshTokenRepositoryInterface $crmRefreshTokenRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting session cleanup...</info>');

        // Calculate the cutoff date (90 days ago)
        $cutoffDate = (new DateTimeImmutable())->modify('-90 days');

        $output->writeln(sprintf(
            '<comment>Deleting sessions created before: %s</comment>',
            $cutoffDate->format('Y-m-d H:i:s')
        ));

        // Delete sessions older than 90 days using DQL
        $query = $this->em->createQuery(
            'DELETE FROM App\Entity\RefreshSession r WHERE r.created_at < :cutoffDate'
        );
        $query->setParameter('cutoffDate', $cutoffDate);

        $deletedCount = $query->execute();

        $output->writeln(sprintf(
            '<info>Successfully deleted %d session(s)</info>',
            $deletedCount
        ));

        // Clean up expired CRM exchange tokens
        $output->writeln('<info>Cleaning up expired CRM exchange tokens...</info>');

        $expiredTokensCount = $this->crmTokenRepository->cleanupExpired();

        $output->writeln(sprintf(
            '<info>Successfully deleted %d expired CRM exchange token(s)</info>',
            $expiredTokensCount
        ));

        // Clean up expired CRM refresh tokens
        $output->writeln('<info>Cleaning up expired CRM refresh tokens...</info>');

        $expiredRefreshCount = $this->crmRefreshTokenRepository->cleanupExpired();

        $output->writeln(sprintf(
            '<info>Successfully deleted %d expired CRM refresh token(s)</info>',
            $expiredRefreshCount
        ));

        return Command::SUCCESS;
    }
}
