<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tool;
use App\Enum\ToolType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ToolCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tool::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tool')
            ->setEntityLabelInPlural('Tools')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield AssociationField::new('site')->setRequired(true);

        yield ChoiceField::new('type')
            ->setChoices(array_combine(
                array_map(fn(ToolType $t) => $t->value, ToolType::cases()),
                ToolType::cases(),
            ))
            ->setColumns(3);

        yield TextField::new('name')->setColumns(6);
        yield SlugField::new('slug')->setTargetFieldName('name')->setColumns(6)->hideOnIndex();
        yield TextareaField::new('description')->setColumns(12)->hideOnIndex();

        yield CodeEditorField::new('config')
            ->setLanguage('yaml')
            ->setColumns(12)
            ->hideOnIndex()
            ->formatValue(fn($v) => $v ? json_encode($v, JSON_PRETTY_PRINT) : '{}');

        yield TextField::new('metaTitle')->setColumns(12)->hideOnIndex();
        yield TextareaField::new('metaDescription')->setColumns(12)->hideOnIndex();
        yield BooleanField::new('isActive');
        yield DateTimeField::new('createdAt')->hideOnForm()->onlyOnDetail();
    }
}
