<?php

namespace App\Controller;

use App\Repository\DirectusFilesRepository;
use App\Repository\GalaxyRepository;
use App\Repository\ModelesFilesRepository;
use App\Repository\ModelesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CarouselController extends AbstractController
{
    #[Route('/carousel', name: 'app_carousel')]
    public function index(Request $request, GalaxyRepository $galaxyRepository, ModelesRepository $modelesRepository, ModelesFilesRepository $modelesFilesRepository, DirectusFilesRepository $directusFilesRepository): Response
    {
        $limit = 4;
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * $limit;

        $galaxies = $galaxyRepository->findBy([], ['sort' => 'ASC'], $limit, $offset);
        $totalGalaxies = $galaxyRepository->count([]);
        $totalPages = ceil($totalGalaxies / $limit);

        $carousel = [];

        foreach($galaxies as $galaxy) {
            $carouselItem = [
                'title' => $galaxy->getTitle(),
                'description' => $galaxy->getDescription(),
            ];

            $modele = $modelesRepository->find($galaxy->getModele());
            $modelesFiles = $modelesFilesRepository->findBy([
                'modeles_id' => $modele->getId()
            ]);
            $files = [];

            foreach($modelesFiles as $modelesFile) {
                $file = $directusFilesRepository->find($modelesFile->getDirectusFilesId());
                $files[] = $file;
            }
            $carouselItem['files'] = $files;
            $carousel[] = $carouselItem;
        }

        $response = $this->render('carousel/index.html.twig', [
            'carousel' => $carousel,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);

        // Cache HTTP : la page est mise en cache 1h côté navigateur
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
