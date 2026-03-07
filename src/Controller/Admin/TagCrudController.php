<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tag;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tag::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tag')
            ->setEntityLabelInPlural('Tags')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('site')->setRequired(true);
        yield TextField::new('name')->setColumns(6);
        yield SlugField::new('slug')->setTargetFieldName('name')->setColumns(6);
    }
}
