<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Repository\BlogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(BlogRepository $blogRepository): Response
    {
        $blogs = array_map(function (Blog $blog): array {
            $image = trim((string) $blog->getImageCouverture());
            $excerpt = trim((string) ($blog->getExtrait() ?: $blog->getContenu()));

            return [
                'id' => $blog->getId(),
                'title' => $blog->getTitre(),
                'slug' => $blog->getSlug(),
                'excerpt' => mb_strimwidth(strip_tags($excerpt), 0, 170, '...'),
                'image' => '' !== $image
                    ? $image
                    : sprintf('https://picsum.photos/seed/blog-home-%d/900/650', $blog->getId() ?? random_int(1, 9999)),
                'author' => $blog->getAuthorId() ?: 'VoyageLoisir',
                'published_at' => $blog->getDatePublication() ?? $blog->getDateCreation(),
                'rating' => round((float) ($blog->getRatingAverage() ?? 0), 1),
            ];
        }, $blogRepository->findBy(['status' => true], ['datePublication' => 'DESC', 'id' => 'DESC'], 6));

        return $this->render('home/index.html.twig', [
            'blogs' => $blogs,
        ]);
    }
    #[Route('/properties', name: 'app_properties')]
    public function properties(): Response
    {
        return $this->render('home/properties.html.twig');
    }

    #[Route('/property-details', name: 'app_property_details')]
    public function propertyDetails(): Response
    {
        return $this->render('home/property-details.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('home/contact.html.twig');
    }
}
