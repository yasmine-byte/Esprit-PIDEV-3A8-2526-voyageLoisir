<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\BlogRating;
use App\Entity\BlogViews;
use App\Entity\CommentReaction;
use App\Entity\Commentaire;
use App\Entity\UserFavorite;
use App\Entity\Users;
use App\Form\BlogType;
use App\Repository\BlogRatingRepository;
use App\Repository\BlogRepository;
use App\Repository\BlogViewsRepository;
use App\Repository\CommentReactionRepository;
use App\Repository\CommentReportRepository;
use App\Repository\CommentaireRepository;
use App\Repository\UserFavoriteRepository;
use App\Service\BlogExcerptGeneratorService;
use App\Service\BlogRecommendationService;
use App\Service\CommentModerationService;
use App\Service\VisionBoardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    public function __construct(
        private readonly BlogExcerptGeneratorService $blogExcerptGeneratorService,
    ) {
    }

    #[Route(name: 'app_blog_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        BlogRepository $blogRepository,
        BlogRecommendationService $blogRecommendationService,
        UserFavoriteRepository $userFavoriteRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $blog = new Blog();
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleBlogImageUpload($form->get('blogCoverImage')->getData(), $blog);
            $this->assignAuthorFromCurrentUser($blog);
            $this->applyBlogWorkflow($blog, $form->get('publish')->isClicked());
            $entityManager->persist($blog);
            $entityManager->flush();

            $this->addFlash(
                $blog->isPublicationRequested() ? 'pending' : 'success',
                $blog->isPublicationRequested()
                    ? 'En attente de validation admin'
                    : 'Votre blog a ete enregistre en brouillon.'
            );

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        $publishedBlogs = $blogRepository->findPublishedOrdered();
        $blogMetrics = $blogRecommendationService->buildMetrics($publishedBlogs);
        $activeCategory = (string) $request->query->get('categorie', 'all');
        $search = mb_strtolower(trim((string) $request->query->get('q', '')));
        $sort = (string) $request->query->get('sort', 'recent');
        $allowedSorts = ['recent', 'oldest', 'views', 'rating', 'alpha'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'recent';
        }

        $filterModes = ['all', 'mieux-notes', 'plus-vus', 'nouveautes', 'tendance', 'coups-de-coeur'];
        if (!in_array($activeCategory, $filterModes, true)) {
            $activeCategory = 'all';
        }

        $filteredBlogs = array_values(array_filter($publishedBlogs, function (Blog $publishedBlog) use ($activeCategory, $blogMetrics, $search): bool {
            $blogId = $publishedBlog->getId();
            if (null === $blogId) {
                return false;
            }

            $blogCategory = (string) ($blogMetrics[$blogId]['category'] ?? 'Nouveautes');
            if ('nouveautes' === $activeCategory && 'Nouveautes' !== $blogCategory) {
                return false;
            }
            if ('tendance' === $activeCategory && 'Tendance' !== $blogCategory) {
                return false;
            }
            if ('coups-de-coeur' === $activeCategory && 'Coups de coeur' !== $blogCategory) {
                return false;
            }

            if ('' === $search) {
                return true;
            }

            $haystack = mb_strtolower(trim(
                implode(' ', [
                    (string) $publishedBlog->getTitre(),
                    (string) $publishedBlog->getExtrait(),
                    (string) $publishedBlog->getContenu(),
                    (string) $blogCategory,
                ])
            ));

            return str_contains($haystack, $search);
        }));

        $effectiveSort = $sort;
        if ('mieux-notes' === $activeCategory) {
            $effectiveSort = 'rating';
        } elseif ('plus-vus' === $activeCategory) {
            $effectiveSort = 'views';
        }

        usort($filteredBlogs, function (Blog $a, Blog $b) use ($effectiveSort, $blogMetrics): int {
            $aId = (int) ($a->getId() ?? 0);
            $bId = (int) ($b->getId() ?? 0);
            $aMetrics = $blogMetrics[$aId] ?? [];
            $bMetrics = $blogMetrics[$bId] ?? [];

            return match ($effectiveSort) {
                'oldest' => (($a->getDatePublication() ?? $a->getDateCreation()) <=> ($b->getDatePublication() ?? $b->getDateCreation())),
                'views' => (($bMetrics['views'] ?? 0) <=> ($aMetrics['views'] ?? 0)),
                'rating' => (($bMetrics['rating_average'] ?? 0) <=> ($aMetrics['rating_average'] ?? 0)),
                'alpha' => strcmp((string) $a->getTitre(), (string) $b->getTitre()),
                default => (($b->getDatePublication() ?? $b->getDateCreation()) <=> ($a->getDatePublication() ?? $a->getDateCreation())),
            };
        });

        $topBlogs = $filteredBlogs;
        usort($topBlogs, fn (Blog $a, Blog $b) => ($blogMetrics[$b->getId()]['score'] ?? 0) <=> ($blogMetrics[$a->getId()]['score'] ?? 0));

        $topViewedBlogs = $publishedBlogs;
        usort($topViewedBlogs, fn (Blog $a, Blog $b) => ($blogMetrics[$b->getId()]['views'] ?? 0) <=> ($blogMetrics[$a->getId()]['views'] ?? 0));
        $topViewedBlogs = array_slice($topViewedBlogs, 0, 3);

        $magazineRows = [];
        $cursor = 0;
        $useTypeA = true;
        $totalFiltered = count($filteredBlogs);
        while ($cursor < $totalFiltered) {
            $chunk = array_slice($filteredBlogs, $cursor, 3);
            if ([] === $chunk) {
                break;
            }
            $magazineRows[] = [
                'type' => $useTypeA ? 'A' : 'B',
                'items' => $chunk,
            ];
            $cursor += 3;
            $useTypeA = !$useTypeA;
        }

        $weekStart = new \DateTimeImmutable('monday this week');
        $featuredRows = $entityManager->createQueryBuilder()
            ->select('IDENTITY(c.blog) AS blog_id', 'COUNT(cr.id) AS reaction_count')
            ->from(CommentReaction::class, 'cr')
            ->leftJoin('cr.commentaire', 'c')
            ->leftJoin('c.blog', 'b')
            ->andWhere('b.status = :published')
            ->andWhere('cr.createdAt >= :weekStart')
            ->setParameter('published', true)
            ->setParameter('weekStart', $weekStart)
            ->groupBy('c.blog')
            ->orderBy('reaction_count', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getArrayResult();

        $featuredBlogIds = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['blog_id'] ?? 0), $featuredRows)));
        $featuredReactionCounts = [];
        foreach ($featuredRows as $row) {
            $blogId = (int) ($row['blog_id'] ?? 0);
            if ($blogId <= 0) {
                continue;
            }
            $featuredReactionCounts[$blogId] = (int) ($row['reaction_count'] ?? 0);
        }

        $featuredWeekBlogs = [];
        if ([] !== $featuredBlogIds) {
            $featuredWeekBlogs = $blogRepository->findBy(['id' => $featuredBlogIds]);
            usort($featuredWeekBlogs, static fn (Blog $a, Blog $b): int => array_search($a->getId(), $featuredBlogIds, true) <=> array_search($b->getId(), $featuredBlogIds, true));
        }

        $favoriteBlogIds = [];
        $favoriteCount = 0;
        $connectedUser = $this->getUser();
        if ($connectedUser instanceof Users) {
            $favoriteBlogIds = $userFavoriteRepository->findBlogIdsByUser($connectedUser);
            $favoriteCount = count($favoriteBlogIds);
        }

        return $this->render('blog/index.html.twig', [
            'blogs' => $publishedBlogs,
            'filtered_blogs' => $filteredBlogs,
            'hero_blogs' => $topViewedBlogs,
            'top_viewed_blogs' => $topViewedBlogs,
            'top_articles' => $topViewedBlogs,
            'latestStories' => array_slice($publishedBlogs, 0, 4),
            'topBlogs' => array_slice($topBlogs, 0, 4),
            'magazine_rows' => $magazineRows,
            'blog_metrics' => $blogMetrics,
            'active_category' => $activeCategory,
            'search_query' => $request->query->get('q', ''),
            'sort_filter' => $sort,
            'category_filters' => $filterModes,
            'featured_week_blogs' => $featuredWeekBlogs,
            'featured_week_reactions' => $featuredReactionCounts,
            'vision_board_count' => $favoriteCount,
            'favorite_blog_ids' => $favoriteBlogIds,
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
            $this->handleBlogImageUpload($form->get('blogCoverImage')->getData(), $blog);
            $this->assignAuthorFromCurrentUser($blog);
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

    #[Route('/{id}', name: 'app_blog_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        Request $request,
        Blog $blog,
        BlogRepository $blogRepository,
        BlogRecommendationService $blogRecommendationService,
        CommentaireRepository $commentaireRepository,
        BlogRatingRepository $blogRatingRepository,
        BlogViewsRepository $blogViewsRepository,
        UserFavoriteRepository $userFavoriteRepository,
        CommentReactionRepository $commentReactionRepository,
        CommentReportRepository $commentReportRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        if (!$blog->getStatus()) {
            throw $this->createNotFoundException('Ce blog n est pas encore publie.');
        }

        $this->trackBlogView($request, $blog, $blogViewsRepository, $entityManager);

        $viewerRole = $this->resolveViewerRole();
        $currentUserName = $this->resolveCurrentUserName();
        $canInteract = $this->isGranted('ROLE_USER');

        $comments = $commentaireRepository->findBy(['blog' => $blog], ['dateCreation' => 'DESC', 'id' => 'DESC']);
        $ratings = $blogRatingRepository->findBy(['blog' => $blog], ['createdAt' => 'DESC', 'id' => 'DESC']);
        $views = $blogViewsRepository->count(['blog' => $blog]);
        $averageRating = $this->calculateAverageRating($ratings, $blog);
        $ratingCount = count($ratings);
        $reactionCountsByComment = [];
        $userReactionsByComment = [];
        $reportedCommentIds = [];
        $ratingsByUser = [];
        foreach ($ratings as $rating) {
            if (!isset($ratingsByUser[$rating->getUserName()])) {
                $ratingsByUser[$rating->getUserName()] = $rating;
            }
        }

        foreach ($comments as $comment) {
            if (null === $comment->getId()) {
                continue;
            }
            $reactionCountsByComment[$comment->getId()] = $commentReactionRepository->aggregateCountsForComment($comment);
        }

        $connectedUser = $this->getUser();
        $isInVisionBoard = false;
        $visionBoardCount = 0;
        if ($connectedUser instanceof \App\Entity\Users) {
            $commentIds = array_values(array_filter(array_map(static fn (Commentaire $comment): ?int => $comment->getId(), $comments)));
            $userReactionsByComment = $commentReactionRepository->getUserReactionsByCommentIds($connectedUser, $commentIds);
            $reportedCommentIds = $commentReportRepository->getUserReportedCommentIds(
                $connectedUser,
                $commentIds
            );
            $isInVisionBoard = $userFavoriteRepository->isFavorite($connectedUser, $blog);
            $visionBoardCount = count($userFavoriteRepository->findBlogIdsByUser($connectedUser));
        }

        $recommendedBlogs = $blogRepository->createQueryBuilder('b')
            ->andWhere('b.status = :published')
            ->andWhere('b.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $blog->getId())
            ->orderBy('b.datePublication', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
        $recommendationMetrics = $blogRecommendationService->buildMetrics(array_merge([$blog], $recommendedBlogs));

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
            'is_in_vision_board' => $isInVisionBoard,
            'vision_board_count' => $visionBoardCount,
            'reaction_counts_by_comment' => $reactionCountsByComment,
            'user_reactions_by_comment' => $userReactionsByComment,
            'reported_comment_ids' => $reportedCommentIds,
            'recommended_blogs' => $recommendedBlogs,
            'recommendation_metrics' => $recommendationMetrics,
        ]);
    }

    #[Route('/{id}/engage', name: 'app_blog_engage', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function engage(
        Request $request,
        Blog $blog,
        BlogRatingRepository $blogRatingRepository,
        CommentModerationService $commentModerationService,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        if (!$blog->getStatus()) {
            return $this->json(['message' => 'Ce blog n est pas encore publie.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isCsrfTokenValid('blog_engage_' . $blog->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['message' => 'Jeton de requete invalide.'], Response::HTTP_FORBIDDEN);
        }

        $currentUserName = $this->resolveCurrentUserName();
        $postedUserName = trim((string) $request->request->get('userName'));
        $authorName = null !== $currentUserName ? $currentUserName : $postedUserName;
        $content = trim((string) $request->request->get('contenu'));
        $ratingValue = (int) $request->request->get('rating');

        if ('' === $authorName || '' === $content || $ratingValue < 1 || $ratingValue > 5) {
            return $this->json(['message' => 'Veuillez saisir votre nom, ecrire un commentaire et choisir une note entre 1 et 5.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $moderation = $commentModerationService->moderate($content);
        $sanitizedContent = $moderation['sanitized'] ?? $content;

        $comment = new Commentaire();
        $comment->setBlog($blog);
        $comment->setNomuser($authorName);
        $comment->setContenu($sanitizedContent);
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
        $rating->setReviewText($sanitizedContent);
        $rating->setCreatedAt(new \DateTime());

        if (null === $existingRating) {
            $entityManager->persist($rating);
        }

        $entityManager->flush();
        $this->refreshBlogRatingStats($blog, $blogRatingRepository, $entityManager);

        return $this->json([
            'message' => ($moderation['contains_blocked_words'] ?? false)
                ? 'Votre avis a ete publie apres masquage des mots inappropries.'
                : 'Votre avis a ete publie.',
            'comment' => [
                'id' => $comment->getId(),
                'author' => $comment->getNomuser(),
                'content' => $comment->getContenu(),
                'createdAt' => $comment->getDateCreation()?->format('Y-m-d H:i'),
                'likesCount' => $comment->getLikesCount() ?? 0,
                'rating' => $rating->getRating(),
                'likeUrl' => $this->generateUrl('app_commentaire_like', ['id' => $comment->getId()]),
                'likeToken' => $this->container->get('security.csrf.token_manager')->getToken('like_comment_' . $comment->getId())->getValue(),
                'reactUrl' => $this->generateUrl('app_commentaire_react', ['id' => $comment->getId()]),
                'reactToken' => $this->container->get('security.csrf.token_manager')->getToken('react_comment_' . $comment->getId())->getValue(),
                'reactionCounts' => [],
                'reportUrl' => $this->generateUrl('app_commentaire_report', ['id' => $comment->getId()]),
                'reportToken' => $this->container->get('security.csrf.token_manager')->getToken('report_comment_' . $comment->getId())->getValue(),
            ],
            'stats' => [
                'averageRating' => $this->calculateAverageRating($blogRatingRepository->findBy(['blog' => $blog]), $blog),
                'ratingCount' => (int) ($blog->getRatingCount() ?? 0),
            ],
            'moderation' => [
                'containsBlockedWords' => (bool) ($moderation['contains_blocked_words'] ?? false),
                'matchedTerms' => $moderation['matched_terms'] ?? [],
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_blog_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Blog $blog, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleBlogImageUpload($form->get('blogCoverImage')->getData(), $blog);
            $this->assignAuthorFromCurrentUser($blog);
            $this->applyBlogWorkflow($blog, $form->get('publish')->isClicked(), false);
            $entityManager->flush();

            return $this->redirectToRoute('app_blog_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('blog/edit.html.twig', [
            'blog' => $blog,
            'form' => $form,
        ]);
    }

    #[Route('/vision-board', name: 'app_blog_vision_board', methods: ['GET'])]
    public function visionBoard(): Response
    {
        if (!$this->isGranted('ROLE_USER')) {
            $this->addFlash('error', 'Connectez-vous pour accéder à votre vision board');
            return $this->redirectToRoute('front_login');
        }

        return $this->redirect($this->generateUrl('app_mon_espace') . '#vision-board');
    }

    #[Route('/{id}/vision-board', name: 'app_blog_toggle_vision_board', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleVisionBoard(
        Request $request,
        Blog $blog,
        UserFavoriteRepository $userFavoriteRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        /** @var Users|null $user */
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->json(['message' => 'Connectez-vous pour enregistrer cet article.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid('vision_board_' . $blog->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['message' => 'Jeton de requete invalide.'], Response::HTTP_FORBIDDEN);
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
            'message' => $saved
                ? 'Blog ajoute a votre vision board.'
                : 'Blog retire de votre vision board.',
        ]);
    }

    #[Route('/{id}', name: 'app_blog_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
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

        if ($isNew || null === $blog->getDateCreation()) {
            $blog->setDateCreation($now);
        }

        $blog->setSlug($this->generateSlug($blog->getTitre()));
        $blog->setExtrait(
            $this->generateExcerpt(
                $blog->getExtrait(),
                $blog->getTitre(),
                $blog->getContenu()
            )
        );

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

    private function handleBlogImageUpload(?UploadedFile $uploadedFile, Blog $blog): void
    {
        if (!$uploadedFile instanceof UploadedFile) {
            return;
        }

        $slugger = new AsciiSlugger();
        $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName ?: 'blog-cover')->lower()->toString();
        $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension() ?: 'jpg';
        $fileName = sprintf('%s-%s.%s', $safeName, uniqid('', true), $extension);
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/blog';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        try {
            $uploadedFile->move($uploadDir, $fileName);
        } catch (FileException $exception) {
            throw new \RuntimeException('Unable to upload the blog cover image.', 0, $exception);
        }

        $blog->setImageCouverture('/uploads/blog/' . $fileName);
    }

    private function generateSlug(?string $title): ?string
    {
        $title = trim((string) $title);
        if ('' === $title) {
            return null;
        }

        return strtolower((new AsciiSlugger())->slug($title)->toString());
    }

    private function generateExcerpt(?string $manualExcerpt, ?string $title, ?string $content): ?string
    {
        $manualExcerpt = trim(strip_tags((string) $manualExcerpt));
        if ('' !== $manualExcerpt) {
            return $manualExcerpt;
        }

        $content = trim(strip_tags((string) $content));
        if ('' === $content) {
            return null;
        }

        return $this->blogExcerptGeneratorService->generate($title, $content, 180);
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
        $summary = $blogRatingRepository->getBlogRatingSummary($blog);

        $blog->setRatingCount($summary['count']);
        $blog->setRatingAverage($summary['average']);

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

    private function assignAuthorFromCurrentUser(Blog $blog): void
    {
        if (null !== $blog->getAuthorId() && '' !== trim((string) $blog->getAuthorId())) {
            return;
        }

        $author = $this->resolveCurrentUserName();
        if (null !== $author && '' !== trim($author)) {
            $blog->setAuthorId($author);
        }
    }

    private function toCategoryKey(string $label): string
    {
        $slug = strtolower((new AsciiSlugger())->slug(trim($label))->toString());
        return '' !== $slug ? $slug : 'all';
    }
}
