<?php

namespace Experteam\ApiCrudBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TranslatableRepository extends ServiceEntityRepository
{
    private ?Request $request;

    public function __construct(ManagerRegistry $registry, string $entityClass, RequestStack $requestStack)
    {
        parent::__construct($registry, $entityClass);
        $this->request = $requestStack->getCurrentRequest();
    }

    public function findTranslatedById(string $id): mixed
    {
        $queryBuilder = $this->createQueryBuilder("e");

        $queryBuilder->where("e.id = :id")
            ->setParameter('id', $id);

        $query = $queryBuilder->getQuery();

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, 'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker');
        $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $this->request->getLocale());
        $query->setHint(TranslatableListener::HINT_FALLBACK, 1);

        try {
            return $query->getSingleResult();
        } catch (NoResultException | NonUniqueResultException) {
            return null;
        }
    }
}
