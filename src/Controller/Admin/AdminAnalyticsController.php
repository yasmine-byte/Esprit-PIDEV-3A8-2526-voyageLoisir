<?php

namespace App\Controller\Admin;

use App\Entity\Blog;
use App\Entity\CommentReport;
use App\Repository\BlogRepository;
use App\Repository\CommentReactionRepository;
use App\Repository\CommentReportRepository;
use App\Repository\CommentaireRepository;
use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminAnalyticsController extends AbstractController
{
    #[Route('/admin/analytics', name: 'admin_analytics', methods: ['GET'])]
    public function index(
        BlogRepository $blogRepository,
        CommentaireRepository $commentaireRepository,
        CommentReactionRepository $commentReactionRepository,
        CommentReportRepository $commentReportRepository,
        UsersRepository $usersRepository
    ): Response {
        $blogs = $blogRepository->findAll();

        $publishedCount = 0;
        $draftCount = 0;
        $pendingCount = 0;
        $categoryBuckets = [];

        foreach ($blogs as $blog) {
            if (!$blog instanceof Blog) {
                continue;
            }

            $isPublished = (bool) $blog->isStatus();
            $isPending = !$isPublished && (bool) $blog->isPublicationRequested();

            if ($isPublished) {
                ++$publishedCount;
            } elseif ($isPending) {
                ++$pendingCount;
            } else {
                ++$draftCount;
            }

            $categoryLabel = $this->resolveCategoryLabel($blog);
            $categoryBuckets[$categoryLabel] = ($categoryBuckets[$categoryLabel] ?? 0) + 1;
        }

        ksort($categoryBuckets);

        $reactionRows = $commentReactionRepository->createQueryBuilder('cr')
            ->select('cr.reactionType AS reaction_type', 'COUNT(cr.id) AS total')
            ->groupBy('cr.reactionType')
            ->getQuery()
            ->getArrayResult();

        $reactionBuckets = [];
        foreach ($reactionRows as $row) {
            $type = (string) ($row['reaction_type'] ?? '');
            if ('' === $type) {
                continue;
            }
            $reactionBuckets[$type] = (int) ($row['total'] ?? 0);
        }

        $reactionOrder = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
        $reactionLabels = ['like' => 'Like', 'love' => 'Love', 'haha' => 'Haha', 'wow' => 'Wow', 'sad' => 'Sad', 'angry' => 'Angry'];
        $orderedReactionLabels = [];
        $orderedReactionData = [];
        foreach ($reactionOrder as $key) {
            if (!isset($reactionBuckets[$key])) {
                continue;
            }
            $orderedReactionLabels[] = $reactionLabels[$key];
            $orderedReactionData[] = $reactionBuckets[$key];
        }

        $reportRows = $commentReportRepository->createQueryBuilder('r')
            ->select('r.reason AS reason', 'COUNT(r.id) AS total')
            ->andWhere('r.status = :status')
            ->setParameter('status', CommentReport::STATUS_PENDING)
            ->groupBy('r.reason')
            ->getQuery()
            ->getArrayResult();

        $reportReasonLabels = [];
        $reportReasonData = [];
        foreach ($reportRows as $row) {
            $reason = (string) ($row['reason'] ?? 'other');
            $reportReasonLabels[] = ucfirst($reason);
            $reportReasonData[] = (int) ($row['total'] ?? 0);
        }

        $monthBuckets = [];
        for ($i = 11; $i >= 0; --$i) {
            $key = (new \DateTimeImmutable('first day of this month'))->modify("-{$i} months")->format('Y-m');
            $monthBuckets[$key] = 0;
        }

        foreach ($blogs as $blog) {
            if (!$blog instanceof Blog) {
                continue;
            }

            $date = $blog->getDatePublication() ?? $blog->getDateCreation();
            if (!$date instanceof \DateTimeInterface) {
                continue;
            }

            $monthKey = $date->format('Y-m');
            if (array_key_exists($monthKey, $monthBuckets)) {
                ++$monthBuckets[$monthKey];
            }
        }

        return $this->render('admin/analytics/index.html.twig', [
            'kpis' => [
                'published' => $publishedCount,
                'comments' => $commentaireRepository->count([]),
                'reactions' => $commentReactionRepository->count([]),
                'active_users' => $usersRepository->countActive(),
            ],
            'category_distribution' => [
                'labels' => array_keys($categoryBuckets),
                'data' => array_values($categoryBuckets),
            ],
            'reaction_distribution' => [
                'labels' => $orderedReactionLabels,
                'data' => $orderedReactionData,
            ],
            'status_distribution' => [
                'labels' => ['Publié', 'Brouillon', 'En attente'],
                'data' => [$publishedCount, $draftCount, $pendingCount],
            ],
            'articles_by_month' => [
                'labels' => array_keys($monthBuckets),
                'data' => array_values($monthBuckets),
            ],
            'report_reason_distribution' => [
                'labels' => $reportReasonLabels,
                'data' => $reportReasonData,
            ],
        ]);
    }

    private function resolveCategoryLabel(Blog $blog): string
    {
        $rawCategory = null;
        if (method_exists($blog, 'getCategorie')) {
            $rawCategory = $blog->getCategorie();
        } elseif (method_exists($blog, 'getCategory')) {
            $rawCategory = $blog->getCategory();
        }

        if (is_string($rawCategory)) {
            $label = trim($rawCategory);
            return '' !== $label ? $label : 'Non catégorisé';
        }

        if (is_object($rawCategory)) {
            if (method_exists($rawCategory, 'getNom')) {
                $label = trim((string) $rawCategory->getNom());
                return '' !== $label ? $label : 'Non catégorisé';
            }
            if (method_exists($rawCategory, 'getName')) {
                $label = trim((string) $rawCategory->getName());
                return '' !== $label ? $label : 'Non catégorisé';
            }
        }

        return 'Non catégorisé';
    }
}

