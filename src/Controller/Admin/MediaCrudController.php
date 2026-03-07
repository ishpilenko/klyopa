<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Service\MediaManager;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
            ->setSearchFields(['originalFilename', 'altText']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW) // Upload via custom form
            ->disable(Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield AssociationField::new('site');

        yield ImageField::new('path', 'Preview')
            ->setBasePath('/uploads/')
            ->onlyOnIndex();

        yield TextField::new('originalFilename', 'File');
        yield TextField::new('altText');
        yield TextField::new('mimeType')->hideOnForm();
        yield IntegerField::new('fileSize')->hideOnForm()->formatValue(fn($v) => $v ? round($v / 1024, 1) . ' KB' : '');
        yield DateTimeField::new('createdAt')->hideOnForm();
    }
}
