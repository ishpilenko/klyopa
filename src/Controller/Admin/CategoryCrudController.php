<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Category')
            ->setEntityLabelInPlural('Categories')
            ->setDefaultSort(['site' => 'ASC', 'sortOrder' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield AssociationField::new('site')->setRequired(true);
        yield AssociationField::new('parent')->setRequired(false);
        yield TextField::new('name')->setColumns(6);
        yield SlugField::new('slug')->setTargetFieldName('name')->setColumns(6);
        yield TextareaField::new('description')->setNumOfRows(3)->setColumns(12)->hideOnIndex();
        yield TextField::new('metaTitle')->setColumns(12)->hideOnIndex();
        yield TextareaField::new('metaDescription')->setNumOfRows(2)->setColumns(12)->hideOnIndex();
        yield IntegerField::new('sortOrder')->setColumns(3)->hideOnIndex();
        yield BooleanField::new('isActive');
    }
}
