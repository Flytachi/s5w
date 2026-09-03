<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Entity\Bucket;
use Main\Entity\FileEntry;
use Main\Entity\Folder;
use Main\Enum\CacheVisibility;
use Main\Enum\DeliveryChannel;

#[Singleton]
final class CachePolicy
{
    public function resolve(
        FileEntry $file,
        ?Folder $folder,
        Bucket $bucket,
        DeliveryChannel $channel,
        ?int $linkTtl = null,
    ): string {
        $visibility = $this->visibility($file, $folder, $bucket, $channel);
        $maxAge = $this->maxAge($folder, $bucket, $visibility);

        if ($visibility === CacheVisibility::NO_STORE) {
            return $visibility->directive();
        }

        foreach ([$linkTtl, $this->secondsUntilExpiry($file)] as $limit) {
            if ($limit !== null) {
                $maxAge = min($maxAge, max(0, $limit));
            }
        }

        $scope = $visibility->directive();
        if ($maxAge <= 0) {
            return $scope . ', max-age=0, must-revalidate';
        }

        $immutable = $visibility === CacheVisibility::SHARED ? ', immutable' : '';

        return "{$scope}, max-age={$maxAge}{$immutable}";
    }

    private function visibility(
        FileEntry $file,
        ?Folder $folder,
        Bucket $bucket,
        DeliveryChannel $channel,
    ): CacheVisibility {
        $visibility = CacheVisibility::from($folder?->cache_visibility ?? $bucket->cache_visibility);

        if ($visibility === CacheVisibility::NO_STORE) {
            return $visibility;
        }

        if (!$file->public || $channel !== DeliveryChannel::PUBLIC) {
            return CacheVisibility::PRIVATE;
        }

        return $visibility;
    }

    private function maxAge(?Folder $folder, Bucket $bucket, CacheVisibility $visibility): int
    {
        return $folder?->cache_max_age
            ?? $bucket->cache_max_age
            ?? $visibility->defaultMaxAge();
    }

    private function secondsUntilExpiry(FileEntry $file): ?int
    {
        if ($file->expires_at === null) {
            return null;
        }
        $at = strtotime($file->expires_at);

        return $at === false ? null : $at - time();
    }
}
