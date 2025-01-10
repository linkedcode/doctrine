<?php

namespace Linkedcode\Doctrine\Repository;

use App\Domain\User;
use Doctrine\ORM\EntityRepository;

class AbstractUserRepository extends EntityRepository
{
    public function createUserFromId(int $id): User
    {
        $user = User::createFromId($id);
        return $this->save($user);
    }

    public function save(User $user): User
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
        return $user;
    }
}
