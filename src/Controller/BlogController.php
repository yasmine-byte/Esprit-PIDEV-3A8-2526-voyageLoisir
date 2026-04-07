<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\BlogRating;
use App\Entity\BlogViews;
use App\Entity\Commentaire;
use App\Form\BlogType;
use App\Repository\BlogRatingRepository;
use App\Repository\BlogRepository;
use App\Repository\BlogViewsRepository;
use App\Repository\CommentaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    #[Route(name: 'app_blog_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        BlogRepository $blogRepository,
        BlogViewsRepository $blogViewsRepository,
        BlogRatingRepository $blogRatingRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $blog = new Blog();
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBlogWorkflow($blog, $form->get('publish')->isClicked());
            $entityManager->persist($blog);
            $entityManager->flush();

            $this->addFlash(
                $blog->isPublicationRequested() ? 'pending' : 'success',
                $blog->isPublicationRequested()
                    ? 'En attente de validation admin'
                    : 'Your blog has been saved as a draft.'
            );

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        $publishedBlogs = $blogRepository->findBy(['status' => true], ['datePublication' => 'DESC', 'id' => 'DESC']);
        $viewCounts = [];
        foreach ($blogViewsRepository->findAll() as $view) {
            $blogId = $view->getBlog()?->getId();
            if (null === $blogId) {
                continue;
            }

            $viewCounts[$blogId] = ($viewCounts[$blogId] ?? 0) + 1;
        }

        $ratingStats = [];
        foreach ($blogRatingRepository->findAll() as $rating) {
            $blogId = $rating->getBlog()?->getId();
            if (null === $blogId) {
                continue;
            }

            if (!isset($ratingStats[$blogId])) {
                $ratingStats[$blogId] = ['sum' => 0, 'count' => 0];
            }

            $ratingStats[$blogId]['sum'] += $rating->getRating() ?? 0;
            $ratingStats[$blogId]['count']++;
        }

        $blogMetrics = [];
        foreach ($publishedBlogs as $publishedBlog) {
            $blogId = $publishedBlog->getId();
            $ratingCount = $ratingStats[$blogId]['count'] ?? ($publishedBlog->getRatingCount() ?? 0);
            $ratingAverage = $ratingCount > 0
                ? round(($ratingStats[$blogId]['sum'] ?? 0) / $ratingCount, 1)
                : round((float) ($publishedBlog->getRatingAverage() ?? 0), 1);
            $views = $viewCounts[$blogId] ?? 0;
            $blogMetrics[$blogId] = [
                'rating_average' => $ratingAverage,
                'rating_count' => $ratingCount,
                'views' => $views,
                'read_time' => max(1, (int) ceil(strlen((string) $publishedBlog->getContenu()) / 700)),
                'cover_image' => $this->resolveBlogImage($publishedBlog),
                'category' => $this->resolveBlogCategory($ratingAverage, $views),
                'score' => ($ratingAverage * 10) + min($views, 250),
            ];
        }

        $topBlogs = $publishedBlogs;
        usort($topBlogs, fn (Blog $a, Blog $b) => ($blogMetrics[$b->getId()]['score'] ?? 0) <=> ($blogMetrics[$a->getId()]['score'] ?? 0));

        return $this->render('blog/index.html.twig', [
            'blogs' => $publishedBlogs,
            'latestStories' => array_slice($publishedBlogs, 0, 4),
            'topBlogs' => array_slice($topBlogs, 0, 4),
            'blog_metrics' => $blogMetrics,
            'blog_form' => $form->createView(),
            'show_blog_modal' => $form->isSubmitted() && !$form->isValid(),
        ]);
    }

    #[Route('/new', name: 'app_blog_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $blog = new Blog();
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBlogWorkflow($blog, $form->get('publish')->isClicked());
            $entityManager->persist($blog);
            $entityManager->flush();

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blog/new.html.twig', [
            'blog' => $blog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_blog_show', methods: ['GET'])]
    public function show(
        Request $request,
        Blog $blog,
        CommentaireRepository $commentaireRepository,
        BlogRatingRepository $blogRatingRepository,
        BlogViewsRepository $blogViewsRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        if (!$blog->getStatus()) {
            throw $this->createNotFoundException('This blog is not published yet.');
        }

        $this->trackBlogView($request, $blog, $blogViewsRepository, $entityManager);

        $viewerRole = $this->resolveViewerRole();
        $currentUserName = $this->resolveCurrentUserName();
        $canInteract = true;

        $comments = $commentaireRepository->findBy(['blog' => $blog], ['dateCreation' => 'DESC', 'id' => 'DESC']);
        $ratings = $blogRatingRepository->findBy(['blog' => $blog], ['createdAt' => 'DESC', 'id' => 'DESC']);
        $views = $blogViewsRepository->count(['blog' => $blog]);
        $averageRating = $this->calculateAverageRating($ratings, $blog);
        $ratingCount = count($ratings);
        $ratingsByUser = [];
        foreach ($ratings as $rating) {
            if (!isset($ratingsByUser[$rating->getUserName()])) {
                $ratingsByUser[$rating->getUserName()] = $rating;
            }
        }

        return $this->render('blog/show.html.twig', [
            'blog' => $blog,
            'comments' => $comments,
            'ratings' => $ratings,
            'ratings_by_user' => $ratingsByUser,
            'views_count' => $views,
            'average_rating' => $averageRating,
            'rating_count' => $ratingCount,
            'viewer_role' => $viewerRole,
            'can_interact' => $canInteract,
            'current_user_name' => $currentUserName,
        ]);
    }

    #[Route('/{id}/engage', name: 'app_blog_engage', methods: ['POST'])]
    public function engage(
        Request $request,
        Blog $blog,
        BlogRatingRepository $blogRatingRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        if (!$blog->getStatus()) {
            return $this->json(['message' => 'This blog is not published yet.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isCsrfTokenValid('blog_engage_' . $blog->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['message' => 'Invalid request token.'], Response::HTTP_FORBIDDEN);
        }

        $currentUserName = $this->resolveCurrentUserName();
        $postedUserName = trim((string) $request->request->get('userName'));
        $authorName = null !== $currentUserName ? $currentUserName : $postedUserName;
        $content = trim((string) $request->request->get('contenu'));
        $ratingValue = (int) $request->request->get('rating');

        if ('' === $authorName || '' === $content || $ratingValue < 1 || $ratingValue > 5) {
            return $this->json(['message' => 'Please enter your name, write a comment, and choose a rating between 1 and 5.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $comment = new Commentaire();
        $comment->setBlog($blog);
        $comment->setNomuser($authorName);
        $comment->setContenu($content);
        $comment->setDateCreation(new \DateTime());
        $comment->setLikesCount(0);
        $comment->setImg(null);
        $entityManager->persist($comment);

        $existingRating = $blogRatingRepository->findOneBy([
            'blog' => $blog,
            'userName' => $authorName,
        ]);

        $rating = $existingRating ?? new BlogRating();
        $rating->setBlog($blog);
        $rating->setUserName($authorName);
        $rating->setRating($ratingValue);
        $rating->setReviewText($content);
        $rating->setCreatedAt(new \DateTime());

        if (null === $existingRating) {
            $entityManager->persist($rating);
        }

        $entityManager->flush();
        $this->refreshBlogRatingStats($blog, $blogRatingRepository, $entityManager);

        return $this->json([
            'message' => 'Your review has been published.',
            'comment' => [
                'id' => $comment->getId(),
                'author' => $comment->getNomuser(),
                'content' => $comment->getContenu(),
                'createdAt' => $comment->getDateCreation()?->format('Y-m-d H:i'),
                'likesCount' => $comment->getLikesCount() ?? 0,
                'rating' => $rating->getRating(),
                'likeUrl' => $this->generateUrl('app_commentaire_like', ['id' => $comment->getId()]),
                'likeToken' => $this->container->get('security.csrf.token_manager')->getToken('like_comment_' . $comment->getId())->getValue(),
            ],
            'stats' => [
                'averageRating' => $this->calculateAverageRating($blogRatingRepository->findBy(['blog' => $blog]), $blog),
                'ratingCount' => (int) ($blog->getRatingCount() ?? 0),
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_blog_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBlogWorkflow($blog, $form->get('publish')->isClicked(), false);
            $entityManager->flush();

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blog/edit.html.twig', [
            'blog' => $blog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_blog_delete', methods: ['POST'])]
    public function delete(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$blog->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($blog);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
    }

    private function applyBlogWorkflow(Blog $blog, bool $publish, bool $isNew = true): void
    {
        $now = new \DateTime();
        $slugger = new AsciiSlugger();

        if ($isNew || null === $blog->getDateCreation()) {
            $blog->setDateCreation($now);
        }

        if (!$blog->getSlug() && $blog->getTitre()) {
            $blog->setSlug(strtolower($slugger->slug($blog->getTitre())->toString()));
        }

        if ($publish) {
            $blog->setStatus(false);
            $blog->setPublicationRequested(true);
            $blog->setDatePublication(null);

            return;
        }

        $blog->setStatus(false);
        $blog->setPublicationRequested(false);

        if ($isNew) {
            $blog->setDatePublication(null);
        }
    }

    private function resolveBlogImage(Blog $blog): string
    {
        $image = trim((string) $blog->getImageCouverture());
        if ('' !== $image) {
            return $image;
        }

        return sprintf('https://picsum.photos/seed/blog-cover-%d/1200/900', $blog->getId() ?? random_int(1, 9999));
    }

    private function resolveBlogCategory(float $ratingAverage, int $views): string
    {
        if ($ratingAverage >= 4.5) {
            return 'Top Rated';
        }

        if ($views >= 50) {
            return 'Most Viewed';
        }

        if ($views >= 10) {
            return 'Trending';
        }

        return 'Fresh Pick';
    }

    private function trackBlogView(Request $request, Blog $blog, BlogViewsRepository $blogViewsRepository, EntityManagerInterface $entityManager): void
    {
        $session = $request->getSession();
        $key = sprintf('blog_viewed_%d', $blog->getId());

        if ($session->has($key)) {
            return;
        }

        $view = new BlogViews();
        $view->setBlog($blog);
        $view->setViewDate(new \DateTime());
        $view->setUserIdentifier($session->getId());

        $entityManager->persist($view);
        $entityManager->flush();

        $session->set($key, true);
    }

    private function refreshBlogRatingStats(Blog $blog, BlogRatingRepository $blogRatingRepository, EntityManagerInterface $entityManager): void
    {
        $ratings = $blogRatingRepository->findBy(['blog' => $blog]);
        $count = count($ratings);
        $sum = array_reduce($ratings, static fn (int $carry, BlogRating $rating) => $carry + ((int) $rating->getRating()), 0);

        $blog->setRatingCount($count);
        $blog->setRatingAverage($count > 0 ? round($sum / $count, 1) : 0.0);

        $entityManager->flush();
    }

    private function calculateAverageRating(array $ratings, Blog $blog): float
    {
        if ([] === $ratings) {
            return round((float) ($blog->getRatingAverage() ?? 0), 1);
        }

        $sum = array_reduce($ratings, static fn (int $carry, BlogRating $rating) => $carry + ((int) $rating->getRating()), 0);

        return round($sum / count($ratings), 1);
    }

    private function resolveViewerRole(): string
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return 'ROLE_ADMIN';
        }

        return null !== $this->resolveCurrentUserName() ? 'ROLE_USER' : 'ROLE_GUEST';
    }

    private function resolveCurrentUserName(): ?string
    {
        $user = $this->getUser();

        if (is_object($user)) {
            $parts = [];
            if (method_exists($user, 'getPrenom') && $user->getPrenom()) {
                $parts[] = trim((string) $user->getPrenom());
            }
            if (method_exists($user, 'getNom') && $user->getNom()) {
                $parts[] = trim((string) $user->getNom());
            }
            if ([] !== $parts) {
                return trim(implode(' ', $parts));
            }
            if (method_exists($user, 'getUserIdentifier')) {
                return trim((string) $user->getUserIdentifier()) ?: null;
            }
            if (method_exists($user, 'getEmail')) {
                return trim((string) $user->getEmail()) ?: null;
            }
        }

        if (is_string($user) && '' !== trim($user)) {
            return trim($user);
        }

        return null;
    }
}
