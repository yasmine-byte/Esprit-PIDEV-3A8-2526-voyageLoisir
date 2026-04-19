<?php

namespace App\Controller;

use App\Entity\CommentReport;
use App\Entity\Commentaire;
use App\Repository\CommentReactionRepository;
use App\Repository\CommentReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/comment-reports')]
class AdminCommentReportController extends AbstractController
{
    #[Route('', name: 'admin_comment_reports_index', methods: ['GET'])]
    public function index(Request $request, CommentReportRepository $commentReportRepository): Response
    {
        $status = (string) $request->query->get('status', 'all');
        $reason = (string) $request->query->get('reason', 'all');

        if ('all' !== $status && !CommentReport::isValidStatus($status)) {
            $status = 'all';
        }

        if ('all' !== $reason && !CommentReport::isValidReason($reason)) {
            $reason = 'all';
        }

        $reports = $commentReportRepository->createAdminListQueryBuilder($status, $reason)
            ->getQuery()
            ->getResult();

        return $this->render('admin/comment_report/index.html.twig', [
            'reports' => $reports,
            'status_filter' => $status,
            'reason_filter' => $reason,
            'all_statuses' => CommentReport::STATUSES,
            'all_reasons' => CommentReport::REASONS,
        ]);
    }

    #[Route('/{id}/dismiss', name: 'admin_comment_report_dismiss', methods: ['POST'])]
    public function dismiss(Request $request, CommentReport $report, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('admin_dismiss_report_' . $report->getId(), (string) $request->request->get('_token'))) {
            $report->setStatus(CommentReport::STATUS_DISMISSED);
            $report->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Signalement ignore.');
        }

        return $this->redirectToRoute('admin_comment_reports_index', $this->extractFilters($request));
    }

    #[Route('/{id}/review', name: 'admin_comment_report_review', methods: ['POST'])]
    public function review(Request $request, CommentReport $report, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('admin_review_report_' . $report->getId(), (string) $request->request->get('_token'))) {
            $report->setStatus(CommentReport::STATUS_REVIEWED);
            $report->setUpdatedAt(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Signalement marque comme traite.');
        }

        return $this->redirectToRoute('admin_comment_reports_index', $this->extractFilters($request));
    }

    #[Route('/{id}/delete-comment', name: 'admin_comment_report_delete_comment', methods: ['POST'])]
    public function deleteComment(
        Request $request,
        CommentReport $report,
        CommentReactionRepository $commentReactionRepository,
        CommentReportRepository $commentReportRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_delete_report_comment_' . $report->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_comment_reports_index', $this->extractFilters($request));
        }

        $comment = $report->getCommentaire();
        if ($comment instanceof Commentaire) {
            // Defensive cleanup to ensure no related reaction/report keeps the comment visible due inconsistent DB constraints.
            $commentReactionRepository->deleteByComment($comment);
            $commentReportRepository->deleteByComment($comment);
            $entityManager->remove($comment);
            $entityManager->flush();
            $this->addFlash('success', 'Commentaire supprime avec succes.');
        } else {
            $this->addFlash('success', 'Commentaire deja indisponible.');
        }

        return $this->redirectToRoute('admin_comment_reports_index', $this->extractFilters($request));
    }

    private function extractFilters(Request $request): array
    {
        return array_filter([
            'status' => $request->request->get('status_filter') ?: null,
            'reason' => $request->request->get('reason_filter') ?: null,
        ]);
    }
}
