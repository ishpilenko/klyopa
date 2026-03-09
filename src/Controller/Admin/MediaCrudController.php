<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Service\MediaManager;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

class MediaCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MediaManager $mediaManager,
        private readonly EntityManagerInterface $em,
        private readonly SiteContext $siteContext,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Media')
            ->setEntityLabelInPlural('Media')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['originalFilename', 'altText'])
            ->setPaginatorPageSize(40);
    }

    public function configureActions(Actions $actions): Actions
    {
        $uploadAction = Action::new('uploadPage', 'Upload Image', 'fa fa-cloud-upload')
            ->linkToCrudAction('uploadPage')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-success');

        return $actions
            ->add(Crud::PAGE_INDEX, $uploadAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield AssociationField::new('site')->hideOnForm();

        yield ImageField::new('path', 'Preview')
            ->setBasePath('/uploads/')
            ->onlyOnIndex();

        yield TextField::new('originalFilename', 'File')->hideOnForm();
        yield TextField::new('altText');
        yield TextField::new('mimeType')->hideOnForm();
        yield IntegerField::new('fileSize', 'Size')
            ->hideOnForm()
            ->formatValue(fn ($v) => $v ? round($v / 1024, 1) . ' KB' : '');
        yield DateTimeField::new('createdAt')->hideOnForm()->onlyOnDetail();
    }

    /**
     * Custom action: renders the upload form and handles file upload.
     */
    public function uploadPage(AdminContext $adminContext): Response
    {
        $request = $adminContext->getRequest();

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            if (!$this->isCsrfTokenValid('media_upload', $submittedToken)) {
                $this->addFlash('danger', 'Invalid CSRF token. Please try again.');

                return $this->redirectToRoute('admin', [
                    'crudAction'          => 'uploadPage',
                    'crudControllerFqcn'  => self::class,
                ]);
            }

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $files */
            $files   = $request->files->all()['files'] ?? [];
            $altText = trim((string) $request->request->get('alt_text', ''));

            if (empty($files)) {
                $this->addFlash('warning', 'Please select at least one file to upload.');
            } else {
                $uploaded = 0;
                $errors   = [];

                foreach ($files as $file) {
                    try {
                        $this->mediaManager->upload($file, $this->siteContext->getSite(), $altText ?: null);
                        ++$uploaded;
                    } catch (\InvalidArgumentException|\RuntimeException $e) {
                        $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
                    }
                }

                $this->em->flush();

                if ($uploaded > 0) {
                    $this->addFlash('success', sprintf('%d file(s) uploaded successfully.', $uploaded));
                }
                foreach ($errors as $error) {
                    $this->addFlash('danger', $error);
                }

                return $this->redirectToRoute('admin', [
                    'crudAction'         => 'index',
                    'crudControllerFqcn' => self::class,
                ]);
            }
        }

        return $this->render('admin/media/upload.html.twig', [
            'site' => $this->siteContext->getSite(),
        ]);
    }
}
