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
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MediaCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MediaManager $mediaManager,
        private readonly EntityManagerInterface $em,
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
        $uploadAction = Action::new('uploadMedia', 'Upload Image', 'fa fa-cloud-upload')
            ->linkToRoute('admin_upload_media')
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

}
