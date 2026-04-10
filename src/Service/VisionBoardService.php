<?php

namespace App\Service;

use App\Entity\Blog;
use Symfony\Component\HttpFoundation\RequestStack;

class VisionBoardService
{
    private const SESSION_KEY = 'vision_board_blog_ids';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return int[]
     */
    public function getBlogIds(): array
    {
        $session = $this->requestStack->getSession();
        if (null === $session) {
            return [];
        }

        $ids = $session->get(self::SESSION_KEY, []);

        return array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
    }

    public function contains(Blog $blog): bool
    {
        $blogId = $blog->getId();

        return null !== $blogId && in_array($blogId, $this->getBlogIds(), true);
    }

    public function add(Blog $blog): int
    {
        $blogId = $blog->getId();
        if (null === $blogId) {
            return count($this->getBlogIds());
        }

        $ids = $this->getBlogIds();
        if (!in_array($blogId, $ids, true)) {
            $ids[] = $blogId;
        }

        $this->persist($ids);

        return count($ids);
    }

    public function remove(Blog $blog): int
    {
        $blogId = $blog->getId();
        $ids = $this->getBlogIds();

        if (null !== $blogId) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $blogId));
        }

        $this->persist($ids);

        return count($ids);
    }

    public function toggle(Blog $blog): array
    {
        $isSaved = $this->contains($blog);
        $count = $isSaved ? $this->remove($blog) : $this->add($blog);

        return [
            'saved' => !$isSaved,
            'count' => $count,
        ];
    }

    public function count(): int
    {
        return count($this->getBlogIds());
    }

    /**
     * @param int[] $ids
     */
    private function persist(array $ids): void
    {
        $session = $this->requestStack->getSession();
        if (null === $session) {
            return;
        }

        $session->set(self::SESSION_KEY, array_values(array_unique(array_filter(array_map('intval', $ids)))));
    }
}
