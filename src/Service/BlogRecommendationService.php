<?php

namespace App\Service;

use App\Entity\Blog;
use App\Repository\BlogRatingRepository;
use App\Repository\BlogViewsRepository;
use App\Repository\CommentaireRepository;

class BlogRecommendationService
{
    private const MINIMUM_VOTES_THRESHOLD = 5;
    private const FRESHNESS_WINDOW_DAYS = 30;

    public function __construct(
        private readonly BlogViewsRepository $blogViewsRepository,
        private readonly BlogRatingRepository $blogRatingRepository,
        private readonly CommentaireRepository $commentaireRepository,
    ) {
    }

    /**
     * @param Blog[] $blogs
     */
    public function buildMetrics(array $blogs): array
    {
        $blogIds = array_values(array_filter(array_map(static fn (Blog $blog): ?int => $blog->getId(), $blogs)));
        $viewCounts = $this->blogViewsRepository->countByBlogIds($blogIds);
        $commentCounts = $this->commentaireRepository->countByBlogIds($blogIds);
        $ratingStats = $this->blogRatingRepository->getAggregatedStatsByBlogIds($blogIds);
        $globalAverageRating = $this->blogRatingRepository->getGlobalAverageRating();

        $maxLogViews = 0.0;
        $maxEngagement = 0.0;
        $prepared = [];

        foreach ($blogs as $blog) {
            $blogId = $blog->getId();
            if (null === $blogId) {
                continue;
            }

            $ratingCount = $ratingStats[$blogId]['count'] ?? (int) ($blog->getRatingCount() ?? 0);
            $ratingAverage = $ratingStats[$blogId]['average'] ?? round((float) ($blog->getRatingAverage() ?? 0), 1);
            $views = $viewCounts[$blogId] ?? 0;
            $comments = $commentCounts[$blogId] ?? 0;
            $logViews = log(1 + $views);
            $engagementRaw = log(1 + $comments + ($ratingCount * 2));

            $maxLogViews = max($maxLogViews, $logViews);
            $maxEngagement = max($maxEngagement, $engagementRaw);

            $prepared[$blogId] = [
                'blog' => $blog,
                'rating_count' => $ratingCount,
                'rating_average' => $ratingAverage,
                'views' => $views,
                'comments' => $comments,
                'read_time' => max(1, (int) ceil(mb_strlen((string) $blog->getContenu()) / 700)),
                'cover_image' => $this->resolveBlogImage($blog),
                'published_at' => $blog->getDatePublication() ?? $blog->getDateCreation(),
                'log_views_raw' => $logViews,
                'engagement_raw' => $engagementRaw,
                'bayesian_rating' => $this->calculateBayesianRating($ratingAverage, $ratingCount, $globalAverageRating),
            ];
        }

        $metrics = [];
        foreach ($prepared as $blogId => $data) {
            /** @var Blog $blog */
            $blog = $data['blog'];
            $recencyScore = $this->calculateRecencyScore($data['published_at']);
            $logViewsScore = $maxLogViews > 0 ? $data['log_views_raw'] / $maxLogViews : 0.0;
            $engagementScore = $maxEngagement > 0 ? $data['engagement_raw'] / $maxEngagement : 0.0;
            $score = (0.45 * ($data['bayesian_rating'] / 5))
                + (0.30 * $logViewsScore)
                + (0.15 * $recencyScore)
                + (0.10 * $engagementScore);

            $metrics[$blogId] = [
                'rating_average' => $data['rating_average'],
                'rating_count' => $data['rating_count'],
                'views' => $data['views'],
                'comments' => $data['comments'],
                'read_time' => $data['read_time'],
                'cover_image' => $data['cover_image'],
                'category' => $this->resolveBlogCategory(
                    $data['bayesian_rating'],
                    $data['rating_count'],
                    $data['views'],
                    $recencyScore,
                    $engagementScore
                ),
                'score' => round($score * 100, 2),
                'bayesian_rating' => round($data['bayesian_rating'], 2),
                'recency_score' => round($recencyScore, 2),
                'engagement_score' => round($engagementScore, 2),
            ];
        }

        return $metrics;
    }

    private function calculateBayesianRating(float $averageRating, int $voteCount, float $globalAverageRating): float
    {
        $minimumVotes = self::MINIMUM_VOTES_THRESHOLD;
        $globalAverageRating = $globalAverageRating > 0 ? $globalAverageRating : $averageRating;

        if ($voteCount <= 0) {
            return round($globalAverageRating, 2);
        }

        return (($voteCount / ($voteCount + $minimumVotes)) * $averageRating)
            + (($minimumVotes / ($voteCount + $minimumVotes)) * $globalAverageRating);
    }

    private function calculateRecencyScore(?\DateTime $publishedAt): float
    {
        if (!$publishedAt instanceof \DateTime) {
            return 0.0;
        }

        $ageInDays = max(0, (int) $publishedAt->diff(new \DateTime())->days);

        return max(0.0, 1 - ($ageInDays / self::FRESHNESS_WINDOW_DAYS));
    }

    private function resolveBlogCategory(
        float $bayesianRating,
        int $ratingCount,
        int $views,
        float $recencyScore,
        float $engagementScore
    ): string {
        if ($bayesianRating >= 4.2 && $ratingCount >= self::MINIMUM_VOTES_THRESHOLD) {
            return 'Mieux notes';
        }

        if ($views >= 50) {
            return 'Plus vus';
        }

        if ($recencyScore >= 0.65) {
            return 'Nouveautes';
        }

        if ($engagementScore >= 0.45) {
            return 'Tendance';
        }

        return 'Coups de coeur';
    }

    private function resolveBlogImage(Blog $blog): string
    {
        $image = trim((string) $blog->getImageCouverture());
        if ('' !== $image) {
            return $image;
        }

        return sprintf('https://picsum.photos/seed/blog-cover-%d/1200/900', $blog->getId() ?? random_int(1, 9999));
    }
}
