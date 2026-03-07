<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Redirect;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class RedirectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Redirect::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Redirect')
            ->setEntityLabelInPlural('Redirects')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['sourcePath', 'targetPath']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield AssociationField::new('site')->setRequired(true);
        yield TextField::new('sourcePath')->setColumns(5);
        yield TextField::new('targetPath')->setColumns(5);
        yield IntegerField::new('statusCode')->setColumns(2);
        yield IntegerField::new('hits')->hideOnForm();
        yield DateTimeField::new('createdAt')->hideOnForm();
    }
}
