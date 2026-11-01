<?php

declare(strict_types=1);

namespace Main\Threads\Schedule;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Services\TrafficMeter;
use Main\Sweepers\Expired\ExpiredFilesSweeper;
use Main\Sweepers\Expired\ExpiredLinksSweeper;
use Main\Sweepers\Expired\ExpiredTrafficSweeper;
use Main\Sweepers\Expired\ExpiredUploadsSweeper;
use Main\Sweepers\Orphans\OrphanBlobFilesSweeper;
use Main\Sweepers\Orphans\OrphanBlobsSweeper;
use Main\Sweepers\Orphans\OrphanBucketDirsSweeper;
use Main\Sweepers\Repair\QuotaReconciler;
use Main\Sweepers\Sweeper;
use Main\Sweepers\SweepLock;
use Psr\Log\LoggerInterface;

/**
 * Всё расписание сервиса в одном месте. Что делает каждая уборка — в её классе;
 * здесь только когда, под какой блокировкой и что сказать в лог.
 *
 * Имена — те же, что принимает `SweepCmd`, и те же, по которым берётся блокировка.
 */
#[Singleton]
final class Maintenance
{
    /** @var array<string, string> */
    private const array REPORT = [
        'retention' => 'removed %d expired file(s)',
        'uploads' => 'removed %d abandoned upload(s) and staging file(s)',
        'links' => 'removed %d dead share link(s)',
        'blobs' => 'removed %d unreferenced blob(s)',
        'blobs-disk' => 'removed %d stray file(s) from disk',
        'dirs' => 'removed %d orphaned bucket dir(s)',
        'traffic' => 'removed %d traffic row(s) older than a year',
        'quota' => 'corrected %d counter(s)',
    ];

    #[Autowired]
    private TrafficMeter $meter;

    #[Autowired]
    private ExpiredFilesSweeper $expiredFiles;

    #[Autowired]
    private ExpiredUploadsSweeper $expiredUploads;

    #[Autowired]
    private ExpiredLinksSweeper $expiredLinks;

    #[Autowired]
    private ExpiredTrafficSweeper $expiredTraffic;

    #[Autowired]
    private OrphanBlobsSweeper $orphanBlobs;

    #[Autowired]
    private OrphanBlobFilesSweeper $orphanBlobFiles;

    #[Autowired]
    private OrphanBucketDirsSweeper $orphanDirs;

    #[Autowired]
    private QuotaReconciler $quota;

    #[Autowired]
    private SweepLock $lock;

    #[Autowired]
    private LoggerInterface $log;

    /**
     * Двадцать секунд это компромисс между тем, что теряется при аварийном падении
     * контейнера, и числом записей: 180 обновлений строки в час на бакет, при том что
     * обращений за тот же час могут быть миллионы. Ни одна из сторон ни во что не
     * упирается, поэтому число выбрано с запасом в обе стороны.
     */
    #[Scheduled(fixedDelay: 20.0, initialDelay: 20.0)]
    public function flushTraffic(): void
    {
        $this->meter->flush();
    }

    #[Scheduled(fixedDelay: 300.0, initialDelay: 60.0)]
    public function retention(): void
    {
        $this->run('retention');
    }

    #[Scheduled(cron: '0 * * * *')]
    public function uploads(): void
    {
        $this->run('uploads');
    }

    #[Scheduled(cron: '15 3 * * *')]
    public function links(): void
    {
        $this->run('links');
    }

    #[Scheduled(cron: '30 3 * * *')]
    public function blobs(): void
    {
        $this->run('blobs');
    }

    #[Scheduled(cron: '45 3 * * 0')]
    public function blobFiles(): void
    {
        $this->run('blobs-disk');
    }

    #[Scheduled(cron: '0 4 * * *')]
    public function dirs(): void
    {
        $this->run('dirs');
    }

    #[Scheduled(cron: '10 4 * * *')]
    public function traffic(): void
    {
        $this->run('traffic');
    }

    #[Scheduled(cron: '20 4 * * *')]
    public function quota(): void
    {
        $this->run('quota');
    }

    /**
     * @return array<string, Sweeper>
     */
    public function sweepers(): array
    {
        return [
            'retention' => $this->expiredFiles,
            'uploads' => $this->expiredUploads,
            'links' => $this->expiredLinks,
            'blobs' => $this->orphanBlobs,
            'blobs-disk' => $this->orphanBlobFiles,
            'dirs' => $this->orphanDirs,
            'traffic' => $this->expiredTraffic,
            'quota' => $this->quota,
        ];
    }

    private function run(string $name): void
    {
        $this->lock->guard($name, function () use ($name): void {
            $count = $this->sweepers()[$name]->sweep();
            if ($count > 0) {
                $this->log->info(sprintf(self::REPORT[$name], $count));
            }
        });
    }
}
