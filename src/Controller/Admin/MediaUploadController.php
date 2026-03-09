<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SiteRepository;
use App\Service\MediaManager;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/upload-media', name: 'admin_upload_media', methods: ['GET', 'POST'])]
class MediaUploadController extends AbstractController
{
    public function __construct(
        private readonly MediaManager $mediaManager,
        private readonly EntityManagerInterface $em,
        private readonly SiteContext $siteContext,
        private readonly SiteRepository $siteRepository,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('media_upload', $request->request->get('_token'))) {
                $errors[] = 'Invalid CSRF token. Please try again.';
            } else {
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $files */
                $files   = $request->files->all()['files'] ?? [];
                $altText = trim((string) $request->request->get('alt_text', ''));

                // Resolve site: from context (domain-based) or from explicit form field
                $site = $this->siteContext->hasSite()
                    ? $this->siteContext->getSite()
                    : $this->siteRepository->find((int) $request->request->get('site_id'));

                if (null === $site) {
                    $errors[] = 'Could not determine the site. Please select a site.';
                } elseif (empty($files)) {
                    $errors[] = 'Please select at least one file to upload.';
                } else {
                    $uploaded = 0;

                    foreach ($files as $file) {
                        try {
                            $this->mediaManager->upload($file, $site, $altText ?: null);
                            ++$uploaded;
                        } catch (\InvalidArgumentException|\RuntimeException $e) {
                            $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
                        }
                    }

                    $this->em->flush();

                    if ($uploaded > 0) {
                        $this->addFlash('success', sprintf('%d file(s) uploaded successfully.', $uploaded));

                        return $this->redirectToRoute('admin', [
                            'crudAction'         => 'index',
                            'crudControllerFqcn' => MediaCrudController::class,
                        ]);
                    }
                }
            }
        }

        return $this->render('admin/media/upload.html.twig', [
            'errors' => $errors,
            'sites'  => $this->siteContext->hasSite() ? [] : $this->siteRepository->findBy(['isActive' => true]),
            'site'   => $this->siteContext->hasSite() ? $this->siteContext->getSite() : null,
        ]);
    }
}
