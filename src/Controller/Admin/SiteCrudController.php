<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Site;
use App\Enum\SiteVertical;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SiteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Site::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Site')
            ->setEntityLabelInPlural('Sites')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield TextField::new('domain')->setColumns(6);
        yield TextField::new('name')->setColumns(6);

        yield ChoiceField::new('vertical')
            ->setChoices(array_combine(
                array_map(fn(SiteVertical $v) => $v->label(), SiteVertical::cases()),
                SiteVertical::cases(),
            ))
            ->setColumns(3);

        yield TextField::new('theme')->setColumns(3)->hideOnIndex();
        yield TextField::new('locale')->setColumns(3)->hideOnIndex();

        yield TextField::new('defaultMetaTitle')->setColumns(12)->hideOnIndex();
        yield TextField::new('defaultMetaDescription')->setColumns(12)->hideOnIndex();
        yield TextField::new('analyticsId')->setColumns(6)->hideOnIndex();
        yield TextField::new('searchConsoleId')->setColumns(6)->hideOnIndex();

        yield CodeEditorField::new('settings')
            ->setLanguage('yaml')
            ->hideOnIndex()
            ->setColumns(12)
            ->formatValue(fn($v) => $v ? json_encode($v, JSON_PRETTY_PRINT) : null);

        yield BooleanField::new('isActive');
        yield DateTimeField::new('createdAt')->hideOnForm()->onlyOnDetail();
    }
}
