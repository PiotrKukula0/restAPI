<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Employee;
use Doctrine\ORM\Tools\Pagination\Paginator;

class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function searchEmployees(array $criteria, array $orderBy, int $pageSize, int $currentPage): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('e');

        if (!empty($criteria['firstName'])) {
            $queryBuilder->andWhere('e.firstName = :firstName')->setParameter('firstName', $criteria['firstName']);
        }
        if (!empty($criteria['lastName'])) {
            $queryBuilder->andWhere('e.lastName = :lastName')->setParameter('lastName', $criteria['lastName']);
        }
        if (!empty($criteria['email'])) {
            $queryBuilder->andWhere('e.email = :email')->setParameter('email', $criteria['email']);
        }
        if (!empty($criteria['gender'])) {
            $queryBuilder->andWhere('e.gender = :gender')->setParameter('gender', $criteria['gender']);
        }

        foreach ($orderBy as $field => $order) {
            $queryBuilder->addOrderBy('e.' . $field, $order);
        }

        $query = $queryBuilder->getQuery();
        $paginator = new Paginator($query);
        $paginator->setUseOutputWalkers(false);

        $paginator->getQuery()
            ->setFirstResult($pageSize * ($currentPage - 1))
            ->setMaxResults($pageSize);

        return $paginator;
    }
}
