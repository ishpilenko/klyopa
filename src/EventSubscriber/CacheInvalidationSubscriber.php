<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postPersist)]
class CacheInvalidationSubscriber
{
    public function __construct(
        private readonly PageCacheSubscriber $pageCache,
    ) {
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    private function invalidate(object $entity): void
    {
        if ($entity instanceof Article) {
            $siteId = $entity->getSite()->getId();
            $category = $entity->getCategory();

            // Invalidate home (latest articles), category listing, article page
            $this->pageCache->invalidateByPrefix($siteId, '/');
            if ($category !== null) {
                $this->pageCache->invalidateByPrefix($siteId, '/' . $category->getSlug() . '/');
            }
            if ($category !== null) {
                $this->pageCache->invalidate($siteId, '/' . $category->getSlug() . '/' . $entity->getSlug());
            }
        }

        if ($entity instanceof Category) {
            $siteId = $entity->getSite()->getId();
            $this->pageCache->invalidateByPrefix($siteId, '/' . $entity->getSlug() . '/');
        }
    }
}
