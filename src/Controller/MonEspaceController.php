<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\CommentReaction;
use App\Entity\UserFavorite;
use App\Entity\Users;
use App\Repository\BlogRepository;
use App\Repository\BlogViewsRepository;
use App\Repository\UserFavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-espace')]
#[IsGranted('ROLE_USER')]
class MonEspaceController extends AbstractController
{
    #[Route('', name: 'app_mon_espace', methods: ['GET'])]
    public function index(
        BlogRepository $blogRepository,
        BlogViewsRepository $blogViewsRepository,
        UserFavoriteRepository $userFavoriteRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Users $user */
        $user = $this->getUser();
        $identifiers = array_values(array_unique(array_filter([
            trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom())),
            trim((string) $user->getEmail()),
            trim((string) $user->getUserIdentifier()),
        ])));

        $myBlogs = [];
        if ([] !== $identifiers) {
            $myBlogs = $blogRepository->createQueryBuilder('b')
                ->andWhere('b.authorId IN (:identifiers)')
                ->setParameter('identifiers', $identifiers)
                ->orderBy('b.dateCreation', 'DESC')
                ->addOrderBy('b.id', 'DESC')
                ->getQuery()
                ->getResult();
        }

        $blogIds = array_values(array_filter(array_map(static fn (Blog $blog): int => (int) ($blog->getId() ?? 0), $myBlogs)));
        $viewsByBlog = $blogViewsRepository->countByBlogIds($blogIds);

        $reactionByBlog = [];
        if ([] !== $blogIds) {
            $rows = $entityManager->createQueryBuilder()
                ->select('IDENTITY(c.blog) AS blog_id', 'COUNT(cr.id) AS total_reactions')
                ->from(CommentReaction::class, 'cr')
                ->leftJoin('cr.commentaire', 'c')
                ->andWhere('c.blog IN (:blogIds)')
                ->setParameter('blogIds', $blogIds)
                ->groupBy('c.blog')
                ->getQuery()
                ->getArrayResult();

            foreach ($rows as $row) {
                $blogId = (int) ($row['blog_id'] ?? 0);
                if ($blogId <= 0) {
                    continue;
                }
                $reactionByBlog[$blogId] = (int) ($row['total_reactions'] ?? 0);
            }
        }

        $favoriteBlogIds = $userFavoriteRepository->findBlogIdsByUser($user);
        $favoriteBlogs = [] !== $favoriteBlogIds ? $blogRepository->findBy(['id' => $favoriteBlogIds]) : [];
        usort($favoriteBlogs, static fn (Blog $a, Blog $b): int => array_search($a->getId(), $favoriteBlogIds, true) <=> array_search($b->getId(), $favoriteBlogIds, true));

        $publishedCount = 0;
        foreach ($myBlogs as $myBlog) {
            if ($myBlog->isStatus()) {
                ++$publishedCount;
            }
        }
        $totalViews = array_sum($viewsByBlog);
        $totalReactions = array_sum($reactionByBlog);

        $level = 'Débutant';
        if ($publishedCount >= 10) {
            $level = 'Expert';
        } elseif ($publishedCount >= 3) {
            $level = 'Contributeur';
        }

        return $this->render('mon-espace/index.html.twig', [
            'my_blogs' => $myBlogs,
            'views_by_blog' => $viewsByBlog,
            'reactions_by_blog' => $reactionByBlog,
            'favorite_blogs' => $favoriteBlogs,
            'favorite_blog_ids' => $favoriteBlogIds,
            'stats' => [
                'published_count' => $publishedCount,
                'views_count' => $totalViews,
                'reactions_count' => $totalReactions,
                'level' => $level,
            ],
            'user_display_name' => trim(sprintf('%s %s', (string) $user->getPrenom(), (string) $user->getNom())),
        ]);
    }

    #[Route('/favorites/{id}/toggle', name: 'app_mon_espace_favorite_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleFavorite(
        Request $request,
        Blog $blog,
        UserFavoriteRepository $userFavoriteRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var Users $user */
        $user = $this->getUser();
        if (!$this->isCsrfTokenValid('favorite_blog_' . $blog->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['message' => 'Jeton invalide.'], Response::HTTP_FORBIDDEN);
        }

        $favorite = $userFavoriteRepository->findOneBy(['user' => $user, 'blog' => $blog]);
        $saved = false;
        if ($favorite instanceof UserFavorite) {
            $entityManager->remove($favorite);
        } else {
            $favorite = new UserFavorite();
            $favorite->setUser($user);
            $favorite->setBlog($blog);
            $favorite->setCreatedAt(new \DateTime());
            $entityManager->persist($favorite);
            $saved = true;
        }

        $entityManager->flush();
        $count = count($userFavoriteRepository->findBlogIdsByUser($user));

        return $this->json([
            'saved' => $saved,
            'count' => $count,
            'message' => $saved ? 'Ajouté à votre vision board.' : 'Retiré de votre vision board.',
        ]);
    }
}

