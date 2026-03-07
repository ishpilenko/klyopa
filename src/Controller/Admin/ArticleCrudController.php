<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Enum\ArticleSchemaType;
use App\Enum\ArticleStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class ArticleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Article::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Article')
            ->setEntityLabelInPlural('Articles')
            ->setDefaultSort(['publishedAt' => 'DESC'])
            ->setSearchFields(['title', 'slug', 'excerpt', 'authorName'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        $publish = Action::new('publish', 'Publish', 'fa fa-check-circle')
            ->linkToCrudAction('publishArticle')
            ->displayIf(static fn(Article $a) => !$a->isPublished())
            ->addCssClass('btn btn-success');

        return $actions
            ->add(Crud::PAGE_INDEX, $publish)
            ->add(Crud::PAGE_DETAIL, $publish)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('site'))
            ->add(EntityFilter::new('category'))
            ->add(ChoiceFilter::new('status')->setChoices(array_combine(
                array_map(fn(ArticleStatus $s) => $s->label(), ArticleStatus::cases()),
                array_map(fn(ArticleStatus $s) => $s->value, ArticleStatus::cases()),
            )))
            ->add('isAiGenerated')
            ->add('isEvergreen');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield AssociationField::new('site')
            ->setRequired(true)
            ->hideOnDetail();

        yield AssociationField::new('category');

        yield TextField::new('title')->setColumns(12);

        yield SlugField::new('slug')
            ->setTargetFieldName('title')
            ->setColumns(12)
            ->hideOnIndex();

        yield TextareaField::new('excerpt')
            ->setNumOfRows(3)
            ->setColumns(12)
            ->hideOnIndex();

        yield TextEditorField::new('content')
            ->setTrixEditorConfig([
                'blockAttributes' => ['default' => ['tagName' => 'p']],
            ])
            ->hideOnIndex()
            ->setColumns(12);

        yield ChoiceField::new('status')
            ->setChoices(array_combine(
                array_map(fn(ArticleStatus $s) => $s->label(), ArticleStatus::cases()),
                ArticleStatus::cases(),
            ))
            ->renderAsBadges([
                ArticleStatus::Draft->value => 'secondary',
                ArticleStatus::Review->value => 'warning',
                ArticleStatus::Published->value => 'success',
                ArticleStatus::Archived->value => 'danger',
            ]);

        yield ChoiceField::new('schemaType')
            ->setChoices(array_combine(
                array_map(fn(ArticleSchemaType $t) => $t->value, ArticleSchemaType::cases()),
                ArticleSchemaType::cases(),
            ))
            ->hideOnIndex();

        yield TextField::new('metaTitle')->setColumns(12)->hideOnIndex();
        yield TextareaField::new('metaDescription')->setNumOfRows(2)->setColumns(12)->hideOnIndex();

        yield AssociationField::new('featuredImage')->hideOnIndex();
        yield AssociationField::new('tags')->hideOnIndex();

        yield TextField::new('authorName')->hideOnIndex();

        yield BooleanField::new('isAiGenerated');
        yield BooleanField::new('isEvergreen')->hideOnIndex();

        yield IntegerField::new('wordCount')->hideOnForm();
        yield IntegerField::new('readingTimeMinutes')->onlyOnDetail();

        yield DateTimeField::new('publishedAt')->hideOnForm();
        yield DateTimeField::new('createdAt')->hideOnForm()->onlyOnDetail();
    }

    public function publishArticle(): Response
    {
        /** @var Article $article */
        $article = $this->getContext()->getEntity()->getInstance();
        $article->publish();
        $this->getDoctrine()->getManager()->flush();

        $this->addFlash('success', sprintf('Article "%s" has been published.', $article->getTitle()));

        return $this->redirectToRoute('admin', ['crudAction' => 'index', 'crudControllerFqcn' => self::class]);
    }

    private function getDoctrine(): \Doctrine\Persistence\ManagerRegistry
    {
        return $this->container->get('doctrine');
    }
}
